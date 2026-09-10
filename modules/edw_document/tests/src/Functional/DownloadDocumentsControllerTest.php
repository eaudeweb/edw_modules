<?php

namespace Drupal\Tests\edw_document\Functional;

use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\media\Entity\Media;

/**
 * Tests the endpoint the download modal submits to.
 *
 * It has three outcomes depending on how many files matched the selection:
 * nothing, a redirect straight to the single file, or a zip. Each one is a
 * different response class, so each one can break on its own.
 *
 * @group edw_document
 */
class DownloadDocumentsControllerTest extends DocumentDownloadTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createMediaType('file', ['id' => 'document', 'label' => 'Document']);
    $this->createFilesField('media', 'document');

    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'view media',
    ]));
  }

  /**
   * Tests that a selection matching nothing reports no content.
   *
   * The modal lets users combine a format and a language that no file
   * satisfies. That is not an error — there is simply nothing to send.
   */
  public function testNoMatchingFilesReturnsNoContent(): void {
    $media = $this->createDocumentMedia([$this->createDocumentFile('report.pdf')]);

    $this->drupalGet('/download/documents', [
      'query' => $this->downloadQuery($media, 'spreadsheet', 'en'),
    ]);

    $this->assertSession()->statusCodeEquals(204);
  }

  /**
   * Tests that a single match redirects straight to the file.
   *
   * Zipping one file would make the user unzip a single document, so the
   * controller redirects instead — which is why the URL it redirects to has to
   * be one a browser can follow.
   */
  public function testSingleMatchRedirectsToTheFile(): void {
    $file = $this->createDocumentFile('report.pdf', 'The document payload.');
    $media = $this->createDocumentMedia([$file]);

    // Mink follows redirects on its own, so go straight to the HTTP client to
    // see the redirect itself.
    $url = $this->buildUrl('/download/documents', [
      'query' => $this->downloadQuery($media, 'pdf', 'en'),
    ]);
    $response = $this->getHttpClient()->get($url, ['allow_redirects' => FALSE]);

    $this->assertSame(302, $response->getStatusCode());
    $location = $response->getHeaderLine('Location');
    $this->assertStringContainsString('/download/file/' . $file->uuid(), $location);

    // Following it has to produce the document, not another redirect.
    $followed = $this->getHttpClient()->get($location);
    $this->assertSame(200, $followed->getStatusCode());
    $this->assertSame('The document payload.', (string) $followed->getBody());
  }

  /**
   * Tests that several matches are delivered as one archive.
   */
  public function testSeveralMatchesReturnAnArchive(): void {
    $media = $this->createDocumentMedia([
      $this->createDocumentFile('first.pdf', 'First document.'),
      $this->createDocumentFile('second.pdf', 'Second document.'),
    ]);

    $this->drupalGet('/download/documents', [
      'query' => $this->downloadQuery($media, 'pdf', 'en'),
    ]);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame(
      'application/zip',
      $this->headerValue($this->getSession()->getResponseHeaders(), 'Content-Type')
    );
    $this->assertStringContainsString(
      'download.zip',
      $this->headerValue($this->getSession()->getResponseHeaders(), 'Content-Disposition')
    );
  }

  /**
   * Tests that an incomplete request is rejected rather than fatal.
   *
   * The endpoint reads ids, format and language straight out of the query
   * string with no validation, so a request missing any of them reaches
   * getFilteredFiles() with a missing array argument. A crawler or a stale
   * bookmark should get a client error, not a server error and a logged
   * exception.
   *
   * @todo This fails until DocumentController::archiveFiles() validates its
   *   query parameters.
   */
  public function testIncompleteRequestIsRejected(): void {
    $media = $this->createDocumentMedia([$this->createDocumentFile('report.pdf')]);

    $incompleteQueries = [
      'no ids' => ['format' => 'pdf', 'language' => 'en'],
      'no format' => ['ids' => $media->id(), 'language' => 'en'],
      'no language' => ['ids' => $media->id(), 'format' => 'pdf'],
      'nothing at all' => [],
    ];

    foreach ($incompleteQueries as $case => $query) {
      $this->drupalGet('/download/documents', ['query' => $query]);

      $status = $this->getSession()->getStatusCode();
      $this->assertLessThan(500, $status, sprintf(
        'A request with %s produced a server error (%d) instead of a client error.',
        $case,
        $status
      ));
    }
  }

  /**
   * Tests that the endpoint is closed to users who cannot view media.
   *
   * Worth knowing when reading this: the Media module grants 'view media' to
   * the anonymous and authenticated roles on install, so in a default site
   * this route's permission keeps nobody out. The permission has to be revoked
   * explicitly before the check is observable at all.
   */
  public function testRequiresViewMediaPermission(): void {
    $media = $this->createDocumentMedia([$this->createDocumentFile('report.pdf')]);

    user_role_revoke_permissions('anonymous', ['view media']);
    user_role_revoke_permissions('authenticated', ['view media']);

    $this->drupalLogin($this->drupalCreateUser(['access content']));
    $this->drupalGet('/download/documents', [
      'query' => $this->downloadQuery($media, 'pdf', 'en'),
    ]);

    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Builds the query string the download modal submits.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The selected document.
   * @param string $format
   *   The selected format.
   * @param string $language
   *   The selected langcode.
   *
   * @return array
   *   The query parameters.
   */
  protected function downloadQuery($media, string $format, string $language): array {
    return [
      'ids' => $media->id(),
      'format' => $format,
      'language' => $language,
      'field_name' => 'field_files',
    ];
  }

  /**
   * Creates a document media entity carrying the given files.
   *
   * @param \Drupal\file\FileInterface[] $files
   *   The files to attach.
   *
   * @return \Drupal\media\MediaInterface
   *   The saved media entity.
   */
  protected function createDocumentMedia(array $files) {
    $media = Media::create([
      'bundle' => 'document',
      'name' => 'Test document',
      'status' => 1,
      'field_files' => $this->fileFieldValues($files),
    ]);
    $media->save();

    return $media;
  }

}
