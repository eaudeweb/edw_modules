<?php

namespace Drupal\Tests\edw_document\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;
use Drupal\edw_document\Services\DocumentManager;

/**
 * Tests the extension to format mapping.
 *
 * This mapping decides which checkboxes the download modal offers and which
 * files getFilteredFiles() hands back, so every consumer of the download path
 * depends on it.
 *
 * @coversDefaultClass \Drupal\edw_document\Services\DocumentManager
 *
 * @group edw_document
 */
class DocumentManagerUriTypeTest extends UnitTestCase {

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

    $this->documentManager = new DocumentManager(
      $this->createMock(CurrentRouteMatch::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(ModuleExtensionList::class),
      $this->createMock(FileUrlGeneratorInterface::class),
      $this->createMock(FileSystemInterface::class),
      $this->createMock(LanguageManagerInterface::class),
      $this->createMock(Connection::class)
    );
  }

  /**
   * Tests that every mapped extension resolves to its format.
   *
   * @covers ::getUriType
   *
   * @dataProvider providerKnownExtensions
   */
  public function testKnownExtensions(string $uri, string $expected): void {
    $this->assertSame($expected, $this->documentManager->getUriType($uri));
  }

  /**
   * Data provider for testKnownExtensions().
   *
   * @return array
   *   Test cases, each a URI and the format it should map to.
   */
  public function providerKnownExtensions(): array {
    $cases = [
      'document' => [
        'csv', 'doc', 'docx', 'fodg', 'fodt', 'odf', 'odg', 'odt', 'pages',
        'rtf',
      ],
      'pdf' => ['pdf'],
      'text' => ['txt'],
      'image' => ['gif', 'jpg', 'jpeg', 'png', 'svg'],
      'presentation' => ['key', 'fodp', 'odp', 'ppt', 'pptx'],
      'spreadsheet' => ['numbers', 'fods', 'ods', 'xls', 'xlsx'],
      'link' => ['shtml', 'htm'],
      'video' => ['mp4', 'mov', 'avi'],
    ];

    $data = [];
    foreach ($cases as $format => $extensions) {
      foreach ($extensions as $extension) {
        $data[$extension] = ["public://report.$extension", $format];
      }
    }

    return $data;
  }

  /**
   * Tests that the extension is matched case-insensitively.
   *
   * Uploads routinely arrive with an upper-case extension; without the
   * strtolower() they would fall through to NULL and disappear from the
   * download options.
   *
   * @covers ::getUriType
   */
  public function testExtensionIsCaseInsensitive(): void {
    $this->assertSame('pdf', $this->documentManager->getUriType('public://REPORT.PDF'));
    $this->assertSame('document', $this->documentManager->getUriType('public://Report.DocX'));
  }

  /**
   * Tests the URIs that map to no format at all.
   *
   * @covers ::getUriType
   *
   * @dataProvider providerUnmappedUris
   */
  public function testUnmappedUris(string $uri): void {
    $this->assertNull($this->documentManager->getUriType($uri));
  }

  /**
   * Data provider for testUnmappedUris().
   *
   * @return array
   *   Test cases, each a URI that should map to NULL.
   */
  public function providerUnmappedUris(): array {
    return [
      'no extension' => ['public://report'],
      'unknown extension' => ['public://report.exe'],
      'double extension keeps only the last' => ['public://archive.tar.gz'],
      'empty string' => [''],
      'trailing dot' => ['public://report.'],
      // pathinfo() has no idea about query strings, so it reports the extension
      // as "pdf?v=1". Reachable from getOptions(), which passes raw file URIs.
      'query string defeats the lookup' => ['public://report.pdf?v=1'],
    ];
  }

  /**
   * Tests the archive directory root and its settings override.
   *
   * @covers ::getDirectoryRoot
   */
  public function testGetDirectoryRoot(): void {
    new Settings([]);
    $this->assertSame('public://downloads', $this->documentManager->getDirectoryRoot());

    new Settings(['edw_document_directory' => 'private://archives']);
    $this->assertSame('private://archives', $this->documentManager->getDirectoryRoot());

    // Leave the singleton clean for whatever runs next in this process.
    new Settings([]);
  }

}
