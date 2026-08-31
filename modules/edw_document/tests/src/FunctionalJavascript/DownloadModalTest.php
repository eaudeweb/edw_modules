<?php

namespace Drupal\Tests\edw_document\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\edw_document\Traits\DocumentTestTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests the download modal end to end in a browser.
 *
 * Everything the modal decides is covered more cheaply elsewhere; what only a
 * browser can show is that the pieces are wired together — the button opens
 * the dialog, the dialog submits over AJAX, and the response is a download
 * command rather than a validation error.
 *
 * @group edw_document
 */
class DownloadModalTest extends WebDriverTestBase {

  use DocumentTestTrait;

  /**
   * {@inheritdoc}
   *
   * The config/install directory ships a node action, a media action and an
   * ultimate_cron job, but edw_document.info.yml declares none of those three
   * modules — so installing the module without them fails outright.
   */
  protected static $modules = [
    'edw_document',
    'node',
    'media',
    'ultimate_cron',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

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
   * Tests opening the modal and submitting a selection.
   *
   * A successful submission closes the dialog and hands the browser a download
   * command; the browser then opens that URL in a new tab, which WebDriver
   * cannot observe. The dialog closing without a validation message is what
   * distinguishes a completed download from a failed one.
   */
  public function testSelectingFormatDownloads(): void {
    $node = Node::create([
      'type' => 'document',
      'title' => 'Test document',
      'status' => 1,
      'field_files' => $this->fileFieldValues([
        $this->createDocumentFile('report.pdf', 'PDF payload.'),
        $this->createDocumentFile('data.xlsx', 'Spreadsheet payload.'),
      ]),
    ]);
    $node->save();

    $this->drupalGet($node->toUrl());

    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    $page->find('css', 'a.download-button.use-ajax')->click();
    $assert->waitForElementVisible('css', '.download-form');

    $page->find('css', '.download-from-formats input[type="checkbox"]')->check();
    $page->find('css', '.download-from-languages input[type="checkbox"]')->check();

    $page->find('css', '.download-form .form-actions input[type="submit"]')->click();

    $this->assertTrue(
      $assert->waitForElementRemoved('css', '.download-form'),
      'The modal stayed open, which means the submission did not produce a download.'
    );
    $assert->pageTextNotContains('Select at least one format');
    $assert->pageTextNotContains('Select at least one language');
  }

  /**
   * Tests that submitting with nothing selected is rejected in the modal.
   */
  public function testSubmittingEmptySelectionShowsError(): void {
    $node = Node::create([
      'type' => 'document',
      'title' => 'Test document',
      'status' => 1,
      'field_files' => $this->fileFieldValues([
        $this->createDocumentFile('report.pdf'),
        $this->createDocumentFile('data.xlsx'),
      ]),
    ]);
    $node->save();

    $this->drupalGet($node->toUrl());

    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    $page->find('css', 'a.download-button.use-ajax')->click();
    $assert->waitForElementVisible('css', '.download-form');

    $page->find('css', '.download-form .form-actions input[type="submit"]')->click();

    $this->assertNotNull(
      $assert->waitForElement('css', '#download-documents-header .messages--error'),
      'Submitting an empty selection should report the error inside the modal.'
    );
  }

}
