<?php

namespace Drupal\Tests\edw_document\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Tests decoding the checkbox keys of a documents bulk form.
 *
 * When someone ticks rows in a documents listing and hits "Download", the
 * AJAX callback only has the checkbox values to work from. Those are opaque
 * base64/JSON keys, and they come in two shapes depending on which bulk form
 * the listing uses — Views Bulk Operations (the Search API listings) or core's
 * node bulk form. This service turns either back into an entity.
 *
 * @coversDefaultClass \Drupal\edw_document\Services\DocumentsBulkManager
 *
 * @group edw_document
 */
class DocumentsBulkManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'node',
    'language',
    'edw_document',
  ];

  /**
   * The bulk manager under test.
   *
   * @var \Drupal\edw_document\Services\DocumentsBulkManager
   */
  protected $bulkManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('node', ['node_access']);

    ConfigurableLanguage::createFromLangcode('fr')->save();

    NodeType::create(['type' => 'document', 'name' => 'Document'])->save();

    $this->bulkManager = \Drupal::service('edw_document.document.bulk_manager');
  }

  /**
   * Tests decoding a Views Bulk Operations checkbox key.
   *
   * @covers ::loadEntityFromBulkFormKey
   */
  public function testLoadsTheEntityNamedInBulkOperationsKey(): void {
    $node = $this->createDocument('Annual report');

    $entity = $this->bulkManager->loadEntityFromBulkFormKey(
      $this->bulkOperationsKey('en', 'node', $node->id())
    );

    $this->assertSame($node->id(), $entity->id());
    $this->assertSame('Annual report', $entity->label());
  }

  /**
   * Tests that the key's entity type is honoured, not assumed to be a node.
   *
   * Search API listings can mix datasources in one view, which is the whole
   * reason the entity type is carried in the key rather than taken from the
   * view. Document listings are usually media, not nodes.
   *
   * @covers ::loadEntityFromBulkFormKey
   */
  public function testTheKeyDecidesWhichEntityTypeIsLoaded(): void {
    $user = User::create(['name' => 'editor']);
    $user->save();

    $entity = $this->bulkManager->loadEntityFromBulkFormKey(
      $this->bulkOperationsKey('en', 'user', $user->id())
    );

    $this->assertSame('user', $entity->getEntityTypeId());
    $this->assertSame($user->id(), $entity->id());
  }

  /**
   * Tests that the entity comes back in the language the key names.
   *
   * The row the user ticked was a specific translation, and the files hanging
   * off a French document are not the ones on its English original.
   *
   * @covers ::loadEntityFromBulkFormKey
   */
  public function testTheEntityComesBackInTheKeysLanguage(): void {
    $node = $this->createDocument('Annual report');
    $node->addTranslation('fr', ['title' => 'Rapport annuel'] + $node->toArray())->save();

    $french = $this->bulkManager->loadEntityFromBulkFormKey(
      $this->bulkOperationsKey('fr', 'node', $node->id())
    );
    $english = $this->bulkManager->loadEntityFromBulkFormKey(
      $this->bulkOperationsKey('en', 'node', $node->id())
    );

    $this->assertSame('fr', $french->language()->getId());
    $this->assertSame('Rapport annuel', $french->label());
    $this->assertSame('en', $english->language()->getId());
    $this->assertSame('Annual report', $english->label());
  }

  /**
   * Tests decoding a core node bulk form key.
   *
   * This shape has no entity type in it — core's bulk form takes that from
   * the view — so the manager has to supply 'node' itself.
   *
   * @covers ::loadEntityFromBulkFormKey
   */
  public function testLoadsTheNodeNamedInNodeBulkFormKey(): void {
    $node = $this->createDocument('Annual report');

    $entity = $this->bulkManager->loadEntityFromBulkFormKey(
      $this->nodeBulkFormKey('en', $node->id()),
      FALSE
    );

    $this->assertSame('node', $entity->getEntityTypeId());
    $this->assertSame($node->id(), $entity->id());
  }

  /**
   * Tests that a node bulk form key may pin a specific revision.
   *
   * Views configured with revisions append the revision id, and downloading
   * from such a listing has to reach the files of that revision rather than
   * the current one.
   *
   * @covers ::loadEntityFromBulkFormKey
   */
  public function testNodeBulkFormKeyMayNameRevision(): void {
    $node = $this->createDocument('Annual report');
    $originalRevisionId = $node->getRevisionId();

    $node->setTitle('Annual report, revised');
    $node->setNewRevision(TRUE);
    $node->save();

    $entity = $this->bulkManager->loadEntityFromBulkFormKey(
      $this->nodeBulkFormKey('en', $node->id(), $originalRevisionId),
      FALSE
    );

    $this->assertSame($originalRevisionId, $entity->getRevisionId());
    $this->assertSame('Annual report', $entity->label());
  }

  /**
   * Tests that a key without a revision loads the current revision.
   *
   * @covers ::loadEntityFromBulkFormKey
   */
  public function testKeyWithoutRevisionLoadsTheCurrentOne(): void {
    $node = $this->createDocument('Annual report');

    $node->setTitle('Annual report, revised');
    $node->setNewRevision(TRUE);
    $node->save();

    $entity = $this->bulkManager->loadEntityFromBulkFormKey(
      $this->nodeBulkFormKey('en', $node->id()),
      FALSE
    );

    $this->assertSame('Annual report, revised', $entity->label());
  }

  /**
   * Builds a Views Bulk Operations checkbox key.
   *
   * The first part is the view row's base field value, which this service
   * discards — the entity type, language and id follow it.
   *
   * @param string $langcode
   *   The language of the ticked row.
   * @param string $entityTypeId
   *   The entity type of the ticked row.
   * @param string|int $id
   *   The entity id.
   *
   * @return string
   *   The encoded key.
   *
   * @see \Drupal\views_bulk_operations\Form\ViewsBulkOperationsFormTrait::calculateEntityBulkFormKey()
   */
  protected function bulkOperationsKey(string $langcode, string $entityTypeId, $id): string {
    return base64_encode(json_encode([$id, $langcode, $entityTypeId, $id]));
  }

  /**
   * Builds a core node bulk form checkbox key.
   *
   * @param string $langcode
   *   The language of the ticked row.
   * @param string|int $id
   *   The node id.
   * @param string|int|null $revisionId
   *   The revision id, when the view is revision aware.
   *
   * @return string
   *   The encoded key.
   *
   * @see \Drupal\views\Plugin\views\field\BulkForm::calculateEntityBulkFormKey()
   */
  protected function nodeBulkFormKey(string $langcode, $id, $revisionId = NULL): string {
    $parts = [$langcode, $id];
    if ($revisionId !== NULL) {
      $parts[] = $revisionId;
    }

    return base64_encode(json_encode($parts));
  }

  /**
   * Creates a document node.
   *
   * @param string $title
   *   The node title.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved node.
   */
  protected function createDocument(string $title) {
    $node = Node::create([
      'type' => 'document',
      'title' => $title,
      'langcode' => 'en',
    ]);
    $node->save();

    return $node;
  }

}
