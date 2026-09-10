<?php

namespace Drupal\Tests\edw_document\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_document\Traits\DocumentTestTrait;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Tests the zip archive built for multi-file downloads.
 *
 * @coversDefaultClass \Drupal\edw_document\Services\DocumentManager
 *
 * @group edw_document
 */
class GenerateArchiveTest extends KernelTestBase {

  use DocumentTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
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
    $this->installSchema('file', ['file_usage']);

    $this->documentManager = \Drupal::service('edw_document.document.manager');
  }

  /**
   * Tests the response headers of a generated archive.
   *
   * @covers ::generateArchive
   */
  public function testArchiveResponseHeaders(): void {
    $files = [
      $this->createDocumentFile('first.pdf', 'First document.'),
      $this->createDocumentFile('second.pdf', 'Second document.'),
    ];

    $response = $this->documentManager->generateArchive($files);

    $this->assertInstanceOf(BinaryFileResponse::class, $response);
    $this->assertSame('application/zip', $response->headers->get('Content-Type'));
    $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    $this->assertStringContainsString('download.zip', $response->headers->get('Content-Disposition'));

    // Content-Length is computed with filesize() on a stream URI; a zero here
    // means the stream wrapper stopped resolving and browsers get a truncated
    // download.
    $this->assertGreaterThan(0, (int) $response->headers->get('Content-Length'));
  }

  /**
   * Tests that the archive actually contains the files.
   *
   * @covers ::generateArchive
   */
  public function testArchiveContainsEveryFile(): void {
    $files = [
      $this->createDocumentFile('first.pdf', 'First document.'),
      $this->createDocumentFile('second.docx', 'Second document.'),
    ];

    $response = $this->documentManager->generateArchive($files);

    $entries = $this->readArchive($response);

    $this->assertCount(2, $entries);
    $this->assertSame('First document.', $entries['first.pdf'] ?? NULL);
    $this->assertSame('Second document.', $entries['second.docx'] ?? NULL);
  }

  /**
   * Tests that files sharing a name do not overwrite each other.
   *
   * Entries are keyed by the file label, and two documents in different
   * languages routinely carry the same label — so the archive silently loses
   * one of them and the user downloads fewer files than they selected.
   *
   * @covers ::generateArchive
   */
  public function testFilesWithTheSameNameAreBothArchived(): void {
    $first = $this->createDocumentFile('report.pdf', 'English version.');
    $second = $this->createDocumentFile('report.pdf', 'French version.');
    // The second file lands on disk as report_0.pdf, but both entities carry
    // the same label, which is what the archive keys on.
    $second->setFilename('report.pdf');
    $second->save();

    $response = $this->documentManager->generateArchive([$first, $second]);

    $this->assertCount(
      2,
      $this->readArchive($response),
      'Two files with the same label must both end up in the archive.'
    );
  }

  /**
   * Tests that empty files are skipped rather than archived as zero bytes.
   *
   * @covers ::generateArchive
   */
  public function testEmptyFilesAreSkipped(): void {
    $files = [
      $this->createDocumentFile('good.pdf', 'Real content.'),
      $this->createDocumentFile('empty.pdf', ''),
    ];

    $entries = $this->readArchive($this->documentManager->generateArchive($files));

    $this->assertSame(['good.pdf'], array_keys($entries));
  }

  /**
   * Tests that the temporary archive is cleaned up after sending.
   *
   * Without this the temporary directory grows by one zip per download.
   *
   * @covers ::generateArchive
   */
  public function testTemporaryArchiveIsDeletedAfterSend(): void {
    $files = [
      $this->createDocumentFile('first.pdf', 'First.'),
      $this->createDocumentFile('second.pdf', 'Second.'),
    ];

    $response = $this->documentManager->generateArchive($files);

    $reflection = new \ReflectionProperty(BinaryFileResponse::class, 'deleteFileAfterSend');
    $reflection->setAccessible(TRUE);
    $this->assertTrue($reflection->getValue($response));
  }

  /**
   * Reads the entries of the archive behind a response.
   *
   * @param \Symfony\Component\HttpFoundation\BinaryFileResponse $response
   *   The archive response.
   *
   * @return array
   *   Entry name keyed to entry contents.
   */
  protected function readArchive(BinaryFileResponse $response): array {
    // The response carries a stream URI, which ZipArchive cannot open.
    $path = \Drupal::service('file_system')->realpath($response->getFile()->getPathname());

    $zip = new \ZipArchive();
    $this->assertTrue($zip->open($path) === TRUE, 'The generated archive could not be opened.');

    $entries = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
      $name = $zip->getNameIndex($i);
      $entries[$name] = $zip->getFromIndex($i);
    }
    $zip->close();

    return $entries;
  }

}
