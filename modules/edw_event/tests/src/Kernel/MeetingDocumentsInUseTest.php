<?php

namespace Drupal\Tests\edw_event\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_event\Traits\EventTestTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\media\Entity\Media;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;

/**
 * Tests which document types and agendas a meeting phase actually uses.
 *
 * The manage-documents screens group a phase's documents under headings, and
 * the headings are only the ones with something behind them: showing every
 * document type in the vocabulary would bury the handful a meeting actually
 * uses. Working that out means going the other way round — from the meeting's
 * published documents back to the terms they reference — which is why these
 * are hand-written joins rather than entity queries.
 *
 * @coversDefaultClass \Drupal\edw_event\Services\MeetingService
 *
 * @group edw_event
 */
class MeetingDocumentsInUseTest extends KernelTestBase {

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
    'draggableviews',
    'entity_clone',
    'edw_event',
    'edw_event_agenda',
  ];

  /**
   * The meeting service under test.
   *
   * @var \Drupal\edw_event\Services\MeetingService
   */
  protected $meetingService;

  /**
   * The agenda service under test.
   *
   * @var \Drupal\edw_event_agenda\Services\MeetingAgendaService
   */
  protected $agendaService;

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

    Vocabulary::create(['vid' => 'document_types', 'name' => 'Document types'])->save();

    $this->createMediaType('file', ['id' => 'document', 'label' => 'Document']);
    $this->createField('media', 'document', 'field_meetings', 'entity_reference', ['target_type' => 'node']);
    $this->createField('media', 'document', 'field_document_types', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->createField('media', 'document', 'field_agenda', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->createField('media', 'document', 'field_document_phase', 'list_string', [
      'allowed_values' => [
        'pre_session' => 'Pre-session',
        'in_session' => 'In-session',
        'post_session' => 'Post-session',
      ],
    ]);

    $this->meetingService = \Drupal::service('edw_event.meeting.service');
    $this->agendaService = \Drupal::service('edw_event_agenda.meeting.agenda.service');
  }

  /**
   * Tests that only the types the phase uses come back.
   *
   * @covers ::getDocumentTypesIdsInUse
   */
  public function testOnlyTheTypesThePhaseUsesComeBack(): void {
    $meeting = $this->createMeeting();
    $used = $this->createDocumentType('Working papers');
    $unused = $this->createDocumentType('Information papers');

    $this->createDocument($meeting, 'in_session', ['field_document_types' => $used->id()]);

    $ids = $this->meetingService->getDocumentTypesIdsInUse($meeting->id(), 'in_session');

    $this->assertSame([$used->id()], array_map('strval', $ids));
    $this->assertNotContains((string) $unused->id(), array_map('strval', $ids));
  }

  /**
   * Tests that a type used only in another phase is not offered.
   *
   * @covers ::getDocumentTypesIdsInUse
   */
  public function testTypeUsedInAnotherPhaseIsNotOffered(): void {
    $meeting = $this->createMeeting();
    $preSession = $this->createDocumentType('Agenda');
    $inSession = $this->createDocumentType('Working papers');

    $this->createDocument($meeting, 'pre_session', ['field_document_types' => $preSession->id()]);
    $this->createDocument($meeting, 'in_session', ['field_document_types' => $inSession->id()]);

    $this->assertSame(
      [$preSession->id()],
      array_map('strval', $this->meetingService->getDocumentTypesIdsInUse($meeting->id(), 'pre_session'))
    );
    $this->assertSame(
      [$inSession->id()],
      array_map('strval', $this->meetingService->getDocumentTypesIdsInUse($meeting->id(), 'in_session'))
    );
  }

  /**
   * Tests that another meeting's documents are not counted.
   *
   * @covers ::getDocumentTypesIdsInUse
   */
  public function testAnotherMeetingsDocumentsAreNotCounted(): void {
    $meeting = $this->createMeeting('Fifth meeting');
    $other = $this->createMeeting('Sixth meeting');
    $mine = $this->createDocumentType('Working papers');
    $theirs = $this->createDocumentType('Information papers');

    $this->createDocument($meeting, 'in_session', ['field_document_types' => $mine->id()]);
    $this->createDocument($other, 'in_session', ['field_document_types' => $theirs->id()]);

    $this->assertSame(
      [$mine->id()],
      array_map('strval', $this->meetingService->getDocumentTypesIdsInUse($meeting->id(), 'in_session'))
    );
  }

  /**
   * Tests that unpublished documents do not put a type on screen.
   *
   * A heading whose only document is unpublished would open onto an empty
   * list for everyone but its author.
   *
   * @covers ::getDocumentTypesIdsInUse
   */
  public function testUnpublishedDocumentsDoNotPutTypeOnScreen(): void {
    $meeting = $this->createMeeting();
    $type = $this->createDocumentType('Working papers');

    $this->createDocument($meeting, 'in_session', ['field_document_types' => $type->id()], FALSE);

    $this->assertSame([], $this->meetingService->getDocumentTypesIdsInUse($meeting->id(), 'in_session'));
  }

  /**
   * Tests that a type used by many documents is listed once.
   *
   * @covers ::getDocumentTypesIdsInUse
   */
  public function testTypeUsedManyTimesIsListedOnce(): void {
    $meeting = $this->createMeeting();
    $type = $this->createDocumentType('Working papers');

    $this->createDocument($meeting, 'in_session', ['field_document_types' => $type->id()]);
    $this->createDocument($meeting, 'in_session', ['field_document_types' => $type->id()]);
    $this->createDocument($meeting, 'in_session', ['field_document_types' => $type->id()]);

    $this->assertSame(
      [$type->id()],
      array_map('strval', $this->meetingService->getDocumentTypesIdsInUse($meeting->id(), 'in_session'))
    );
  }

  /**
   * Tests that the types come back in the vocabulary's own order.
   *
   * The headings follow the order an editor gave the vocabulary, not the
   * order documents happened to be uploaded in.
   *
   * @covers ::getDocumentTypesIdsInUse
   */
  public function testTheTypesComeBackInVocabularyOrder(): void {
    $meeting = $this->createMeeting();
    $last = $this->createDocumentType('Information papers', 10);
    $first = $this->createDocumentType('Agenda', -10);
    $middle = $this->createDocumentType('Working papers', 0);

    $this->createDocument($meeting, 'in_session', ['field_document_types' => $last->id()]);
    $this->createDocument($meeting, 'in_session', ['field_document_types' => $middle->id()]);
    $this->createDocument($meeting, 'in_session', ['field_document_types' => $first->id()]);

    $this->assertSame(
      [$first->id(), $middle->id(), $last->id()],
      array_map('strval', $this->meetingService->getDocumentTypesIdsInUse($meeting->id(), 'in_session'))
    );
  }

  /**
   * Tests the same lookup against an arbitrary reference field.
   *
   * Sites group in-session documents by their own fields — contact groups,
   * for example — so the lookup is repeated for any reference field rather
   * than just the document type.
   *
   * @covers ::getEntityReferenceIdsInUse
   */
  public function testAnyReferenceFieldCanBeLookedUp(): void {
    $meeting = $this->createMeeting();
    $used = $this->createAgenda($meeting, 'Opening of the meeting');
    $unused = $this->createAgenda($meeting, 'Any other business');

    $this->createDocument($meeting, 'in_session', ['field_agenda' => $used->id()]);

    $ids = $this->meetingService
      ->getEntityReferenceIdsInUse($meeting->id(), 'in_session', 'field_agenda');

    $this->assertSame([$used->id()], array_map('strval', $ids));
    $this->assertNotContains((string) $unused->id(), array_map('strval', $ids));
  }

  /**
   * Tests that the generic lookup also ignores unpublished documents.
   *
   * @covers ::getEntityReferenceIdsInUse
   */
  public function testTheGenericLookupIgnoresUnpublishedDocuments(): void {
    $meeting = $this->createMeeting();
    $agenda = $this->createAgenda($meeting, 'Opening of the meeting');

    $this->createDocument($meeting, 'in_session', ['field_agenda' => $agenda->id()], FALSE);

    $this->assertSame([], $this->meetingService
      ->getEntityReferenceIdsInUse($meeting->id(), 'in_session', 'field_agenda'));
  }

  /**
   * Tests the agenda lookup used by the in-session grouping.
   *
   * @covers \Drupal\edw_event_agenda\Services\MeetingAgendaService::getMeetingAgendaIdsInUse
   */
  public function testTheAgendasInUseAreFound(): void {
    $meeting = $this->createMeeting();
    $used = $this->createAgenda($meeting, 'Opening of the meeting');
    $this->createAgenda($meeting, 'Any other business');

    $this->createDocument($meeting, 'in_session', ['field_agenda' => $used->id()]);
    $this->createDocument($meeting, 'pre_session', ['field_agenda' => $used->id()]);

    $ids = $this->agendaService->getMeetingAgendaIdsInUse($meeting->id(), 'in_session', FALSE);

    $this->assertSame([$used->id()], array_map('strval', $ids));
  }

  /**
   * Tests that checking status hides unpublished agendas and documents.
   *
   * Without the check this is the administrative view, which has to show a
   * heading for an agenda item that is still a draft.
   *
   * @covers \Drupal\edw_event_agenda\Services\MeetingAgendaService::getMeetingAgendaIdsInUse
   */
  public function testCheckingStatusHidesUnpublishedAgendasAndDocuments(): void {
    $meeting = $this->createMeeting();
    $live = $this->createAgenda($meeting, 'Opening of the meeting');
    $draftAgenda = $this->createAgenda($meeting, 'Any other business', FALSE, FALSE);
    $withDraftDocument = $this->createAgenda($meeting, 'Adoption of the report');

    $this->createDocument($meeting, 'in_session', ['field_agenda' => $live->id()]);
    $this->createDocument($meeting, 'in_session', ['field_agenda' => $draftAgenda->id()]);
    $this->createDocument($meeting, 'in_session', ['field_agenda' => $withDraftDocument->id()], FALSE);

    $unchecked = $this->agendaService->getMeetingAgendaIdsInUse($meeting->id(), 'in_session', FALSE);
    $checked = $this->agendaService->getMeetingAgendaIdsInUse($meeting->id(), 'in_session', TRUE);

    $this->assertEqualsCanonicalizing(
      [$live->id(), $draftAgenda->id(), $withDraftDocument->id()],
      array_map('strval', $unchecked)
    );
    $this->assertSame([$live->id()], array_map('strval', $checked));
  }

  /**
   * Creates a document type term.
   *
   * @param string $name
   *   The term name.
   * @param int $weight
   *   The term weight, which decides the heading order.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   The saved term.
   */
  protected function createDocumentType(string $name, int $weight = 0): TermInterface {
    $term = Term::create([
      'vid' => 'document_types',
      'name' => $name,
      'weight' => $weight,
    ]);
    $term->save();

    return $term;
  }

  /**
   * Creates a document media item attached to a meeting.
   *
   * @param \Drupal\node\NodeInterface $meeting
   *   The meeting the document belongs to.
   * @param string $phase
   *   The phase of the meeting.
   * @param array $values
   *   Extra field values, such as the document type or agenda.
   * @param bool $status
   *   Whether the document is published.
   *
   * @return \Drupal\media\MediaInterface
   *   The saved media item.
   */
  protected function createDocument(NodeInterface $meeting, string $phase, array $values = [], bool $status = TRUE) {
    $document = Media::create([
      'bundle' => 'document',
      'name' => 'A document',
      'status' => $status,
      'field_meetings' => $meeting->id(),
      'field_document_phase' => $phase,
    ] + $values);
    $document->save();

    return $document;
  }

}
