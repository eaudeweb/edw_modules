<?php

namespace Drupal\Tests\edw_document\Functional;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the route that actually delivers document bytes to the browser.
 *
 * Every document link on the site ends up here, so the failures this covers
 * are the ones users see: a download that 404s, a download that arrives with
 * the wrong name, and a download that serves stale bytes from cache.
 *
 * Note that edw_document declares hard dependencies (entity_browser,
 * media_library, search_api_solr, views_bulk_operations, edw_utilities), so
 * these tests require a codebase where those modules are present.
 *
 * @group edw_document
 */
class FileServeTest extends DocumentDownloadTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dynamic_page_cache',
    'language',
  ];

  /**
   * Tests that an anonymous visitor gets the file.
   */
  public function testServesTheFile(): void {
    $file = $this->createDocumentFile('report.pdf', 'The document payload.');

    $this->drupalGet('/download/file/' . $file->uuid());

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame('The document payload.', $this->getSession()->getPage()->getContent());

    $headers = $this->getSession()->getResponseHeaders();
    $this->assertStringContainsString('application/pdf', $this->headerValue($headers, 'Content-Type'));
    $this->assertStringContainsString('inline', $this->headerValue($headers, 'Content-Disposition'));
    $this->assertStringContainsString('report.pdf', $this->headerValue($headers, 'Content-Disposition'));
  }

  /**
   * Tests that an unknown uuid is a not found, not an error.
   */
  public function testUnknownUuidIsNotFound(): void {
    $this->drupalGet('/download/file/00000000-0000-0000-0000-000000000000');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests that a file whose bytes are gone is a not found.
   *
   * File entities outlive their payloads — after a botched migration or a
   * files directory that was not synced, the entity is still there. Serving it
   * would emit an empty 200 the browser saves as a corrupt document.
   */
  public function testMissingBytesAreNotFound(): void {
    $file = $this->createDocumentFile('gone.pdf');
    $this->deleteFileBytes($file);

    $this->drupalGet('/download/file/' . $file->uuid());
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests that unusual uuid segments fail cleanly.
   *
   * The route accepts anything (`uuid: '.+'`) so that already-encoded paths
   * still reach the controller. Everything that gets through has to end in a
   * 404 rather than an uncaught exception.
   */
  public function testMalformedUuidsFailCleanly(): void {
    foreach (['not-a-uuid', 'nested/path/segment', 'with%20space', 'with+plus'] as $segment) {
      $this->drupalGet('/download/file/' . $segment);
      $this->assertSession()->statusCodeEquals(404);
    }
  }

  /**
   * Tests that the filename query parameter names the download.
   *
   * The hook puts the real filename in the query because the path only carries
   * a uuid — without it every download would be saved as the uuid.
   */
  public function testFilenameQueryParameterNamesTheDownload(): void {
    $file = $this->createDocumentFile('internal-name.pdf');

    $this->drupalGet('/download/file/' . $file->uuid(), [
      'query' => ['filename' => 'Annual Report 2026.pdf'],
    ]);

    $this->assertSession()->statusCodeEquals(200);
    $disposition = $this->headerValue($this->getSession()->getResponseHeaders(), 'Content-Disposition');
    $this->assertStringContainsString('Annual Report 2026.pdf', rawurldecode($disposition));
  }

  /**
   * Tests filenames that have historically broken the download URL.
   *
   * These are generated the way the site generates them — through the file URL
   * generator, so the hook builds the URL — and then requested verbatim. A
   * double-encoded or under-encoded path shows up here as a 404.
   */
  public function testFilenamesThatNeedEncoding(): void {
    $filenames = [
      'My Annual Report.pdf',
      'report+annex.pdf',
      'report 100%.pdf',
      'report #12.pdf',
      'budget & finance.pdf',
      'rapport-français.pdf',
      'report (final).pdf',
    ];

    foreach ($filenames as $filename) {
      $file = $this->createDocumentFile($filename, 'Payload for ' . $filename);

      $url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
      $this->drupalGet($url);

      $this->assertSession()->statusCodeEquals(200);
      $this->assertSame(
        'Payload for ' . $filename,
        $this->getSession()->getPage()->getContent(),
        sprintf('"%s" was not served correctly from %s', $filename, $url)
      );
    }
  }

  /**
   * Tests that a language-prefixed URL still resolves.
   *
   * The controller re-resolves the path through the inbound path processors
   * precisely so that prefixes and aliases are stripped before the uuid is
   * read. Documents are downloaded from every language of the site.
   */
  public function testLanguagePrefixedPath(): void {
    ConfigurableLanguage::createFromLangcode('fr')->save();
    drupal_flush_all_caches();

    $file = $this->createDocumentFile('report.pdf', 'The document payload.');

    // English is the default language and carries no prefix; French is the
    // case that exercises the path processor.
    $this->drupalGet('/fr/download/file/' . $file->uuid());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame('The document payload.', $this->getSession()->getPage()->getContent());

    $this->drupalGet('/download/file/' . $file->uuid());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame('The document payload.', $this->getSession()->getPage()->getContent());
  }

  /**
   * Tests that the response is cached and still correct when served from cache.
   *
   * The serve route was made cacheable to stop every document request booting
   * a full render. Caching means the response object is serialised, and
   * BinaryFileResponse holds a file handle that cannot be serialised — so a
   * cache hit that returns the wrong bytes, or no bytes, is the failure mode.
   */
  public function testResponseIsCachedAndStillCorrect(): void {
    $file = $this->createDocumentFile('report.pdf', 'The document payload.');
    $path = '/download/file/' . $file->uuid();

    // Dynamic page cache only applies to authenticated requests; anonymous
    // ones are handled by the page cache.
    $this->drupalLogin($this->drupalCreateUser(['access content']));

    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame('MISS', $this->headerValue($this->getSession()->getResponseHeaders(), 'X-Drupal-Dynamic-Cache'));

    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame('HIT', $this->headerValue($this->getSession()->getResponseHeaders(), 'X-Drupal-Dynamic-Cache'));
    $this->assertSame(
      'The document payload.',
      $this->getSession()->getPage()->getContent(),
      'The cached response did not serve the file.'
    );
  }

  /**
   * Tests that replacing a file invalidates the cached response.
   */
  public function testReplacingFileInvalidatesCache(): void {
    $file = $this->createDocumentFile('report.pdf', 'First version.');
    $path = '/download/file/' . $file->uuid();

    $this->drupalLogin($this->drupalCreateUser(['access content']));
    $this->drupalGet($path);
    $this->assertSame('First version.', $this->getSession()->getPage()->getContent());

    file_put_contents($file->getFileUri(), 'Second version.');
    $file->save();

    $this->drupalGet($path);
    $this->assertSame(
      'Second version.',
      $this->getSession()->getPage()->getContent(),
      'The cache was not invalidated when the file changed.'
    );
  }

}
