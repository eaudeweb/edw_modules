<?php

namespace Drupal\Tests\edw_document\Functional;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests the download button rendered on a document.
 *
 * The formatter has two branches: when a document has exactly one format and
 * one language it renders a direct link to the file, otherwise it renders a
 * button that opens the format/language modal. The direct-link branch is the
 * one coupled to how the file URL hook builds URLs.
 *
 * @group edw_document
 */
class DownloadFileFormatterTest extends DocumentDownloadTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'dblog',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    NodeType::create(['type' => 'document', 'name' => 'Document'])->save();
    $this->createFilesField('node', 'document');

    \Drupal::service('entity_display.repository')
      ->getViewDisplay('node', 'document', 'default')
      ->setComponent('field_files', ['type' => 'file_download_formatter'])
      ->save();

    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'view media',
    ]));
  }

  /**
   * Tests the direct download link rendered for a single file.
   *
   * This is where the relative/absolute shape of the generated file URL
   * matters: the formatter feeds it to Url::fromUserInput(), which rejects
   * anything that is not a relative reference. A URL that is correct for the
   * redirect and modal paths can still be rejected here.
   */
  public function testSingleFileRendersDirectDownloadLink(): void {
    $file = $this->createDocumentFile('report.pdf', 'The document payload.');
    $node = $this->createDocumentNode([$file]);

    $this->drupalGet($node->toUrl());
    $this->assertSession()->statusCodeEquals(200);

    $link = $this->assertSession()->elementExists('css', 'a.download-button');
    $this->assertStringContainsString('/download/file/' . $file->uuid(), $link->getAttribute('href'));
    $this->assertSame('_blank', $link->getAttribute('target'));
  }

  /**
   * Tests that following the rendered link downloads the file.
   *
   * Rendering a link is not the same as it working — this walks the whole way
   * from the node page to the bytes.
   */
  public function testTheRenderedLinkDownloadsTheFile(): void {
    $file = $this->createDocumentFile('report.pdf', 'The document payload.');
    $node = $this->createDocumentNode([$file]);

    $this->drupalGet($node->toUrl());
    $this->clickLink('Download');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame('The document payload.', $this->getSession()->getPage()->getContent());
  }

  /**
   * Tests that several formats render the modal button instead.
   */
  public function testSeveralFormatsRenderTheModalButton(): void {
    $node = $this->createDocumentNode([
      $this->createDocumentFile('report.pdf'),
      $this->createDocumentFile('data.xlsx'),
    ]);

    $this->drupalGet($node->toUrl());

    $link = $this->assertSession()->elementExists('css', 'a.download-button.use-ajax');
    $this->assertStringContainsString(
      '/document-download/modal/node/' . $node->id() . '/field_files',
      $link->getAttribute('href')
    );
  }

  /**
   * Tests that a document with no usable files renders nothing and logs.
   *
   * The formatter reaches this state when the field references files whose
   * bytes are gone. It must not fail the page render, and it must leave a
   * trace an administrator can find.
   */
  public function testMissingFilesRenderNothingAndAreLogged(): void {
    $file = $this->createDocumentFile('report.pdf');
    $node = $this->createDocumentNode([$file]);
    $this->deleteFileBytes($file);

    $this->drupalGet($node->toUrl());

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementNotExists('css', 'a.download-button');

    $logged = \Drupal::database()->select('watchdog', 'w')
      ->fields('w', ['message'])
      ->condition('type', 'edw_document')
      ->execute()
      ->fetchCol();
    $this->assertNotEmpty($logged, 'A document with no usable files should be logged on the edw_document channel.');
  }

  /**
   * Tests that the link honours view access on the document.
   */
  public function testUnpublishedDocumentsRenderNoLink(): void {
    $file = $this->createDocumentFile('report.pdf');
    $node = $this->createDocumentNode([$file]);
    $node->setUnpublished()->save();

    $this->drupalLogout();
    $this->drupalGet($node->toUrl());

    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Creates a published document node carrying the given files.
   *
   * @param \Drupal\file\FileInterface[] $files
   *   The files to attach.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved node.
   */
  protected function createDocumentNode(array $files) {
    $node = Node::create([
      'type' => 'document',
      'title' => 'Test document',
      'status' => 1,
      'field_files' => $this->fileFieldValues($files),
    ]);
    $node->save();

    return $node;
  }

}
