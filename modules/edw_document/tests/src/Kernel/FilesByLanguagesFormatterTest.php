<?php

namespace Drupal\Tests\edw_document\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_document\Traits\DocumentTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\Role;

/**
 * Tests the table of a document's files grouped by site language.
 *
 * Where DropdownFilesFormatter reads a per-item language column, this one
 * groups by *entity translation*: the English translation's files are the
 * English row. It walks every configured language rather than only the ones
 * the document has been translated into, so the table keeps a stable set of
 * rows across documents.
 *
 * @coversDefaultClass \Drupal\edw_document\Plugin\Field\FieldFormatter\FilesByLanguagesFormatterBase
 *
 * @group edw_document
 */
class FilesByLanguagesFormatterTest extends KernelTestBase {

  use DocumentTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'node',
    'language',
    'edw_document',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['user', 'language']);

    // This formatter checks 'view' access on the referenced files rather than
    // 'download', so the viewer needs 'access content' for public files.
    $anonymous = Role::load(Role::ANONYMOUS_ID);
    $anonymous->grantPermission('access content');
    $anonymous->save();

    // Added in this order on purpose: the language manager sorts by label, so
    // the configured order is en, fr, de — which is not alphabetical by
    // langcode, and so proves the formatter's ksort() actually runs.
    ConfigurableLanguage::createFromLangcode('fr')->save();
    ConfigurableLanguage::createFromLangcode('de')->save();

    NodeType::create(['type' => 'document', 'name' => 'Document'])->save();
    $this->createFilesField('node', 'document');
  }

  /**
   * Tests that each translation's files land in that language's row.
   *
   * @covers ::viewElements
   * @covers ::getLanguageElements
   */
  public function testEachTranslationsFilesLandInItsOwnRow(): void {
    $node = $this->createTranslatedDocument([
      'en' => $this->createDocumentFile('report-en.pdf'),
      'fr' => $this->createDocumentFile('rapport-fr.pdf'),
    ]);

    $rows = $this->buildField($node)['#rows'];

    $this->assertCount(1, $rows['en']);
    $this->assertCount(1, $rows['fr']);
    $this->assertSame('report-en.pdf', $rows['en'][0]['content']['#file']->getFilename());
    $this->assertSame('rapport-fr.pdf', $rows['fr'][0]['content']['#file']->getFilename());
  }

  /**
   * Tests that a language the document is not translated into stays empty.
   *
   * The row is still present: the template renders a full language list and
   * decides for itself how to show a language with nothing behind it.
   *
   * @covers ::viewElements
   */
  public function testUntranslatedLanguagesGetAnEmptyRow(): void {
    $node = $this->createTranslatedDocument([
      'en' => $this->createDocumentFile('report-en.pdf'),
    ]);

    $rows = $this->buildField($node)['#rows'];

    $this->assertArrayHasKey('de', $rows);
    $this->assertSame([], $rows['de']);
    $this->assertSame([], $rows['fr']);
  }

  /**
   * Tests that the rows are ordered by langcode, not by language weight.
   *
   * @covers ::viewElements
   */
  public function testRowsAreSortedByLangcode(): void {
    $node = $this->createTranslatedDocument([
      'en' => $this->createDocumentFile('report-en.pdf'),
    ]);

    // The language manager hands them over sorted by label — English, French,
    // German — and the formatter re-sorts by key.
    $this->assertSame(['en', 'fr', 'de'], array_keys(\Drupal::languageManager()->getLanguages()));
    $this->assertSame(['de', 'en', 'fr'], array_keys($this->buildField($node)['#rows']));
  }

  /**
   * Tests that every row entry carries a link and its file extension.
   *
   * The template prints the extension as the format column, so it comes from
   * the URI rather than from the MIME type despite the key's name.
   *
   * @covers ::getLanguageElements
   */
  public function testEachEntryCarriesLinkAndExtension(): void {
    $node = $this->createTranslatedDocument([
      'en' => $this->createDocumentFile('report-en.pdf'),
    ]);

    $entry = $this->buildField($node)['#rows']['en'][0];

    $this->assertSame('pdf', $entry['filemime']['#markup']);
    $this->assertSame('file_link', $entry['content']['#theme']);
    $this->assertSame('report-en.pdf', $entry['content']['#description']);
  }

  /**
   * Tests that the current language is passed to the template.
   *
   * The table highlights the row matching the language being browsed in.
   *
   * @covers ::viewElements
   */
  public function testTheCurrentLanguageIsPassedToTheTemplate(): void {
    $node = $this->createTranslatedDocument([
      'en' => $this->createDocumentFile('report-en.pdf'),
    ]);

    $build = $this->buildField($node);

    $this->assertSame('files_group_by_language', $build['#theme']);
    $this->assertSame(
      \Drupal::languageManager()->getCurrentLanguage()->getId(),
      $build['#current_language']
    );
  }

  /**
   * Tests that a translation holding several files lists all of them.
   *
   * @covers ::getLanguageElements
   */
  public function testTranslationMayHoldSeveralFiles(): void {
    $node = Node::create([
      'type' => 'document',
      'title' => 'Annual report',
      'langcode' => 'en',
      'field_files' => $this->fileFieldValues([
        $this->createDocumentFile('report-en.pdf'),
        $this->createDocumentFile('annexes-en.docx'),
      ]),
    ]);
    $node->save();

    $rows = $this->buildField($node)['#rows'];

    $this->assertCount(2, $rows['en']);
    $this->assertSame('pdf', $rows['en'][0]['filemime']['#markup']);
    $this->assertSame('docx', $rows['en'][1]['filemime']['#markup']);
  }

  /**
   * Builds the file field of a node with the grouped formatter.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to render.
   *
   * @return array
   *   The field's render array.
   */
  protected function buildField(NodeInterface $node): array {
    return $node->get('field_files')->view([
      'type' => 'files_group_by_language',
      'label' => 'hidden',
      'settings' => [],
    ]);
  }

  /**
   * Creates a document translated into each of the given languages.
   *
   * @param \Drupal\file\FileInterface[] $filesByLangcode
   *   One file per langcode. The first langcode becomes the source language.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved node, in its source language.
   */
  protected function createTranslatedDocument(array $filesByLangcode): NodeInterface {
    $langcodes = array_keys($filesByLangcode);
    $source = array_shift($langcodes);

    $node = Node::create([
      'type' => 'document',
      'title' => 'Annual report',
      'langcode' => $source,
      'field_files' => $this->fileFieldValues([$filesByLangcode[$source]]),
    ]);

    foreach ($langcodes as $langcode) {
      $translation = $node->addTranslation($langcode, $node->toArray());
      $translation->set('field_files', $this->fileFieldValues([$filesByLangcode[$langcode]]));
    }

    $node->save();

    return $node;
  }

}
