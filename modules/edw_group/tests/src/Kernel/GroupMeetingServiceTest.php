<?php

namespace Drupal\Tests\edw_group\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_group\Traits\GroupMeetingTestTrait;
use Drupal\Tests\group\Traits\GroupTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\node\Entity\Node;

/**
 * Tests the link between meetings and their access groups.
 *
 * Each meeting gets a group of its own, and its sections name the groups
 * allowed to see or moderate them. This service is what everything else asks
 * about that relationship — the grants handler, the delete cascade, and the
 * clone flow — so it also has to say clearly when the site is not set up for
 * groups at all rather than failing further down.
 *
 * @coversDefaultClass \Drupal\edw_group\Services\MeetingService
 *
 * @group edw_group
 */
class GroupMeetingServiceTest extends KernelTestBase {

  use GroupMeetingTestTrait;
  use GroupTestTrait;
  use UserCreationTrait;

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
    'entity',
    'flexible_permissions',
    'group',
    'node_access_grants',
    'edw_group',
  ];

  /**
   * The service under test.
   *
   * @var \Drupal\edw_group\Services\MeetingService
   */
  protected $meetingService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['user', 'group']);

    $this->setUpCurrentUser();
    $this->createMeetingContentTypes();

    $this->meetingService = \Drupal::service('edw_group.meeting');
  }

  /**
   * Tests that a site with no event group type is reported as not set up.
   *
   * The delete cascade asks this before touching anything, because a site can
   * install edw_group without ever creating the group type it expects.
   *
   * @covers ::isAllowed
   */
  public function testSiteWithNoEventGroupTypeIsNotAllowed(): void {
    $this->assertFalse($this->meetingService->isAllowed());
  }

  /**
   * Tests that a group type without the meeting field is not enough.
   *
   * @covers ::isAllowed
   */
  public function testGroupTypeWithoutTheMeetingFieldIsNotAllowed(): void {
    $this->createGroupType(['id' => 'event', 'label' => 'Event']);

    $this->assertFalse($this->meetingService->isAllowed());
  }

  /**
   * Tests that a fully configured site is allowed.
   *
   * @covers ::isAllowed
   */
  public function testFullyConfiguredSiteIsAllowed(): void {
    $this->createEventGroupType();

    $this->assertTrue($this->meetingService->isAllowed());
  }

  /**
   * Tests that viewing a section is governed by its access groups.
   *
   * @covers ::getNodeGroups
   */
  public function testViewingSectionIsGovernedByItsAccessGroups(): void {
    $this->createEventGroupType();
    $viewer = $this->createGroup(['type' => 'event']);
    $moderator = $this->createGroup(['type' => 'event']);
    $section = $this->createSection($this->createMeeting(), [
      'field_groups' => [$viewer->id()],
      'field_moderator_groups' => [$moderator->id()],
    ]);

    $groups = $this->meetingService->getNodeGroups($section, 'view');

    $this->assertCount(1, $groups);
    $this->assertSame($viewer->id(), $groups[0]->id());
  }

  /**
   * Tests that moderating a section is governed by a separate list.
   *
   * Being able to read a section is not the same as being able to change it,
   * so the two operations read different fields.
   *
   * @covers ::getNodeGroups
   */
  public function testModeratingSectionIsGovernedBySeparateGroups(): void {
    $this->createEventGroupType();
    $viewer = $this->createGroup(['type' => 'event']);
    $moderator = $this->createGroup(['type' => 'event']);
    $section = $this->createSection($this->createMeeting(), [
      'field_groups' => [$viewer->id()],
      'field_moderator_groups' => [$moderator->id()],
    ]);

    foreach (['update', 'delete'] as $operation) {
      $groups = $this->meetingService->getNodeGroups($section, $operation);
      $this->assertCount(1, $groups, "The '$operation' operation reads the moderator groups.");
      $this->assertSame($moderator->id(), $groups[0]->id());
    }
  }

  /**
   * Tests that a group that has been deleted is skipped.
   *
   * Reference fields keep pointing at deleted entities, and a stale id must
   * not become a grant for a group that no longer exists.
   *
   * @covers ::getNodeGroups
   */
  public function testGroupThatHasBeenDeletedIsSkipped(): void {
    $this->createEventGroupType();
    $group = $this->createGroup(['type' => 'event']);
    $section = $this->createSection($this->createMeeting(), [
      'field_groups' => [$group->id()],
    ]);

    $group->delete();

    $this->assertSame([], $this->meetingService->getNodeGroups($section, 'view'));
  }

  /**
   * Tests that a section with no groups yields none.
   *
   * @covers ::getNodeGroups
   */
  public function testSectionWithNoGroupsYieldsNone(): void {
    $section = $this->createSection($this->createMeeting());

    $this->assertSame([], $this->meetingService->getNodeGroups($section, 'view'));
  }

  /**
   * Tests that content the service does not own is refused outright.
   *
   * @covers ::getNodeGroups
   */
  public function testContentTheServiceDoesNotOwnIsRefused(): void {
    $page = Node::create(['type' => 'page', 'title' => 'About us']);
    $page->save();

    $this->expectException(\InvalidArgumentException::class);
    $this->meetingService->getNodeGroups($page, 'view');
  }

  /**
   * Tests that an unknown operation is refused outright.
   *
   * Silently treating an unknown operation as 'update' would hand out the
   * moderator groups for something nobody asked about.
   *
   * @covers ::getNodeGroups
   */
  public function testAnUnknownOperationIsRefused(): void {
    $section = $this->createSection($this->createMeeting());

    $this->expectException(\InvalidArgumentException::class);
    $this->meetingService->getNodeGroups($section, 'publish');
  }

  /**
   * Tests that a meeting's own group is created and found again.
   *
   * @covers ::createGroupForMeeting
   * @covers ::getGroupsCreatedForMeeting
   */
  public function testMeetingsOwnGroupIsCreatedAndFoundAgain(): void {
    $this->createEventGroupType();
    $meeting = $this->createMeeting(TRUE, 'Fifth meeting');

    $this->meetingService->createGroupForMeeting($meeting);

    $groups = $this->meetingService->getGroupsCreatedForMeeting($meeting);
    $this->assertCount(1, $groups);
    $group = reset($groups);
    $this->assertSame('Fifth meeting', $group->label());
    $this->assertEquals($meeting->id(), $group->get('field_event')->target_id);
  }

  /**
   * Tests that another meeting's group is not returned.
   *
   * @covers ::getGroupsCreatedForMeeting
   */
  public function testAnotherMeetingsGroupIsNotReturned(): void {
    $this->createEventGroupType();
    $meeting = $this->createMeeting(TRUE, 'Fifth meeting');
    $other = $this->createMeeting(TRUE, 'Sixth meeting');

    $this->meetingService->createGroupForMeeting($meeting);
    $this->meetingService->createGroupForMeeting($other);

    $this->assertCount(1, $this->meetingService->getGroupsCreatedForMeeting($meeting));
  }

  /**
   * Tests that deleting a meeting takes its group with it.
   */
  public function testDeletingMeetingTakesItsGroupWithIt(): void {
    $this->createEventGroupType();
    $meeting = $this->createMeeting();
    $this->meetingService->createGroupForMeeting($meeting);

    $meeting->delete();

    $this->assertCount(0, \Drupal::entityTypeManager()->getStorage('group')->loadMultiple());
  }

  /**
   * Tests that a section's own groups are removed with it.
   *
   * @covers ::deleteMeetingSectionGroups
   */
  public function testSectionsOwnGroupsAreRemovedWithIt(): void {
    $this->createEventGroupType();
    $viewer = $this->createGroup(['type' => 'event', 'field_access' => 'restricted']);
    $moderator = $this->createGroup(['type' => 'event', 'field_access' => 'restricted']);
    $section = $this->createSection($this->createMeeting(), [
      'field_groups' => [$viewer->id()],
      'field_moderator_groups' => [$moderator->id()],
    ]);

    $this->meetingService->deleteMeetingSectionGroups($section);

    $storage = \Drupal::entityTypeManager()->getStorage('group');
    $this->assertNull($storage->load($viewer->id()));
    $this->assertNull($storage->load($moderator->id()));
  }

  /**
   * Tests that groups shared across the site are kept.
   *
   * Some groups — "all participants", say — are reused by every meeting, so
   * deleting one section must not take them away from the others.
   *
   * @see hook_global_access_groups()
   */
  public function testGroupsSharedAcrossTheSiteAreKept(): void {
    \Drupal::service('module_installer')->install(['edw_group_test']);
    $this->createEventGroupType();
    $meetingService = \Drupal::service('edw_group.meeting');

    $shared = $this->createGroup(['type' => 'event', 'field_access' => 'public']);
    $private = $this->createGroup(['type' => 'event', 'field_access' => 'restricted']);
    $section = $this->createSection($this->createMeeting(), [
      'field_groups' => [$shared->id(), $private->id()],
    ]);

    $meetingService->deleteMeetingSectionGroups($section);

    $storage = \Drupal::entityTypeManager()->getStorage('group');
    $this->assertNotNull($storage->load($shared->id()), 'A site-wide group survives.');
    $this->assertNull($storage->load($private->id()));
  }

  /**
   * Tests that a node which is not a section is left alone.
   *
   * @covers ::deleteMeetingSectionGroups
   */
  public function testNodeWhichIsNotSectionIsLeftAlone(): void {
    $this->createEventGroupType();
    $group = $this->createGroup(['type' => 'event']);
    $meeting = $this->createMeeting();

    $this->meetingService->deleteMeetingSectionGroups($meeting);

    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('group')->load($group->id()));
  }

  /**
   * Creates the event group type with the fields edw_group expects.
   */
  protected function createEventGroupType(): void {
    $this->createGroupType(['id' => 'event', 'label' => 'Event']);
    $this->createField('group', 'event', 'field_event', 'entity_reference', ['target_type' => 'node']);
    $this->createField('group', 'event', 'field_access', 'string');
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  }

}
