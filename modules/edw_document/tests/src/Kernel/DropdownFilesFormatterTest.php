<?php

namespace Drupal\Tests\edw_document\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_document\Traits\DocumentTestTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\Role;

/**
 * Tests the language dropdown built for a document's files.
 *
 * This formatter renders one "download" control listing a document's files by
 * language. Files with no language are deliberately dropped: the dropdown is
 * a language picker, and an entry with nothing to pick would be unlabelled.
 *
 * @coversDefaultClass \Drupal\edw_document\Plugin\Field\FieldFormatter\DropdownFilesFormatter
 *
 * @group edw_document
 */
class DropdownFilesFormatterTest extends KernelTestBase {

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
    $this->installConfig(['user']);

    $anonymous = Role::load(Role::ANONYMOUS_ID);
    $anonymous->grantPermission('access content');
    $anonymous->save();

    NodeType::create(['type' => 'document', 'name' => 'Document'])->save();
    $this->createFileLanguageField('node', 'document');
  }

  /**
   * Tests that files without a language are left out of the dropdown.
   *
   * @covers ::viewElements
   */
  public function testFilesWithoutLanguageAreDropped(): void {
    $node = $this->createDocumentNode([
      $this->fileLanguageValue($this->createDocumentFile('report-fr.pdf'), 'fr'),
      $this->fileLanguageValue($this->createDocumentFile('annex.pdf'), ''),
      $this->fileLanguageValue($this->createDocumentFile('report-en.pdf'), 'en'),
    ]);

    $build = $this->buildField($node);

    $this->assertArrayHasKey(0, $build['#items']);
    $this->assertArrayNotHasKey(1, $build['#items']);
    $this->assertArrayHasKey(2, $build['#items']);
  }

  /**
   * Tests that the surviving entries keep their language labels.
   *
   * The dropdown inherits its labelling from FileWithLanguageFormatter, so
   * the entries are the language names the user picks between.
   *
   * @covers ::viewElements
   */
  public function testTheRemainingEntriesAreLabelledByLanguage(): void {
    $node = $this->createDocumentNode([
      $this->fileLanguageValue($this->createDocumentFile('report-fr.pdf'), 'fr'),
      $this->fileLanguageValue($this->createDocumentFile('report-en.pdf'), 'en'),
    ]);

    $build = $this->buildField($node);

    $this->assertSame('French', (string) $build['#items'][0]['#description']);
    $this->assertSame('English', (string) $build['#items'][1]['#description']);
  }

  /**
   * Tests that the dropdown is handed the pieces its template needs.
   *
   * The template links each entry against the host entity, so `#object` has
   * to be the document rather than the field or the file.
   *
   * @covers ::viewElements
   */
  public function testTheDropdownIsBuiltForTheHostEntity(): void {
    $node = $this->createDocumentNode([
      $this->fileLanguageValue($this->createDocumentFile('report-fr.pdf'), 'fr'),
    ]);

    $build = $this->buildField($node);

    $this->assertSame('dropdown_file_language', $build['#theme']);
    $this->assertSame($node->id(), $build['#object']->id());
    $this->assertContains('download-files', $build['#attributes']['class']);
  }

  /**
   * Tests that a document whose files have no language yields no entries.
   *
   * The wrapper is still returned — the template decides what an empty
   * dropdown looks like — but it must not carry the language-less files.
   *
   * @covers ::viewElements
   */
  public function testDocumentWithNoLanguagesYieldsAnEmptyDropdown(): void {
    $node = $this->createDocumentNode([
      $this->fileLanguageValue($this->createDocumentFile('annex-one.pdf'), ''),
      $this->fileLanguageValue($this->createDocumentFile('annex-two.pdf'), ''),
    ]);

    $build = $this->buildField($node);

    $this->assertSame('dropdown_file_language', $build['#theme']);
    $this->assertSame([], $build['#items']);
  }

  /**
   * Tests that hidden files stay out of the dropdown.
   *
   * @covers ::viewElements
   */
  public function testHiddenFilesAreNotOffered(): void {
    $node = $this->createDocumentNode([
      $this->fileLanguageValue($this->createDocumentFile('report-fr.pdf'), 'fr', '', TRUE),
      $this->fileLanguageValue($this->createDocumentFile('report-en.pdf'), 'en', '', FALSE),
    ]);

    $build = $this->buildField($node);

    $this->assertSame([0], array_keys($build['#items']));
    $this->assertSame('French', (string) $build['#items'][0]['#description']);
  }

  /**
   * Builds the file field of a node with the dropdown formatter.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to render.
   *
   * @return array
   *   The field's render array.
   */
  protected function buildField(NodeInterface $node): array {
    return $node->get('field_files')->view([
      'type' => 'dropdown_file_language_formatter',
      'label' => 'hidden',
      'settings' => [],
    ]);
  }

  /**
   * Creates a document node carrying the given field items.
   *
   * @param array $values
   *   Field item values, as built by fileLanguageValue().
   *
   * @return \Drupal\node\NodeInterface
   *   The saved node.
   */
  protected function createDocumentNode(array $values): NodeInterface {
    $node = Node::create([
      'type' => 'document',
      'title' => 'Annual report',
      'langcode' => 'en',
      'field_files' => $values,
    ]);
    $node->save();

    return $node;
  }

}
