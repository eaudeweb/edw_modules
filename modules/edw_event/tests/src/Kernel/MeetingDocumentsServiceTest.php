<?php

namespace Drupal\Tests\edw_event\Kernel;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_event\Traits\EventTestTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\edw_event\Services\MeetingDocumentsService;

/**
 * Tests the document phases and the "add document" buttons.
 *
 * The phases are not configuration of this module: they are the allowed
 * values of the document media type's phase field, so a site defines its own
 * workflow there and edw_event follows it. The routes, the tabs and these
 * buttons all read the same list, which is why it lives in one service.
 *
 * @coversDefaultClass \Drupal\edw_event\Services\MeetingDocumentsService
 *
 * @group edw_event
 */
class MeetingDocumentsServiceTest extends KernelTestBase {

  use EventTestTrait;
  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'options',
    'filter',
    'node',
    'taxonomy',
    'file',
    'image',
    'media',
    'entity_clone',
    'edw_event',
  ];

  /**
   * The service under test.
   *
   * @var \Drupal\edw_event\Services\MeetingDocumentsService
   */
  protected $documentsService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'system', 'image', 'file', 'media']);

    $this->createMeetingContentTypes();
    $this->createAgendaVocabulary();
    $this->createMediaType('file', ['id' => 'document', 'label' => 'Document']);

    $this->documentsService = \Drupal::service('edw_event.meeting.documents.service');
  }

  /**
   * Tests that the phases are the phase field's allowed values.
   *
   * @covers ::getDocumentPhases
   */
  public function testThePhasesAreThePhaseFieldsAllowedValues(): void {
    $this->createPhaseField([
      'pre_session' => 'Pre-session',
      'in_session' => 'In-session',
      'post_session' => 'Post-session',
    ]);

    $this->assertSame([
      'pre_session' => 'Pre-session',
      'in_session' => 'In-session',
      'post_session' => 'Post-session',
    ], $this->documentsService->getDocumentPhases());
  }

  /**
   * Tests that a site may define its own phases.
   *
   * @covers ::getDocumentPhases
   */
  public function testSiteMayDefineItsOwnPhases(): void {
    $this->createPhaseField([
      'preparation' => 'Preparation',
      'meeting' => 'Meeting',
    ]);

    $this->assertSame(['preparation', 'meeting'], array_keys($this->documentsService->getDocumentPhases()));
  }

  /**
   * Tests that a document type with no phase field yields no phases.
   *
   * The field is optional, and the routes and tabs both branch on an empty
   * list rather than assuming three phases exist.
   *
   * @covers ::getDocumentPhases
   */
  public function testDocumentTypeWithNoPhaseFieldYieldsNoPhases(): void {
    $this->assertSame([], $this->documentsService->getDocumentPhases());
  }

  /**
   * Tests that a site without the media module still gets a phase list.
   *
   * The phases are read straight off the media document type, but edw_event
   * does not depend on the media module — a site can run it over nodes alone.
   * The lookup is made unconditionally, so on such a site it raises "The
   * media entity type does not exist" instead of returning an empty list.
   *
   * That matters more than it looks: the route callback calls this, so the
   * failure lands in the router rebuild that runs whenever any module is
   * installed, not on a page an editor could avoid.
   *
   * @covers ::getDocumentPhases
   */
  public function testSiteWithoutTheMediaModuleStillGetsPhaseList(): void {
    $documentsService = new MeetingDocumentsService(
      $this->entityFieldManagerWithoutMedia()
    );

    $this->assertSame([], $documentsService->getDocumentPhases());
  }

  /**
   * Tests that the add-document link opens the media form for the meeting.
   *
   * The link carries the meeting, the agenda and the phase so the media form
   * can pre-fill and lock them — an editor adding a document from an agenda
   * heading should not have to re-pick where it belongs.
   *
   * @covers ::addDocumentModal
   */
  public function testTheAddDocumentLinkCarriesTheMeetingAgendaAndPhase(): void {
    $meeting = $this->createMeeting();
    $agenda = $this->createAgenda($meeting, 'Opening of the meeting');

    $link = $this->documentsService->addDocumentModal($agenda, 'in_session');

    $this->assertSame('link', $link['#type']);
    $this->assertSame('entity.media.add_form', $link['#url']->getRouteName());
    $this->assertSame([
      'media_type' => 'document',
      'nid' => $meeting->id(),
      'field_agenda' => $agenda->id(),
      'field_document_phase' => 'in_session',
    ], $link['#url']->getRouteParameters());
  }

  /**
   * Tests that the add-document link opens in a dialog.
   *
   * Adding a document is done without leaving the list being worked through,
   * so the link is an ajax dialog rather than a page load.
   *
   * @covers ::addDocumentModal
   */
  public function testTheAddDocumentLinkOpensInDialog(): void {
    $meeting = $this->createMeeting();
    $agenda = $this->createAgenda($meeting, 'Opening of the meeting');

    $attributes = $this->documentsService->addDocumentModal($agenda, 'in_session')['#attributes'];

    $this->assertContains('use-ajax', $attributes['class']);
    $this->assertSame('dialog', $attributes['data-dialog-type']);
    $this->assertSame(
      ['width' => '60%', 'dialogClass' => 'views-ui-dialog js-views-ui-dialog'],
      Json::decode($attributes['data-dialog-options'])
    );
  }

  /**
   * Tests that an arbitrary button can be turned into the same dialog.
   *
   * The manage-documents screens have several such buttons; they all have to
   * look and behave the same.
   *
   * @covers ::addButtonModal
   */
  public function testAnyButtonCanBeTurnedIntoTheSameDialog(): void {
    $url = Url::fromRoute('edw_event.documents.settings', ['node' => 1]);

    $link = $this->documentsService->addButtonModal($url, 'Reorder agenda');

    $this->assertSame('link', $link['#type']);
    $this->assertSame('Reorder agenda', (string) $link['#title']);
    $this->assertSame($url, $link['#url']);
    $this->assertContains('use-ajax', $link['#attributes']['class']);
    $this->assertSame('dialog', $link['#attributes']['data-dialog-type']);
  }

  /**
   * Returns an entity field manager for a site with no media entity type.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The entity field manager.
   */
  protected function entityFieldManagerWithoutMedia() {
    $entityFieldManager = $this->createMock('Drupal\Core\Entity\EntityFieldManagerInterface');
    $entityFieldManager->method('getFieldDefinitions')
      ->willThrowException(new PluginNotFoundException('media', 'The "media" entity type does not exist.'));

    return $entityFieldManager;
  }

  /**
   * Adds the phase field to the document media type.
   *
   * @param array $allowedValues
   *   Phase id to title.
   */
  protected function createPhaseField(array $allowedValues): void {
    $this->createField('media', 'document', 'field_document_phase', 'list_string', [
      'allowed_values' => $allowedValues,
    ]);
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  }

}
