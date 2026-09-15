<?php

namespace Drupal\Tests\edw_event\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_event\Traits\EventTestTrait;

/**
 * Tests the manual ordering of a meeting's sections and agenda items.
 *
 * Editors drag sections into the order they want on the reorder screen, and
 * draggableviews records that order in its own table keyed by view, display
 * and the view's arguments. Reading it back is not just "select ordered by
 * weight": draggableviews only writes a row once the reorder form has been
 * saved, so an item added afterwards has no weight at all and would vanish
 * from the list if the query result were used on its own.
 *
 * @coversDefaultClass \Drupal\edw_event\Services\MeetingService
 *
 * @group edw_event
 */
class MeetingSectionOrderTest extends KernelTestBase {

  use EventTestTrait;

  const VIEW_NAME = 'meeting_sections';

  const VIEW_DISPLAY = 'order_meeting_sections';

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
    $this->installSchema('draggableviews', ['draggableviews_structure']);

    $this->createMeetingContentTypes();

    $this->meetingService = \Drupal::service('edw_event.meeting.service');
  }

  /**
   * Tests that the saved order is returned, not the creation order.
   *
   * @covers ::getOrderIds
   * @covers ::orderMeetingSectionIds
   */
  public function testTheSavedOrderIsReturned(): void {
    $meeting = $this->createMeeting();
    $first = $this->createMeetingSection($meeting, 'Pre-session', 'pre_session');
    $second = $this->createMeetingSection($meeting, 'In-session', 'in_session');
    $third = $this->createMeetingSection($meeting, 'Post-session', 'post_session');

    // The editor dragged them into reverse order.
    $this->recordOrder($meeting->id(), [
      $third->id() => 0,
      $second->id() => 1,
      $first->id() => 2,
    ]);

    $ordered = $this->meetingService
      ->orderMeetingSectionIds($meeting->id(), self::VIEW_NAME, self::VIEW_DISPLAY);

    $this->assertSame(
      [$third->id(), $second->id(), $first->id()],
      array_map('strval', $ordered)
    );
  }

  /**
   * Tests that a section added after the last reorder still appears.
   *
   * This is the case the array merge exists for: a new section has no row in
   * the draggableviews table until someone opens and saves the reorder form,
   * and until then it must still be listed.
   *
   * @covers ::getOrderIds
   */
  public function testSectionWithNoSavedWeightIsStillListed(): void {
    $meeting = $this->createMeeting();
    $ordered = $this->createMeetingSection($meeting, 'Pre-session', 'pre_session');
    $added = $this->createMeetingSection($meeting, 'Added later', 'in_session');

    $this->recordOrder($meeting->id(), [$ordered->id() => 0]);

    $ids = $this->meetingService->getOrderIds(
      $meeting->id(),
      self::VIEW_NAME,
      self::VIEW_DISPLAY,
      [$ordered->id(), $added->id()]
    );

    // The one with a weight leads; the new one is appended rather than lost.
    $this->assertSame(
      [$ordered->id(), $added->id()],
      array_map('strval', array_values($ids))
    );
  }

  /**
   * Tests that an id appearing in both sources is not listed twice.
   *
   * @covers ::getOrderIds
   */
  public function testAnOrderedIdIsNotListedTwice(): void {
    $meeting = $this->createMeeting();
    $section = $this->createMeetingSection($meeting, 'Pre-session', 'pre_session');

    $this->recordOrder($meeting->id(), [$section->id() => 0]);

    $ids = $this->meetingService->getOrderIds(
      $meeting->id(),
      self::VIEW_NAME,
      self::VIEW_DISPLAY,
      [$section->id()]
    );

    $this->assertCount(1, $ids);
  }

  /**
   * Tests that the order of another meeting is not read.
   *
   * The table is shared by every meeting using the same view, and the only
   * thing separating them is the serialised argument list.
   *
   * @covers ::getOrderIds
   */
  public function testTheOrderOfAnotherMeetingIsNotRead(): void {
    $meeting = $this->createMeeting('Fifth meeting');
    $other = $this->createMeeting('Sixth meeting');
    $mine = $this->createMeetingSection($meeting, 'Pre-session', 'pre_session');
    $theirs = $this->createMeetingSection($other, 'Pre-session', 'pre_session');

    $this->recordOrder($meeting->id(), [$mine->id() => 0]);
    $this->recordOrder($other->id(), [$theirs->id() => 0]);

    $ids = $this->meetingService
      ->orderMeetingSectionIds($meeting->id(), self::VIEW_NAME, self::VIEW_DISPLAY);

    $this->assertSame([$mine->id()], array_map('strval', $ids));
  }

  /**
   * Tests that another view's order is not read.
   *
   * The same meeting has both a section reorder screen and an agenda reorder
   * screen, whose rows live side by side in this table.
   *
   * @covers ::getOrderIds
   */
  public function testAnotherViewsOrderIsNotRead(): void {
    $meeting = $this->createMeeting();
    $section = $this->createMeetingSection($meeting, 'Pre-session', 'pre_session');

    $this->recordOrder($meeting->id(), [$section->id() => 0], 'meeting_agendas', 'order_agendas');

    $this->assertSame([], $this->meetingService
      ->orderMeetingSectionIds($meeting->id(), self::VIEW_NAME, self::VIEW_DISPLAY));
  }

  /**
   * Tests that passing ids narrows the result to those ids.
   *
   * @covers ::getOrderIds
   */
  public function testPassingIdsNarrowsTheResult(): void {
    $meeting = $this->createMeeting();
    $wanted = $this->createMeetingSection($meeting, 'Pre-session', 'pre_session');
    $unwanted = $this->createMeetingSection($meeting, 'In-session', 'in_session');

    $this->recordOrder($meeting->id(), [
      $wanted->id() => 0,
      $unwanted->id() => 1,
    ]);

    $ids = $this->meetingService->getOrderIds(
      $meeting->id(),
      self::VIEW_NAME,
      self::VIEW_DISPLAY,
      [$wanted->id()]
    );

    $this->assertSame([$wanted->id()], array_map('strval', array_values($ids)));
  }

  /**
   * Tests that a meeting nobody has reordered comes back empty.
   *
   * @covers ::getOrderIds
   */
  public function testMeetingWithNoSavedOrderComesBackEmpty(): void {
    $meeting = $this->createMeeting();
    $this->createMeetingSection($meeting, 'Pre-session', 'pre_session');

    $this->assertSame([], $this->meetingService
      ->orderMeetingSectionIds($meeting->id(), self::VIEW_NAME, self::VIEW_DISPLAY));
  }

  /**
   * Records a draggableviews order for a meeting.
   *
   * @param string|int $meetingId
   *   The meeting id, which is the view's single argument.
   * @param array $weights
   *   Entity id to weight.
   * @param string $viewName
   *   The view the order belongs to.
   * @param string $viewDisplay
   *   The display the order belongs to.
   */
  protected function recordOrder($meetingId, array $weights, string $viewName = self::VIEW_NAME, string $viewDisplay = self::VIEW_DISPLAY): void {
    foreach ($weights as $entityId => $weight) {
      \Drupal::database()->insert('draggableviews_structure')
        ->fields([
          'view_name' => $viewName,
          'view_display' => $viewDisplay,
          'args' => json_encode([$meetingId]),
          'entity_id' => $entityId,
          'weight' => $weight,
          'parent' => 0,
        ])
        ->execute();
    }
  }

}
