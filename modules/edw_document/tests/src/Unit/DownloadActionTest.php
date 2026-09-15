<?php

namespace Drupal\Tests\edw_document\Unit;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\edw_document\Plugin\Action\DownloadAction;
use Drupal\edw_document\Plugin\Action\MediaDownloadAction;

/**
 * Tests the "Download Documents" bulk operation.
 *
 * This action is a placeholder. Its only job is to appear in a listing's
 * operations select so that hook_form_alter() can hang an AJAX callback off
 * the submit button and open the download modal instead of running it. So the
 * two things worth pinning are that it never refuses to be offered, and that
 * executing it — which happens if the AJAX callback is ever bypassed, e.g.
 * with JavaScript off — does nothing rather than something surprising.
 *
 * @coversDefaultClass \Drupal\edw_document\Plugin\Action\DownloadAction
 *
 * @group edw_document
 */
class DownloadActionTest extends UnitTestCase {

  /**
   * The action under test.
   *
   * @var \Drupal\edw_document\Plugin\Action\DownloadAction
   */
  protected $action;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->action = new DownloadAction(
      [],
      'node_download_documents_action',
      [],
      $this->createMock(EntityTypeManagerInterface::class)
    );
  }

  /**
   * Tests that the operation is offered to everyone who can see the listing.
   *
   * Access to the files themselves is decided later, when the modal builds
   * the download; refusing here would hide the button entirely.
   *
   * @covers ::access
   */
  public function testAccessIsAlwaysGranted(): void {
    $entity = $this->createMock(EntityInterface::class);
    $account = $this->createMock(AccountInterface::class);

    $this->assertTrue($this->action->access($entity));
    $this->assertTrue($this->action->access($entity, $account));
  }

  /**
   * Tests the access result object form.
   *
   * Views Bulk Operations asks for the object form, and a bare TRUE there
   * would be treated as an access result and fail.
   *
   * @covers ::access
   */
  public function testAccessCanBeReturnedAsAnObject(): void {
    $access = $this->action->access($this->createMock(EntityInterface::class), NULL, TRUE);

    $this->assertInstanceOf(AccessResultInterface::class, $access);
    $this->assertTrue($access->isAllowed());
  }

  /**
   * Tests that executing the action is a no-op.
   *
   * @covers ::execute
   * @covers ::executeMultiple
   */
  public function testExecutingTheActionDoesNothing(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->never())->method('save');

    $this->assertNull($this->action->execute($entity));
    $this->assertNull($this->action->execute());
    $this->assertNull($this->action->executeMultiple([$entity, $entity]));
  }

  /**
   * Tests that the media variant behaves identically.
   *
   * Documents are media on most sites and nodes on others, so the two
   * actions exist only to declare different `type` targets.
   *
   * @covers \Drupal\edw_document\Plugin\Action\MediaDownloadAction
   */
  public function testTheMediaVariantBehavesTheSame(): void {
    $action = new MediaDownloadAction(
      [],
      'media_download_documents_action',
      [],
      $this->createMock(EntityTypeManagerInterface::class)
    );

    $entity = $this->createMock(EntityInterface::class);

    $this->assertTrue($action->access($entity));
    $this->assertNull($action->execute($entity));
  }

}
