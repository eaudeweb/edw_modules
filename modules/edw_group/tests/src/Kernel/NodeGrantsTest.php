<?php

namespace Drupal\Tests\edw_group\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_group\Traits\GroupMeetingTestTrait;
use Drupal\Tests\group\Traits\GroupTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\edw_group\NodeGrants;
use Drupal\node\Entity\Node;

/**
 * Tests the access records written for meetings and their sections.
 *
 * Meeting sections carry the documents a body circulates before, during and
 * after a session, and much of that is not public — a section is visible only
 * to the access groups it names. Node access grants are how that is enforced,
 * and they are written once at save time, so a wrong record silently exposes
 * or hides content until the node is saved again.
 *
 * @coversDefaultClass \Drupal\edw_group\NodeGrants
 *
 * @group edw_group
 */
class NodeGrantsTest extends KernelTestBase {

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
   * The grants handler under test.
   *
   * @var \Drupal\edw_group\NodeGrants
   */
  protected $nodeGrants;

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

    // Saving a group makes its owner a member, so there has to be one.
    $this->setUpCurrentUser();

    $this->createGroupType(['id' => 'event', 'label' => 'Event']);
    $this->createMeetingContentTypes();

    $this->nodeGrants = \Drupal::service('edw_group.node_grants');
  }

  /**
   * Tests that a meeting's contributors may view and edit it.
   *
   * The grant is keyed by the meeting's own id, which is what a contributor's
   * list of assigned meetings is matched against.
   *
   * @covers ::accessRecords
   */
  public function testMeetingsContributorsMayViewAndEditIt(): void {
    $meeting = $this->createMeeting();

    $grants = $this->grantsInRealm(
      $this->nodeGrants->accessRecords($meeting),
      NodeGrants::EDW_REALM_MEETING_CONTRIBUTORS
    );

    $this->assertCount(1, $grants);
    $this->assertEquals($meeting->id(), $grants[0]['gid']);
    $this->assertSame(1, $grants[0]['grant_view']);
    $this->assertSame(1, $grants[0]['grant_update']);
    $this->assertSame(0, $grants[0]['grant_delete'], 'Contributors may not delete the meeting itself.');
  }

  /**
   * Tests that a published meeting is visible to everyone.
   *
   * @covers ::accessRecords
   */
  public function testPublishedMeetingIsVisibleToEveryone(): void {
    $grants = $this->grantsInRealm(
      $this->nodeGrants->accessRecords($this->createMeeting(TRUE)),
      NodeGrants::GLOBAL_REALM
    );

    $this->assertCount(1, $grants);
    $this->assertSame(1, $grants[0]['grant_view']);
    $this->assertSame(0, $grants[0]['grant_update']);
  }

  /**
   * Tests that an unpublished meeting is not visible to everyone.
   *
   * @covers ::accessRecords
   */
  public function testAnUnpublishedMeetingIsNotVisibleToEveryone(): void {
    $grants = $this->grantsInRealm(
      $this->nodeGrants->accessRecords($this->createMeeting(FALSE)),
      NodeGrants::GLOBAL_REALM
    );

    $this->assertSame([], $grants);
  }

  /**
   * Tests that content managers may do anything to a section.
   *
   * @covers ::accessRecords
   */
  public function testContentManagersMayDoAnythingToSection(): void {
    $section = $this->createSection($this->createMeeting(), ['field_access' => 'public']);

    $grants = $this->grantsInRealm(
      $this->nodeGrants->accessRecords($section),
      NodeGrants::EDW_REALM_CONTENT_MANAGERS
    );

    $this->assertCount(1, $grants);
    $this->assertSame(1, $grants[0]['grant_view']);
    $this->assertSame(1, $grants[0]['grant_update']);
    $this->assertSame(1, $grants[0]['grant_delete']);
  }

  /**
   * Tests that a section is visible to each group it names.
   *
   * @covers ::accessRecords
   */
  public function testSectionIsVisibleToEachGroupItNames(): void {
    $first = $this->createGroup(['type' => 'event']);
    $second = $this->createGroup(['type' => 'event']);
    $section = $this->createSection($this->createMeeting(), [
      'field_access' => 'restricted',
      'field_groups' => [$first->id(), $second->id()],
    ]);

    $grants = $this->grantsInRealm(
      $this->nodeGrants->accessRecords($section),
      NodeGrants::EDW_VIEW_REALM
    );

    $this->assertEqualsCanonicalizing(
      [$first->id(), $second->id()],
      array_column($grants, 'gid')
    );
    foreach ($grants as $grant) {
      $this->assertSame(1, $grant['grant_view']);
      $this->assertSame(0, $grant['grant_update'], 'Viewing a section does not confer editing it.');
    }
  }

  /**
   * Tests that a section with no groups falls back to its access level.
   *
   * A published section that nobody restricted is simply public.
   *
   * @covers ::accessRecords
   */
  public function testSectionWithNoGroupsFallsBackToBeingPublic(): void {
    $section = $this->createSection($this->createMeeting(), [
      'field_access' => 'public',
      'status' => 1,
    ]);

    $grants = $this->grantsInRealm(
      $this->nodeGrants->accessRecords($section),
      NodeGrants::GLOBAL_REALM
    );

    $this->assertCount(1, $grants);
    $this->assertSame(1, $grants[0]['grant_view']);
  }

  /**
   * Tests that an unpublished section with no groups is not public.
   *
   * @covers ::accessRecords
   */
  public function testAnUnpublishedSectionWithNoGroupsIsNotPublic(): void {
    $section = $this->createSection($this->createMeeting(), [
      'field_access' => 'public',
      'status' => 0,
    ]);

    $grants = $this->grantsInRealm(
      $this->nodeGrants->accessRecords($section),
      NodeGrants::GLOBAL_REALM
    );

    $this->assertCount(1, $grants);
    $this->assertSame(0, $grants[0]['grant_view']);
  }

  /**
   * Tests that a participants-only section is closed rather than public.
   *
   * This is the important case: a section marked for participants but with no
   * group attached yet must not fall through to the public grant. It gets a
   * grant that allows nothing instead, so only the realms above it — content
   * managers, contributors — can reach it.
   *
   * @covers ::accessRecords
   */
  public function testParticipantsOnlySectionWithNoGroupsIsClosed(): void {
    $section = $this->createSection($this->createMeeting(), [
      'field_access' => 'participants',
      'status' => 1,
    ]);

    $records = $this->nodeGrants->accessRecords($section);

    $this->assertSame([], $this->grantsInRealm($records, NodeGrants::GLOBAL_REALM));
    $viewGrants = $this->grantsInRealm($records, NodeGrants::EDW_VIEW_REALM);
    $this->assertCount(1, $viewGrants);
    $this->assertSame(0, $viewGrants[0]['grant_view']);
    $this->assertSame(0, $viewGrants[0]['grant_update']);
    $this->assertSame(0, $viewGrants[0]['grant_delete']);
  }

  /**
   * Tests that a site can add its own private access levels.
   *
   * @see hook_private_access_roles()
   */
  public function testSiteCanAddItsOwnPrivateAccessLevels(): void {
    \Drupal::service('module_installer')->install(['edw_group_test']);
    $nodeGrants = \Drupal::service('edw_group.node_grants');

    $section = $this->createSection($this->createMeeting(), [
      'field_access' => 'observers',
      'status' => 1,
    ]);

    $records = $nodeGrants->accessRecords($section);

    $this->assertSame([], $this->grantsInRealm($records, NodeGrants::GLOBAL_REALM));
    $this->assertSame(0, $this->grantsInRealm($records, NodeGrants::EDW_VIEW_REALM)[0]['grant_view']);
  }

  /**
   * Tests that a section's meeting decides which contributors may manage it.
   *
   * Contributors are assigned to meetings, not to individual sections, so the
   * grant that lets them edit is keyed by the meeting id. A second grant,
   * keyed by the section id, is what lets them delete their own section.
   *
   * @covers ::accessRecords
   */
  public function testSectionsMeetingDecidesWhichContributorsMayManageIt(): void {
    $meeting = $this->createMeeting();
    $section = $this->createSection($meeting, ['field_access' => 'public']);

    $grants = $this->grantsInRealm(
      $this->nodeGrants->accessRecords($section),
      NodeGrants::EDW_REALM_MEETING_CONTRIBUTORS
    );

    $byGid = array_column($grants, NULL, 'gid');

    $this->assertArrayHasKey($meeting->id(), $byGid);
    $this->assertSame(1, $byGid[$meeting->id()]['grant_update']);
    $this->assertSame(0, $byGid[$meeting->id()]['grant_delete']);

    $this->assertArrayHasKey($section->id(), $byGid);
    $this->assertSame(1, $byGid[$section->id()]['grant_delete']);
    $this->assertSame(0, $byGid[$section->id()]['grant_update']);
  }

  /**
   * Tests that a section with no meeting gets no contributor grants.
   *
   * A section can be saved before its meeting is chosen; that must not write
   * a grant keyed by a missing meeting.
   *
   * @covers ::accessRecords
   */
  public function testSectionWithNoMeetingGetsNoContributorGrants(): void {
    $section = $this->createSection(NULL, ['field_access' => 'public']);

    $grants = $this->grantsInRealm(
      $this->nodeGrants->accessRecords($section),
      NodeGrants::EDW_REALM_MEETING_CONTRIBUTORS
    );

    $this->assertSame([], $grants);
  }

  /**
   * Tests that other content is left to Drupal's own access rules.
   *
   * Writing no records at all is what hands the decision back; writing an
   * empty-looking record would deny everything.
   *
   * @covers ::accessRecords
   */
  public function testOtherContentIsLeftToDrupalsOwnAccessRules(): void {
    $page = Node::create(['type' => 'page', 'title' => 'About us']);
    $page->save();

    $this->assertSame([], $this->nodeGrants->accessRecords($page));
  }

  /**
   * Tests that an anonymous visitor is granted nothing.
   *
   * @covers ::grants
   */
  public function testAnAnonymousVisitorIsGrantedNothing(): void {
    $anonymous = new AnonymousUserSession();

    $this->assertSame([], $this->nodeGrants->grants($anonymous, 'view'));
  }

}
