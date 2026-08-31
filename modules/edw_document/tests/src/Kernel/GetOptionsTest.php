<?php

namespace Drupal\Tests\edw_document\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_document\Traits\DocumentTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests the format and language options offered for a set of documents.
 *
 * These options decide which checkboxes the download modal shows, and the
 * field formatter uses their counts to choose between a direct download link
 * and the modal.
 *
 * @coversDefaultClass \Drupal\edw_document\Services\DocumentManager
 *
 * @group edw_document
 */
class GetOptionsTest extends KernelTestBase {

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
   * The document manager under test.
   *
   * @var \Drupal\edw_document\Services\DocumentManager
   */
  protected $documentManager;

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

    ConfigurableLanguage::createFromLangcode('fr')->save();

    NodeType::create(['type' => 'document', 'name' => 'Document'])->save();
    $this->createFilesField('node', 'document');

    $this->documentManager = \Drupal::service('edw_document.document.manager');
  }

  /**
   * Tests the formats and languages found across several documents.
   *
   * @covers ::getOptions
   */
  public function testCollectsFormatsAndLanguages(): void {
    $englishNode = $this->createDocumentNode([
      $this->createDocumentFile('report.pdf'),
      $this->createDocumentFile('data.xlsx'),
    ], 'en');
    $frenchNode = $this->createDocumentNode([
      $this->createDocumentFile('rapport.pdf'),
    ], 'fr');

    [$formats, $languages] = $this->documentManager
      ->getOptions([$englishNode->id(), $frenchNode->id()], 'field_files');

    $this->assertEqualsCanonicalizing(['pdf', 'spreadsheet'], array_values($formats));
    $this->assertEqualsCanonicalizing(['en', 'fr'], array_values($languages));
  }

  /**
   * Tests that files with the display column off are not offered.
   *
   * The display checkbox on a file field is how editors hide an attachment
   * from the front end; a hidden file must not appear as a download option.
   *
   * @covers ::getOptions
   */
  public function testIgnoresFilesWithDisplayOff(): void {
    $visible = $this->createDocumentFile('visible.pdf');
    $hidden = $this->createDocumentFile('hidden.xlsx');

    $node = Node::create([
      'type' => 'document',
      'title' => 'Test document',
      'field_files' => array_merge(
        $this->fileFieldValues([$visible], TRUE),
        $this->fileFieldValues([$hidden], FALSE)
      ),
    ]);
    $node->save();

    [$formats] = $this->documentManager->getOptions([$node->id()], 'field_files');

    $this->assertSame(['pdf'], array_values($formats));
  }

  /**
   * Tests that duplicate formats and languages are collapsed.
   *
   * Removing duplicates preserves the original keys, so the returned arrays
   * have gaps. Callers rely on count() and in_array(), both of which are
   * key-agnostic — but anything that assumes a list (array_values(), $x[0],
   * json_encode() producing an array rather than an object) would break. This
   * pins the shape so a future tidy-up is a deliberate choice.
   *
   * @covers ::getOptions
   */
  public function testDuplicatesAreCollapsedAndKeysAreNotReindexed(): void {
    $node = $this->createDocumentNode([
      $this->createDocumentFile('one.pdf'),
      $this->createDocumentFile('two.pdf'),
      $this->createDocumentFile('three.docx'),
    ], 'en');

    [$formats, $languages] = $this->documentManager->getOptions([$node->id()], 'field_files');

    $this->assertCount(2, $formats);
    $this->assertCount(1, $languages);
    $this->assertEqualsCanonicalizing(['pdf', 'document'], array_values($formats));

    // The keys are the positions the values first appeared at, not 0..n.
    $this->assertNotSame(range(0, count($formats) - 1), array_keys($formats));
  }

  /**
   * Tests that documents with no files produce no options.
   *
   * @covers ::getOptions
   */
  public function testNoFilesProducesNoOptions(): void {
    $node = $this->createDocumentNode([], 'en');

    [$formats, $languages] = $this->documentManager->getOptions([$node->id()], 'field_files');

    $this->assertSame([], $formats);
    $this->assertSame([], $languages);
  }

  /**
   * Creates a document node carrying the given files.
   *
   * @param \Drupal\file\FileInterface[] $files
   *   The files to attach.
   * @param string $langcode
   *   The node language.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved node.
   */
  protected function createDocumentNode(array $files, string $langcode = 'en') {
    $node = Node::create([
      'type' => 'document',
      'title' => 'Test document',
      'langcode' => $langcode,
      'field_files' => $this->fileFieldValues($files),
    ]);
    $node->save();

    return $node;
  }

}
