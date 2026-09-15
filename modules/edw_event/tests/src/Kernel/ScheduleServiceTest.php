<?php

namespace Drupal\Tests\edw_event\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_event\Traits\EventTestTrait;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Tests the daily schedule's published state.
 *
 * A multi-day meeting's programme is a list of days, each holding the
 * activities taking place on it. A day with nothing on it — or nothing that
 * is public yet — should not appear at all, so the day's published state is
 * derived from its items rather than set by hand.
 *
 * Reading those items has two cases: a day already in storage has its items
 * as separate saved paragraphs, while a day being created for the first time
 * only has them in memory on the form.
 *
 * @coversDefaultClass \Drupal\edw_event_daily_schedule\Services\ScheduleService
 *
 * @group edw_event
 */
class ScheduleServiceTest extends KernelTestBase {

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
    'file',
    'entity_reference_revisions',
    'paragraphs',
    'entity_clone',
    'edw_event',
    'edw_event_daily_schedule',
  ];

  /**
   * The schedule service under test.
   *
   * @var \Drupal\edw_event_daily_schedule\Services\ScheduleService
   */
  protected $scheduleService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);

    $this->createMeetingContentTypes();

    ParagraphsType::create(['id' => 'edw_daily_schedule', 'label' => 'Daily schedule'])->save();
    ParagraphsType::create(['id' => 'edw_daily_schedule_item', 'label' => 'Daily schedule item'])->save();
    ParagraphsType::create(['id' => 'edw_text', 'label' => 'Text'])->save();
    $this->createField('paragraph', 'edw_daily_schedule', 'field_paragraphs', 'entity_reference_revisions', [
      'target_type' => 'paragraph',
    ], -1);

    $this->scheduleService = \Drupal::service('edw_event_daily_schedule.schedule.service');
  }

  /**
   * Tests that a stored day's published items are found.
   *
   * @covers ::getScheduleItems
   */
  public function testStoredDaysPublishedItemsAreFound(): void {
    $day = $this->createDay();
    $first = $this->createItem($day, 'edw_daily_schedule_item', TRUE);
    $second = $this->createItem($day, 'edw_daily_schedule_item', TRUE);

    $items = $this->scheduleService->getScheduleItems($day);

    $this->assertEqualsCanonicalizing(
      [$first->id(), $second->id()],
      array_map(fn (ParagraphInterface $item) => $item->id(), array_values($items))
    );
  }

  /**
   * Tests that unpublished items of a stored day are skipped.
   *
   * @covers ::getScheduleItems
   */
  public function testUnpublishedItemsOfStoredDayAreSkipped(): void {
    $day = $this->createDay();
    $published = $this->createItem($day, 'edw_daily_schedule_item', TRUE);
    $this->createItem($day, 'edw_daily_schedule_item', FALSE);

    $items = $this->scheduleService->getScheduleItems($day);

    $this->assertCount(1, $items);
    $this->assertSame($published->id(), reset($items)->id());
  }

  /**
   * Tests that other kinds of paragraph on the day are not schedule items.
   *
   * A day can hold an intro text alongside its activities; that text must not
   * be enough to keep the day published.
   *
   * @covers ::getScheduleItems
   */
  public function testOtherKindsOfParagraphAreNotScheduleItems(): void {
    $day = $this->createDay();
    $this->createItem($day, 'edw_text', TRUE);

    $this->assertCount(0, $this->scheduleService->getScheduleItems($day));
  }

  /**
   * Tests that another day's items are not counted.
   *
   * @covers ::getScheduleItems
   */
  public function testAnotherDaysItemsAreNotCounted(): void {
    $day = $this->createDay();
    $other = $this->createDay();
    $mine = $this->createItem($day, 'edw_daily_schedule_item', TRUE);
    $this->createItem($other, 'edw_daily_schedule_item', TRUE);

    $items = $this->scheduleService->getScheduleItems($day);

    $this->assertCount(1, $items);
    $this->assertSame($mine->id(), reset($items)->id());
  }

  /**
   * Tests that a day being created reads its items from the form.
   *
   * On first save the items have no parent_id to query by yet, so they have
   * to be read off the field as it stands in memory.
   *
   * @covers ::getScheduleItems
   */
  public function testDayBeingCreatedReadsItsItemsFromTheForm(): void {
    $published = Paragraph::create(['type' => 'edw_daily_schedule_item', 'status' => 1]);
    $published->save();
    $unpublished = Paragraph::create(['type' => 'edw_daily_schedule_item', 'status' => 0]);
    $unpublished->save();
    $text = Paragraph::create(['type' => 'edw_text', 'status' => 1]);
    $text->save();

    $day = Paragraph::create([
      'type' => 'edw_daily_schedule',
      'field_paragraphs' => [$published, $unpublished, $text],
    ]);

    $items = $this->scheduleService->getScheduleItems($day);

    $this->assertCount(1, $items);
    $this->assertSame($published->id(), reset($items)->id());
  }

  /**
   * Tests that a new day with nothing on it has no items.
   *
   * @covers ::getScheduleItems
   */
  public function testNewDayWithNothingOnItHasNoItems(): void {
    $day = Paragraph::create(['type' => 'edw_daily_schedule']);

    $this->assertSame([], $this->scheduleService->getScheduleItems($day));
  }

  /**
   * Tests that a paragraph's published state can be set either way.
   *
   * @covers ::updateParagraphStatus
   */
  public function testParagraphsPublishedStateCanBeSetEitherWay(): void {
    $day = $this->createDay();

    $this->scheduleService->updateParagraphStatus($day, FALSE);
    $this->assertFalse($day->isPublished());

    $this->scheduleService->updateParagraphStatus($day, TRUE);
    $this->assertTrue($day->isPublished());
  }

  /**
   * Creates a daily schedule day.
   *
   * @param bool $status
   *   Whether the day is published.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The saved day.
   */
  protected function createDay(bool $status = TRUE): ParagraphInterface {
    $day = Paragraph::create(['type' => 'edw_daily_schedule', 'status' => $status]);
    $day->save();

    return $day;
  }

  /**
   * Creates a paragraph parented to a day.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $day
   *   The day the paragraph belongs to.
   * @param string $type
   *   The paragraph bundle.
   * @param bool $status
   *   Whether the paragraph is published.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The saved paragraph.
   */
  protected function createItem(ParagraphInterface $day, string $type, bool $status): ParagraphInterface {
    $item = Paragraph::create([
      'type' => $type,
      'status' => $status,
      'parent_type' => 'paragraph',
      'parent_id' => $day->id(),
      'parent_field_name' => 'field_paragraphs',
    ]);
    $item->save();

    return $item;
  }

}
