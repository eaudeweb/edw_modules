<?php

namespace Drupal\Tests\edw_document\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_document\Traits\DocumentTestTrait;
use Drupal\edw_document\Response\CacheableBinaryFileResponse;

/**
 * Tests the cacheable file response used to serve documents.
 *
 * Making the serve route cacheable means the dynamic page cache stores the
 * response object, and storing it means serialising it. BinaryFileResponse
 * holds an \SplFileInfo, which cannot be serialised — hence the custom
 * __serialize()/__wakeup() pair this test exists to protect.
 *
 * @coversDefaultClass \Drupal\edw_document\Response\CacheableBinaryFileResponse
 *
 * @group edw_document
 */
class CacheableBinaryFileResponseTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
  }

  /**
   * Tests that a file dependency contributes its cache tag.
   *
   * Without the tag, editing or replacing a file would leave the cached
   * response serving the old document.
   *
   * @covers ::addCacheableDependency
   */
  public function testFileDependencyAddsItsCacheTag(): void {
    $file = $this->createDocumentFile('report.pdf');

    $response = new CacheableBinaryFileResponse($file->getFileUri());
    $response->addCacheableDependency($file);

    $this->assertContains('file:' . $file->id(), $response->getCacheableMetadata()->getCacheTags());
  }

  /**
   * Tests that the response survives a serialize/unserialize round trip.
   *
   * This is exactly what the dynamic page cache does to it between the first
   * request and every later one.
   *
   * @covers ::__serialize
   * @covers ::__wakeup
   */
  public function testSurvivesSerialization(): void {
    $file = $this->createDocumentFile('report.pdf', 'The document payload.');

    $response = new CacheableBinaryFileResponse($file->getFileUri(), 200, [
      'Content-Type' => 'application/pdf',
    ]);
    $response->addCacheableDependency($file);

    // Round tripping our own response object is the behaviour under test, so
    // the unserialize() call is deliberate and its input is not user supplied.
    // phpcs:ignore DrupalPractice.FunctionCalls.InsecureUnserialize.InsecureUnserialize
    $restored = unserialize(serialize($response));

    $this->assertInstanceOf(CacheableBinaryFileResponse::class, $restored);
    $this->assertNotNull($restored->getFile(), 'The file was not restored on wakeup.');
    $this->assertSame(
      'The document payload.',
      file_get_contents($restored->getFile()->getPathname()),
      'The restored response points at different bytes.'
    );
    $this->assertSame('application/pdf', $restored->headers->get('Content-Type'));
    $this->assertContains('file:' . $file->id(), $restored->getCacheableMetadata()->getCacheTags());
  }

  /**
   * Tests the constructor defaults that differ from Symfony's.
   *
   * The response is public (so it can be cached at all) and does not compute
   * an ETag, which for a large document would mean checksumming the whole file
   * on every request.
   *
   * @covers ::__construct
   */
  public function testConstructorDefaults(): void {
    $file = $this->createDocumentFile('report.pdf');

    $response = new CacheableBinaryFileResponse($file->getFileUri());

    $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
    $this->assertNull($response->getEtag(), 'An automatic ETag would checksum the whole file per request.');
    $this->assertNotNull($response->getLastModified());
  }

}
