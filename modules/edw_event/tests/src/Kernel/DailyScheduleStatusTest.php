<?php

namespace Drupal\Tests\edw_event\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_event\Traits\EventTestTrait;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Tests that a schedule day publishes itself from its activities.
 *
 * Editors never set a day's published state directly — they add and remove
 * activities, and the day follows. A day whose activities are all still
 * drafts has nothing to show, so it must not appear in the programme.
 *
 * @group edw_event
 */
class DailyScheduleStatusTest extends KernelTestBase {

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
    $this->createField('paragraph', 'edw_daily_schedule', 'field_paragraphs', 'entity_reference_revisions', [
      'target_type' => 'paragraph',
    ], -1);
  }

  /**
   * Tests that a day created with a public activity is published.
   */
  public function testDayCreatedWithPublicActivityIsPublished(): void {
    $activity = $this->createActivity(TRUE);

    $day = Paragraph::create([
      'type' => 'edw_daily_schedule',
      'status' => 0,
      'field_paragraphs' => [$activity],
    ]);
    $day->save();

    $this->assertTrue($day->isPublished());
  }

  /**
   * Tests that a day whose activities are all drafts is not published.
   */
  public function testDayWhoseActivitiesAreAllDraftsIsNotPublished(): void {
    $draft = $this->createActivity(FALSE);

    $day = Paragraph::create([
      'type' => 'edw_daily_schedule',
      'status' => 1,
      'field_paragraphs' => [$draft],
    ]);
    $day->save();

    $this->assertFalse($day->isPublished());
  }

  /**
   * Tests that an empty day is not published.
   */
  public function testAnEmptyDayIsNotPublished(): void {
    $day = Paragraph::create(['type' => 'edw_daily_schedule', 'status' => 1]);
    $day->save();

    $this->assertFalse($day->isPublished());
  }

  /**
   * Tests that a stored day is re-evaluated when it is saved again.
   *
   * Its activities are separate entities by then, so the day is judged on
   * what is in storage rather than on what is on the form.
   */
  public function testStoredDayIsReEvaluatedWhenSavedAgain(): void {
    $day = Paragraph::create(['type' => 'edw_daily_schedule', 'status' => 0]);
    $day->save();
    $this->attachActivity($day, TRUE);

    $day->save();

    $this->assertTrue($day->isPublished());
  }

  /**
   * Tests that a day goes back to unpublished when its activities are pulled.
   */
  public function testDayGoesBackToUnpublishedWhenItsActivitiesArePulled(): void {
    $day = Paragraph::create(['type' => 'edw_daily_schedule', 'status' => 0]);
    $day->save();
    $activity = $this->attachActivity($day, TRUE);
    $day->save();
    $this->assertTrue($day->isPublished());

    $activity->setUnpublished();
    $activity->save();
    $day->save();

    $this->assertFalse($day->isPublished());
  }

  /**
   * Tests that adding an activity to a live day does not hide it.
   *
   * This is the ordinary editing case: a day is already public and another
   * activity is added to it. The presave hook decides the day's state from
   * "has activities AND is not currently published", which is only true while
   * the day is still hidden — so adding to a day that is already live flips
   * it back off, and the whole day disappears from the programme.
   */
  public function testAddingAnActivityToLiveDayDoesNotHideIt(): void {
    $day = Paragraph::create(['type' => 'edw_daily_schedule', 'status' => 1]);
    $day->save();

    $this->attachActivity($day, TRUE);

    $storage = \Drupal::entityTypeManager()->getStorage('paragraph');
    $storage->resetCache([$day->id()]);

    $this->assertTrue($storage->load($day->id())->isPublished());
  }

  /**
   * Tests that an activity with no day at all is left alone.
   *
   * An activity can exist before it is placed, and the hook must not fail on
   * one that has no parent yet.
   */
  public function testAnActivityWithNoDayIsLeftAlone(): void {
    $orphan = Paragraph::create(['type' => 'edw_daily_schedule_item', 'status' => 1]);

    edw_event_daily_schedule_paragraph_presave($orphan);

    $this->assertTrue($orphan->isPublished());
  }

  /**
   * Creates a standalone activity.
   *
   * @param bool $status
   *   Whether the activity is published.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The saved activity.
   */
  protected function createActivity(bool $status): ParagraphInterface {
    $activity = Paragraph::create([
      'type' => 'edw_daily_schedule_item',
      'status' => $status,
    ]);
    $activity->save();

    return $activity;
  }

  /**
   * Creates an activity already parented to a day.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $day
   *   The day the activity belongs to.
   * @param bool $status
   *   Whether the activity is published.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The saved activity.
   */
  protected function attachActivity(ParagraphInterface $day, bool $status): ParagraphInterface {
    $activity = Paragraph::create([
      'type' => 'edw_daily_schedule_item',
      'status' => $status,
      'parent_type' => 'paragraph',
      'parent_id' => $day->id(),
      'parent_field_name' => 'field_paragraphs',
    ]);
    $activity->save();

    return $activity;
  }

}
