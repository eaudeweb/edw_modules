<?php

namespace Drupal\Tests\edw_event\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_event\Traits\EventTestTrait;
use Drupal\edw_event_agenda\Commands\AgendaCommands;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests a meeting's agenda items.
 *
 * Agenda items are taxonomy terms rather than nodes, and they point at their
 * meeting the same way sections do. Each meeting also gets one item flagged
 * as the default, which is where documents land when nobody picked an agenda
 * item for them — so there must never be two of those.
 *
 * @coversDefaultClass \Drupal\edw_event_agenda\Services\MeetingAgendaService
 *
 * @group edw_event
 */
class MeetingAgendaServiceTest extends KernelTestBase {

  use EventTestTrait;

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
    'draggableviews',
    'entity_clone',
    'edw_event',
    'edw_event_agenda',
  ];

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
    $this->installSchema('node', ['node_access']);

    $this->createMeetingContentTypes();
    $this->createAgendaVocabulary();

    $this->agendaService = \Drupal::service('edw_event_agenda.meeting.agenda.service');
  }

  /**
   * Tests that a meeting's agenda items are found.
   *
   * @covers ::getMeetingAgendaIds
   * @covers ::getAllMeetingAgendas
   */
  public function testMeetingsAgendaItemsAreFound(): void {
    $meeting = $this->createMeeting();
    $first = $this->createAgenda($meeting, 'Opening of the meeting');
    $second = $this->createAgenda($meeting, 'Adoption of the agenda');

    $ids = $this->agendaService->getMeetingAgendaIds($meeting->id(), FALSE);
    $agendas = $this->agendaService->getAllMeetingAgendas($meeting, FALSE);

    $this->assertEqualsCanonicalizing([$first->id(), $second->id()], array_values($ids));
    $this->assertEqualsCanonicalizing(
      ['Opening of the meeting', 'Adoption of the agenda'],
      array_map(fn ($agenda) => $agenda->label(), array_values($agendas))
    );
  }

  /**
   * Tests that another meeting's agenda is not returned.
   *
   * @covers ::getMeetingAgendaIds
   */
  public function testAnotherMeetingsAgendaIsNotReturned(): void {
    $meeting = $this->createMeeting('Fifth meeting');
    $other = $this->createMeeting('Sixth meeting');
    $mine = $this->createAgenda($meeting, 'Opening of the meeting');
    $this->createAgenda($other, 'Opening of the meeting');

    $this->assertSame([$mine->id()], array_values($this->agendaService->getMeetingAgendaIds($meeting->id(), FALSE)));
  }

  /**
   * Tests that terms outside the agenda vocabulary are ignored.
   *
   * @covers ::getMeetingAgendaIds
   */
  public function testTermsOutsideTheAgendaVocabularyAreIgnored(): void {
    $meeting = $this->createMeeting();
    $agenda = $this->createAgenda($meeting, 'Opening of the meeting');

    // A term in another vocabulary pointing at the same meeting.
    Vocabulary::create(['vid' => 'topics', 'name' => 'Topics'])->save();
    $this->createField('taxonomy_term', 'topics', 'field_event', 'entity_reference', ['target_type' => 'node']);
    Term::create([
      'vid' => 'topics',
      'name' => 'Finance',
      'field_event' => $meeting->id(),
    ])->save();

    $this->assertSame([$agenda->id()], array_values($this->agendaService->getMeetingAgendaIds($meeting->id(), FALSE)));
  }

  /**
   * Tests that unpublished agenda items are hidden when status is checked.
   *
   * @covers ::getMeetingAgendaIds
   */
  public function testUnpublishedAgendaItemsAreHiddenWhenStatusIsChecked(): void {
    $meeting = $this->createMeeting();
    $published = $this->createAgenda($meeting, 'Opening of the meeting', FALSE, TRUE);
    $draft = $this->createAgenda($meeting, 'Any other business', FALSE, FALSE);

    $checked = $this->agendaService->getMeetingAgendaIds($meeting->id(), TRUE);
    $unchecked = $this->agendaService->getMeetingAgendaIds($meeting->id(), FALSE);

    $this->assertSame([$published->id()], array_values($checked));
    $this->assertEqualsCanonicalizing([$published->id(), $draft->id()], array_values($unchecked));
  }

  /**
   * Tests that a default agenda is created for a meeting.
   *
   * @covers ::createDefaultAgenda
   */
  public function testDefaultAgendaIsCreated(): void {
    $meeting = $this->createMeeting();

    $this->agendaService->createDefaultAgenda((int) $meeting->id(), 'Other documents');

    $agendas = $this->agendaService->getAllMeetingAgendas($meeting, FALSE);
    $this->assertCount(1, $agendas);
    $agenda = reset($agendas);
    $this->assertSame('Other documents', $agenda->label());
    $this->assertTrue((bool) $agenda->get('field_is_default_agenda')->value);
    $this->assertSame($meeting->id(), $agenda->get('field_event')->target_id);
  }

  /**
   * Tests that a meeting is not given a second default agenda.
   *
   * Documents with no agenda item land on the default one, so a second
   * default would split them across two headings unpredictably.
   *
   * @covers ::createDefaultAgenda
   */
  public function testMeetingIsNotGivenSecondDefaultAgenda(): void {
    $meeting = $this->createMeeting();

    $this->agendaService->createDefaultAgenda((int) $meeting->id(), 'Other documents');
    $this->agendaService->createDefaultAgenda((int) $meeting->id(), 'More documents');

    $agendas = $this->agendaService->getAllMeetingAgendas($meeting, FALSE);
    $this->assertCount(1, $agendas);
    $this->assertSame('Other documents', reset($agendas)->label());
  }

  /**
   * Tests that an ordinary agenda item does not block the default one.
   *
   * @covers ::createDefaultAgenda
   */
  public function testAnOrdinaryAgendaItemDoesNotBlockTheDefault(): void {
    $meeting = $this->createMeeting();
    $this->createAgenda($meeting, 'Opening of the meeting');

    $this->agendaService->createDefaultAgenda((int) $meeting->id(), 'Other documents');

    $this->assertCount(2, $this->agendaService->getAllMeetingAgendas($meeting, FALSE));
  }

  /**
   * Tests that each meeting gets its own default agenda.
   *
   * @covers ::createDefaultAgenda
   */
  public function testEachMeetingGetsItsOwnDefaultAgenda(): void {
    $meeting = $this->createMeeting('Fifth meeting');
    $other = $this->createMeeting('Sixth meeting');

    $this->agendaService->createDefaultAgenda((int) $meeting->id(), 'Other documents');
    $this->agendaService->createDefaultAgenda((int) $other->id(), 'Other documents');

    $this->assertCount(1, $this->agendaService->getAllMeetingAgendas($meeting, FALSE));
    $this->assertCount(1, $this->agendaService->getAllMeetingAgendas($other, FALSE));
  }

  /**
   * Tests that the drush command creates the default agenda.
   *
   * @covers \Drupal\edw_event_agenda\Commands\AgendaCommands::createDefaultAgenda
   */
  public function testTheDrushCommandCreatesTheDefaultAgenda(): void {
    $meeting = $this->createMeeting();

    (new AgendaCommands($this->agendaService))
      ->createDefaultAgenda((int) $meeting->id(), 'Other documents');

    $this->assertCount(1, $this->agendaService->getAllMeetingAgendas($meeting, FALSE));
  }

  /**
   * Tests that agenda items are ordered from the draggableviews table.
   *
   * @covers ::orderMeetingAgendaIds
   */
  public function testAgendaItemsAreOrderedByTheSavedWeight(): void {
    $this->installSchema('draggableviews', ['draggableviews_structure']);

    $meeting = $this->createMeeting();
    $first = $this->createAgenda($meeting, 'Opening of the meeting');
    $second = $this->createAgenda($meeting, 'Adoption of the agenda');

    foreach ([$second->id() => 0, $first->id() => 1] as $entityId => $weight) {
      \Drupal::database()->insert('draggableviews_structure')
        ->fields([
          'view_name' => 'meeting_agendas',
          'view_display' => 'order_agendas',
          'args' => json_encode([$meeting->id()]),
          'entity_id' => $entityId,
          'weight' => $weight,
          'parent' => 0,
        ])
        ->execute();
    }

    $ids = $this->agendaService
      ->orderMeetingAgendaIds($meeting->id(), 'meeting_agendas', 'order_agendas');

    $this->assertSame([$second->id(), $first->id()], array_map('strval', $ids));
  }

}
