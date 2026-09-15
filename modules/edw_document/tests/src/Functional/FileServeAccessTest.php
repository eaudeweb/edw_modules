<?php

namespace Drupal\Tests\edw_document\Functional;

use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Documents what access control the serve route does and does not apply.
 *
 * The route requires only the 'access content' permission and performs no
 * check against the file or the entity that references it. Sites built on this
 * module gate downloads elsewhere — in a theme preprocess, or by hiding the
 * link — but the route itself remains reachable by anyone who has the uuid,
 * and uuids appear in the markup of every document listing.
 *
 * These tests pin the current behaviour so it cannot change unnoticed. Each
 * one carries a note describing what it should assert instead, once the route
 * performs its own access checks.
 *
 * @group edw_document
 */
class FileServeAccessTest extends DocumentDownloadTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    NodeType::create(['type' => 'document', 'name' => 'Document'])->save();
    $this->createFilesField('node', 'document');
  }

  /**
   * Tests that the route needs the access content permission.
   *
   * This is the one check that is in place.
   */
  public function testRequiresAccessContentPermission(): void {
    $file = $this->createDocumentFile('report.pdf');

    // Take the permission away from anonymous users.
    user_role_revoke_permissions('anonymous', ['access content']);

    $this->drupalGet('/download/file/' . $file->uuid());
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that a file on an unpublished document is still served.
   *
   * The node is not viewable, and the download link is not rendered, but the
   * file behind it is delivered to anyone who asks for it directly. Embargoed
   * documents published ahead of a meeting are exactly this shape.
   *
   * @todo Once the route checks the referencing entity, this should assert a
   *   403 for users without 'bypass node access'.
   */
  public function testUnpublishedDocumentFilesAreStillServed(): void {
    $file = $this->createDocumentFile('embargoed.pdf', 'Not public yet.');

    $node = Node::create([
      'type' => 'document',
      'title' => 'Embargoed document',
      'status' => 0,
      'field_files' => $this->fileFieldValues([$file]),
    ]);
    $node->save();

    $this->drupalLogin($this->drupalCreateUser(['access content']));

    // The node itself is correctly protected.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->statusCodeEquals(403);

    // Its file is not.
    $this->drupalGet('/download/file/' . $file->uuid());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame('Not public yet.', $this->getSession()->getPage()->getContent());
  }

  /**
   * Tests that a private scheme file is served without hook_file_download.
   *
   * Core protects private:// files by asking every module, through
   * hook_file_download(), whether the current user may have them. This
   * controller reads the file directly, so that conversation never happens and
   * the private scheme provides no protection for documents.
   *
   * @todo Once the route delegates to the file download machinery, this should
   *   assert a 403 for a user with no claim to the file.
   */
  public function testPrivateFilesAreServedWithoutTheDownloadHook(): void {
    $uri = 'private://confidential.pdf';
    file_put_contents($uri, 'Confidential payload.');

    $file = File::create([
      'uri' => $uri,
      'filename' => 'confidential.pdf',
      'status' => FileInterface::STATUS_PERMANENT,
    ]);
    $file->save();

    $this->drupalLogin($this->drupalCreateUser(['access content']));

    // Core's own private file route denies this user.
    $this->drupalGet('/system/files/confidential.pdf');
    $this->assertSession()->statusCodeEquals(403);

    // The document serve route does not.
    $this->drupalGet('/download/file/' . $file->uuid());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame('Confidential payload.', $this->getSession()->getPage()->getContent());
  }

  /**
   * Tests that uuids are all that stands between a visitor and a document.
   *
   * Recorded deliberately: the uuid is the only secret, and it is printed in
   * the href of every document link on the site.
   */
  public function testAnyValidUuidIsEnough(): void {
    $file = $this->createDocumentFile('report.pdf', 'The document payload.');

    // No account at all.
    $this->drupalGet('/download/file/' . $file->uuid());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame('The document payload.', $this->getSession()->getPage()->getContent());
  }

}
