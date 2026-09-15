<?php

namespace Drupal\Tests\edw_utilities\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\edw_utilities\Controller\TermsAutocompleteController;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the term autocomplete endpoint.
 *
 * This backs the site-wide search suggestions: as a visitor types, the
 * endpoint returns terms from one vocabulary, each rendered as a link. What
 * counts as a match, and where the link points, are both left to the site
 * through hooks — different vocabularies want different behaviour.
 *
 * @coversDefaultClass \Drupal\edw_utilities\Controller\TermsAutocompleteController
 *
 * @group edw_utilities
 */
class TermsAutocompleteTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'taxonomy',
    'edw_utilities',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['user']);

    $this->setUpCurrentUser([], ['access content']);

    Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords'])->save();
    Vocabulary::create(['vid' => 'countries', 'name' => 'Countries'])->save();
  }

  /**
   * Tests that nothing typed means nothing suggested.
   *
   * The endpoint is hit on every keystroke, so the empty case has to return
   * early rather than loading a whole vocabulary.
   *
   * @covers ::autocomplete
   */
  public function testNothingTypedMeansNothingSuggested(): void {
    $this->createTerm('keywords', 'Climate change');

    $this->assertSame([], $this->suggestionsFor('keywords', ''));
  }

  /**
   * Tests that suggestions come from the vocabulary in the path.
   *
   * @covers ::autocomplete
   */
  public function testSuggestionsComeFromTheVocabularyInThePath(): void {
    $this->createTerm('keywords', 'Climate change');
    $this->createTerm('countries', 'Chile');

    $suggestions = $this->suggestionsFor('keywords', 'Cl');

    $this->assertSame(['Climate change'], array_column($suggestions, 'value'));
  }

  /**
   * Tests that each suggestion carries a label and a plain value.
   *
   * The value is what lands in the text field; the label is markup shown in
   * the dropdown, so it is a link to the term.
   *
   * @covers ::autocomplete
   */
  public function testEachSuggestionCarriesLabelAndPlainValue(): void {
    $term = $this->createTerm('keywords', 'Climate change');

    $suggestion = $this->suggestionsFor('keywords', 'Cl')[0];

    $this->assertSame('Climate change', $suggestion['value']);
    $this->assertStringContainsString('Climate change', $suggestion['label']);
    $this->assertStringContainsString('<a href=', $suggestion['label']);
    $this->assertStringContainsString('/taxonomy/term/' . $term->id(), $suggestion['label']);
  }

  /**
   * Tests that the whole vocabulary is offered until a site narrows it.
   *
   * The controller filters by vocabulary only — matching what was typed is
   * left entirely to hook_terms_autocomplete_query_alter(). A site that does
   * not implement it gets every term in the vocabulary back on every
   * keystroke, which is worth knowing before pointing this at a large one.
   *
   * @covers ::autocomplete
   */
  public function testTheWholeVocabularyIsOfferedUntilSiteNarrowsIt(): void {
    $this->createTerm('keywords', 'Climate change');
    $this->createTerm('keywords', 'Biodiversity');

    $suggestions = $this->suggestionsFor('keywords', 'Climate');

    $this->assertCount(2, $suggestions);
  }

  /**
   * Tests that a site can narrow the suggestions to what was typed.
   *
   * @see hook_terms_autocomplete_query_alter()
   */
  public function testSiteCanNarrowTheSuggestionsToWhatWasTyped(): void {
    \Drupal::service('module_installer')->install(['edw_utilities_test']);
    $this->createTerm('keywords', 'Climate change');
    $this->createTerm('keywords', 'Biodiversity');

    $suggestions = $this->suggestionsFor('keywords', 'Climate');

    $this->assertSame(['Climate change'], array_column($suggestions, 'value'));
  }

  /**
   * Tests that a site can rewrite the suggestion labels.
   *
   * @see hook_terms_autocomplete_label_alter()
   */
  public function testSiteCanRewriteTheSuggestionLabels(): void {
    \Drupal::service('module_installer')->install(['edw_utilities_test']);
    $this->createTerm('keywords', 'Climate change');

    $suggestions = $this->suggestionsFor('keywords', 'Climate');

    $this->assertSame('Filter by Climate change', $suggestions[0]['label']);
  }

  /**
   * Tests that extra query properties reach the site's hook.
   *
   * The endpoint is called from several places with different needs — a
   * result limit, say — and passes them straight through.
   *
   * @covers ::autocomplete
   */
  public function testExtraQueryPropertiesReachTheHook(): void {
    \Drupal::service('module_installer')->install(['edw_utilities_test']);
    $this->createTerm('keywords', 'Climate change');
    $this->createTerm('keywords', 'Climate finance');

    $suggestions = $this->suggestionsFor('keywords', 'Climate', ['limit' => 1]);

    $this->assertCount(1, $suggestions);
  }

  /**
   * Tests that markup typed into the box is stripped before use.
   *
   * The typed text is handed to site hooks, which may put it into a query or
   * a label, so it is filtered on the way in.
   *
   * @covers ::autocomplete
   */
  public function testMarkupTypedIntoTheBoxIsStripped(): void {
    \Drupal::service('module_installer')->install(['edw_utilities_test']);
    $this->createTerm('keywords', 'alert(1)');

    $suggestions = $this->suggestionsFor('keywords', '<script>alert(1)</script>');

    // The tags are gone, so the surviving text is what was matched on.
    $this->assertSame(['alert(1)'], array_column($suggestions, 'value'));
  }

  /**
   * Tests that a vocabulary with no terms suggests nothing.
   *
   * @covers ::autocomplete
   */
  public function testAnEmptyVocabularySuggestsNothing(): void {
    $this->assertSame([], $this->suggestionsFor('keywords', 'Cl'));
  }

  /**
   * Returns the decoded suggestions for a typed string.
   *
   * @param string $vid
   *   The vocabulary to search.
   * @param string $input
   *   What the visitor typed.
   * @param array $properties
   *   Extra properties passed through to the site's hooks.
   *
   * @return array
   *   The suggestions.
   */
  protected function suggestionsFor(string $vid, string $input, array $properties = []): array {
    $query = ['q' => $input];
    if ($properties) {
      $query['properties'] = $properties;
    }
    $request = Request::create('/search/autocomplete/' . $vid . '/terms', 'GET', $query);

    $controller = TermsAutocompleteController::create(\Drupal::getContainer());
    $response = $controller->autocomplete($request, $vid);

    return json_decode($response->getContent(), TRUE);
  }

  /**
   * Creates a term.
   *
   * @param string $vid
   *   The vocabulary.
   * @param string $name
   *   The term name.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   The saved term.
   */
  protected function createTerm(string $vid, string $name) {
    $term = Term::create(['vid' => $vid, 'name' => $name]);
    $term->save();

    return $term;
  }

}
