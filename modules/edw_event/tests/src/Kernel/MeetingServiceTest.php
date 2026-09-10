<?php

namespace Drupal\Tests\edw_event\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_event\Traits\EventTestTrait;
use Drupal\node\Entity\Node;

/**
 * Tests finding the sections that belong to a meeting.
 *
 * A meeting is a node, and its sections are separate nodes pointing back at
 * it through field_event. Almost everything else in the module starts here:
 * the clone form lists them, deleting a meeting deletes them, and the reorder
 * screen sorts them. The $checkStatus flag is the subtle part — it switches
 * both access checking and the published filter at once, so administrative
 * callers see everything and front-end callers see only what is live.
 *
 * @coversDefaultClass \Drupal\edw_event\Services\MeetingService
 *
 * @group edw_event
 */
class MeetingServiceTest extends KernelTestBase {

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
    'entity_clone',
    'edw_event',
  ];

  /**
   * The meeting service under test.
   *
   * @var \Drupal\edw_event\Services\MeetingService
   */
  protected $meetingService;

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

    $this->meetingService = \Drupal::service('edw_event.meeting.service');
  }

  /**
   * Tests that a meeting's own sections are found.
   *
   * @covers ::getAllMeetingSectionsIds
   */
  public function testMeetingsSectionsAreFound(): void {
    $meeting = $this->createMeeting();
    $first = $this->createMeetingSection($meeting, 'Pre-session', 'pre_session');
    $second = $this->createMeetingSection($meeting, 'In-session', 'in_session');

    $ids = $this->meetingService->getAllMeetingSectionsIds($meeting, FALSE);

    $this->assertEqualsCanonicalizing(
      [$first->id(), $second->id()],
      array_values($ids)
    );
  }

  /**
   * Tests that another meeting's sections are not returned.
   *
   * @covers ::getAllMeetingSectionsIds
   */
  public function testAnotherMeetingsSectionsAreNotReturned(): void {
    $meeting = $this->createMeeting('Fifth meeting');
    $other = $this->createMeeting('Sixth meeting');
    $mine = $this->createMeetingSection($meeting, 'Pre-session', 'pre_session');
    $this->createMeetingSection($other, 'Pre-session', 'pre_session');

    $ids = $this->meetingService->getAllMeetingSectionsIds($meeting, FALSE);

    $this->assertSame([$mine->id()], array_values($ids));
  }

  /**
   * Tests that only 'event_section' nodes count as sections.
   *
   * @covers ::getAllMeetingSectionsIds
   */
  public function testOnlySectionNodesAreReturned(): void {
    $meeting = $this->createMeeting();
    $section = $this->createMeetingSection($meeting, 'Pre-session', 'pre_session');

    // A meeting pointing at another meeting is not one of its sections.
    $this->createField('node', 'event', 'field_event', 'entity_reference', ['target_type' => 'node']);
    $decoy = Node::create([
      'type' => 'event',
      'title' => 'Not a section',
      'field_event' => $meeting->id(),
    ]);
    $decoy->save();

    $ids = $this->meetingService->getAllMeetingSectionsIds($meeting, FALSE);

    $this->assertSame([$section->id()], array_values($ids));
  }

  /**
   * Tests that unpublished sections are hidden when the status is checked.
   *
   * The clone form and the delete cascade pass FALSE because they are
   * administrative and must see every section; anything rendering to a
   * visitor passes TRUE.
   *
   * @covers ::getAllMeetingSectionsIds
   */
  public function testUnpublishedSectionsAreHiddenWhenTheStatusIsChecked(): void {
    $meeting = $this->createMeeting();
    $published = $this->createMeetingSection($meeting, 'Published', 'pre_session', TRUE);
    $unpublished = $this->createMeetingSection($meeting, 'Draft', 'in_session', FALSE);

    $checked = $this->meetingService->getAllMeetingSectionsIds($meeting, TRUE);
    $unchecked = $this->meetingService->getAllMeetingSectionsIds($meeting, FALSE);

    $this->assertSame([$published->id()], array_values($checked));
    $this->assertEqualsCanonicalizing(
      [$published->id(), $unpublished->id()],
      array_values($unchecked)
    );
  }

  /**
   * Tests that a meeting with no sections comes back empty.
   *
   * The clone form and the delete cascade both branch on an empty result, so
   * this must be an empty array rather than anything falsier.
   *
   * @covers ::getAllMeetingSectionsIds
   * @covers ::getAllMeetingSections
   */
  public function testMeetingWithNoSectionsComesBackEmpty(): void {
    $meeting = $this->createMeeting();

    $this->assertSame([], $this->meetingService->getAllMeetingSectionsIds($meeting, FALSE));
    $this->assertSame([], $this->meetingService->getAllMeetingSections($meeting, FALSE));
  }

  /**
   * Tests that the loaded sections are the entities, keyed by id.
   *
   * @covers ::getAllMeetingSections
   */
  public function testTheLoadedSectionsAreKeyedById(): void {
    $meeting = $this->createMeeting();
    $section = $this->createMeetingSection($meeting, 'Pre-session', 'pre_session');

    $sections = $this->meetingService->getAllMeetingSections($meeting, FALSE);

    $this->assertSame([(int) $section->id()], array_keys($sections));
    $this->assertSame('Pre-session', $sections[$section->id()]->label());
  }

  /**
   * Tests loading sections and agenda terms by id.
   *
   * These two wrappers exist so callers do not have to reach for the entity
   * type manager themselves; they must not silently cross entity types.
   *
   * @covers ::loadMultipleSections
   * @covers ::loadMultipleTerms
   */
  public function testSectionsAndTermsAreLoadedFromTheirOwnStorage(): void {
    $meeting = $this->createMeeting();
    $section = $this->createMeetingSection($meeting, 'Pre-session', 'pre_session');
    $agenda = $this->createAgenda($meeting, 'Opening of the meeting');

    $sections = $this->meetingService->loadMultipleSections([$section->id()]);
    $terms = $this->meetingService->loadMultipleTerms([$agenda->id()]);

    $this->assertSame('node', reset($sections)->getEntityTypeId());
    $this->assertSame('taxonomy_term', reset($terms)->getEntityTypeId());
    $this->assertSame([], $this->meetingService->loadMultipleSections([]));
  }

}
