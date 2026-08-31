<?php

namespace Drupal\Tests\edw_document\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_document\Traits\DocumentTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests the file selection behind every download.
 *
 * Selecting files turns "these entities, these formats, these languages" into
 * the actual list of files to serve or zip, and is shared by the download
 * controller, the download form and the field formatter.
 *
 * @coversDefaultClass \Drupal\edw_document\Services\DocumentManager
 *
 * @group edw_document
 */
class GetFilteredFilesTest extends KernelTestBase {

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
   * Tests that only the requested formats come back.
   *
   * @covers ::getFilteredFiles
   */
  public function testFiltersByFormat(): void {
    $pdf = $this->createDocumentFile('report.pdf');
    $doc = $this->createDocumentFile('report.docx');
    $node = $this->createDocumentNode([$pdf, $doc]);

    $files = $this->documentManager->getFilteredFiles([$node->id()], [], 'field_files', ['pdf'], ['en']);

    $this->assertSame($this->fileIds([$pdf]), array_keys($files));
  }

  /**
   * Tests that files whose bytes are gone are dropped.
   *
   * A managed file entity whose payload has been removed from disk would
   * otherwise be handed to the zip builder or redirected to, producing a
   * corrupt archive or a 404. The guard uses getDestinationFilename() with
   * EXISTS_ERROR, whose truthiness reads backwards, so it is easy to invert by
   * accident.
   *
   * @covers ::getFilteredFiles
   */
  public function testDropsFilesMissingFromDisk(): void {
    $present = $this->createDocumentFile('present.pdf');
    $missing = $this->createDocumentFile('missing.pdf');
    $node = $this->createDocumentNode([$present, $missing]);

    $this->deleteFileBytes($missing);

    $files = $this->documentManager->getFilteredFiles([$node->id()], [], 'field_files', ['pdf'], ['en']);

    $this->assertSame($this->fileIds([$present]), array_keys($files));
  }

  /**
   * Tests that files are filtered by the field table's langcode.
   *
   * @covers ::getFilteredFiles
   */
  public function testFiltersByLanguage(): void {
    $english = $this->createDocumentFile('report-en.pdf');
    $french = $this->createDocumentFile('report-fr.pdf');

    $englishNode = $this->createDocumentNode([$english], 'en');
    $frenchNode = $this->createDocumentNode([$french], 'fr');
    $ids = [$englishNode->id(), $frenchNode->id()];

    $files = $this->documentManager->getFilteredFiles($ids, [], 'field_files', ['pdf'], ['fr']);
    $this->assertSame($this->fileIds([$french]), array_keys($files));

    $files = $this->documentManager->getFilteredFiles($ids, [], 'field_files', ['pdf'], ['en', 'fr']);
    $this->assertEqualsCanonicalizing($this->fileIds([$english, $french]), array_keys($files));
  }

  /**
   * Tests that files can be pulled from an older revision.
   *
   * Documents are versioned, and the download form passes revision ids so that
   * a file removed from the current revision is still downloadable from the
   * revision that had it.
   *
   * @covers ::getFilteredFiles
   */
  public function testIncludesFilesFromRequestedRevisions(): void {
    $original = $this->createDocumentFile('original.pdf');
    $node = $this->createDocumentNode([$original]);
    $oldRevisionId = $node->getRevisionId();

    // Replace the file in a new default revision.
    $replacement = $this->createDocumentFile('replacement.pdf');
    $node->set('field_files', $this->fileFieldValues([$replacement]));
    $node->setNewRevision(TRUE);
    $node->save();

    // Without the revision id, only the current file is found.
    $current = $this->documentManager->getFilteredFiles([$node->id()], [], 'field_files', ['pdf'], ['en']);
    $this->assertSame($this->fileIds([$replacement]), array_keys($current));

    // With it, the superseded file comes back too.
    $withRevision = $this->documentManager->getFilteredFiles([$node->id()], [$oldRevisionId], 'field_files', ['pdf'], ['en']);
    $this->assertEqualsCanonicalizing(
      $this->fileIds([$original, $replacement]),
      array_keys($withRevision)
    );
  }

  /**
   * Tests that no matches returns an empty array rather than throwing.
   *
   * This method used to throw NotFoundHttpException, which surfaced as a hard
   * error page instead of the controller's 204 or the formatter's logged
   * warning. Callers now branch on an empty array.
   *
   * @covers ::getFilteredFiles
   */
  public function testEmptyResultReturnsAnArray(): void {
    $node = $this->createDocumentNode([$this->createDocumentFile('report.pdf')]);

    $this->assertSame([], $this->documentManager->getFilteredFiles([$node->id()], [], 'field_files', ['spreadsheet'], ['en']));
    $this->assertSame([], $this->documentManager->getFilteredFiles([$node->id()], [], 'field_files', ['pdf'], ['fr']));
    $this->assertSame([], $this->documentManager->getFilteredFiles([-1], [], 'field_files', ['pdf'], ['en']));
  }

  /**
   * Tests that setEntityTypeId() selects which field table is queried.
   *
   * The download controller hardcodes 'media', while the field formatter uses
   * whichever entity type it is rendering, so this switch has to work.
   *
   * @covers ::setEntityTypeId
   * @covers ::getFilteredFiles
   */
  public function testEntityTypeIdSelectsTheFieldTable(): void {
    $file = $this->createDocumentFile('report.pdf');
    $node = $this->createDocumentNode([$file]);

    $this->documentManager->setEntityTypeId('node');
    $this->assertSame($this->fileIds([$file]), array_keys(
      $this->documentManager->getFilteredFiles([$node->id()], [], 'field_files', ['pdf'], ['en'])
    ));

    // A different entity type looks at a different table, so it finds nothing
    // — and must not fall back to the node table.
    $this->documentManager->setEntityTypeId('media');
    $this->expectException(\Exception::class);
    $this->documentManager->getFilteredFiles([$node->id()], [], 'field_files', ['pdf'], ['en']);
  }

  /**
   * Returns the ids of the given files, typed the way storage keys them.
   *
   * @param \Drupal\file\FileInterface[] $files
   *   The files.
   *
   * @return int[]
   *   The file ids.
   */
  protected function fileIds(array $files): array {
    return array_map(function ($file) {
      return (int) $file->id();
    }, array_values($files));
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
