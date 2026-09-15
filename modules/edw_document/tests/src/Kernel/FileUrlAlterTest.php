<?php

namespace Drupal\Tests\edw_document\Kernel;

use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_document\Traits\DocumentTestTrait;

/**
 * Tests that managed file URLs are rewritten onto the serve_file route.
 *
 * The alter hook fires for every managed file URL on the site, so it decides
 * what every document link, every redirect and every download button points
 * at. Both of the recent "page not found" tickets came from here.
 *
 * @group edw_document
 */
class FileUrlAlterTest extends KernelTestBase {

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
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    // The hook builds a Url from the serve_file route, so the router has to
    // know about it.
    \Drupal::service('router.builder')->rebuild();

    $this->fileUrlGenerator = \Drupal::service('file_url_generator');
  }

  /**
   * Tests that a relative URL points at the serve route and resolves.
   */
  public function testGenerateStringPointsAtServeRoute(): void {
    $file = $this->createDocumentFile('test.pdf');

    $url = $this->fileUrlGenerator->generateString($file->getFileUri());

    $this->assertStringStartsWith('/download/file/' . $file->uuid(), $url);
    $this->assertSame($file->uuid(), $this->matchServeRoute($url));
  }

  /**
   * Tests that the absolute URL really is absolute.
   *
   * This is the whole point of the `absolute => TRUE` option in the hook. With
   * a schemeless altered URI, core's doGenerateString() falls through to
   * generatePath(), which sees the leading slash and returns the value
   * verbatim — so generateAbsoluteString() used to return a *relative* string
   * despite its name, and every caller that needs a real absolute URL (the
   * single-file RedirectResponse, and window.open() in the download modal) got
   * a path instead.
   */
  public function testGenerateAbsoluteStringIsAbsolute(): void {
    $file = $this->createDocumentFile('test.pdf');

    $url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());

    $this->assertMatchesRegularExpression('#^https?://#', $url, 'generateAbsoluteString() must return an absolute URL.');
    $this->assertStringContainsString('/download/file/' . $file->uuid(), $url);
  }

  /**
   * Tests that the Url object form resolves back to the same file.
   *
   * The Url object form is what template_preprocess_file_link() uses, so this
   * is the path behind every rendered file link on the site. Round-tripping it
   * through the router is what catches double-encoding: a URL that has been
   * encoded twice no longer matches the route and the user gets a 404.
   *
   * @dataProvider providerFilenames
   */
  public function testGenerateResolvesBackToTheFile(string $filename): void {
    $file = $this->createDocumentFile($filename);

    $url = $this->fileUrlGenerator->generate($file->getFileUri())->toString();

    $this->assertSame($file->uuid(), $this->matchServeRoute($url), sprintf(
      'The URL generated for "%s" (%s) did not resolve back to the file.',
      $filename,
      $url
    ));
  }

  /**
   * Data provider for testGenerateResolvesBackToTheFile().
   *
   * @return array
   *   Test cases, each a filename that has to survive the round trip.
   */
  public function providerFilenames(): array {
    return [
      'plain' => ['report.pdf'],
      'spaces' => ['My Annual Report.pdf'],
      'plus sign' => ['report+annex.pdf'],
      'percent sign' => ['report 100%.pdf'],
      'hash' => ['report #12.pdf'],
      'ampersand' => ['budget & finance.pdf'],
      'non-ascii' => ['rapport-français.pdf'],
      'parentheses' => ['report (final).pdf'],
    ];
  }

  /**
   * Tests that the filename query parameter survives generation.
   */
  public function testFilenameQueryParameterIsPreserved(): void {
    $file = $this->createDocumentFile('My Annual Report.pdf');

    $url = $this->fileUrlGenerator->generateString($file->getFileUri());

    $query = [];
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $this->assertSame('My Annual Report.pdf', $query['filename'] ?? NULL);
  }

  /**
   * Tests that URIs which are not managed files are left alone.
   *
   * The hook runs for every file URL on the site, including shipped assets
   * such as theme images, so it has to be inert for anything it does not own.
   */
  public function testUnmanagedUrisAreNotRewritten(): void {
    $url = $this->fileUrlGenerator->generateString('public://never-managed.png');
    $this->assertStringNotContainsString('/download/file/', $url);

    $external = 'https://example.com/report.pdf';
    $this->assertSame($external, $this->fileUrlGenerator->generateAbsoluteString($external));
  }

  /**
   * Tests that generating the same URL twice does not rewrite it twice.
   */
  public function testRewritingIsIdempotent(): void {
    $file = $this->createDocumentFile('test.pdf');

    $first = $this->fileUrlGenerator->generateString($file->getFileUri());
    $second = $this->fileUrlGenerator->generateString($file->getFileUri());

    $this->assertSame($first, $second);
    $this->assertSame(1, substr_count($second, '/download/file/'));
  }

  /**
   * Records how many queries one file URL costs.
   *
   * Looking the file up by URI is an uncached loadByProperties() on every
   * single file URL the site generates, so a listing of 50 documents pays for
   * 50 extra entity queries. This test does not fail that design — it pins
   * cost so that adding a static cache has a measurable baseline, and so that
   * the cost cannot quietly grow.
   */
  public function testUrlGenerationCostPerFile(): void {
    $files = [];
    foreach (['a.pdf', 'b.pdf', 'c.pdf'] as $filename) {
      $files[] = $this->createDocumentFile($filename);
    }

    Database::startLog('file_url');
    foreach ($files as $file) {
      $this->fileUrlGenerator->generateString($file->getFileUri());
    }
    $queries = Database::getLog('file_url', 'default');

    $this->assertGreaterThanOrEqual(
      count($files),
      count($queries),
      'Each file URL costs at least one query; see getFileByUri().'
    );
  }

  /**
   * Resolves a generated URL back through the router.
   *
   * @param string $url
   *   A relative or absolute URL.
   *
   * @return string|null
   *   The uuid route parameter, or NULL if the URL does not match the route.
   */
  protected function matchServeRoute(string $url): ?string {
    $path = (string) parse_url($url, PHP_URL_PATH);

    try {
      $match = \Drupal::service('router.no_access_checks')->match($path);
    }
    catch (\Exception $e) {
      $this->fail(sprintf('"%s" did not match any route: %s', $path, $e->getMessage()));
    }

    if (($match['_route'] ?? NULL) !== 'edw_document.serve_file') {
      return NULL;
    }

    return $match['uuid'] ?? NULL;
  }

}
