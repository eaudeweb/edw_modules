<?php

namespace Drupal\Tests\edw_event\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_event\Traits\EventTestTrait;
use Drupal\Tests\group\Traits\GroupTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\entity_clone\Event\EntityCloneEvent;
use Drupal\entity_clone\Event\EntityCloneEvents;

/**
 * Tests carrying a meeting's access groups over when it is cloned.
 *
 * The sixth session of a body has the same shape of access as the fifth, but
 * not the same people: its restricted groups have to be fresh, so that adding
 * someone to the new meeting does not also let them into the old one's
 * documents. Groups shared across the whole site — "all participants" — are
 * the exception and must be reused rather than duplicated.
 *
 * @group edw_event
 */
class MeetingCloneGroupsTest extends KernelTestBase {

  use EventTestTrait;
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
    'taxonomy',
    'file',
    'image',
    'media',
    'entity',
    'flexible_permissions',
    'group',
    'entity_reference_revisions',
    'paragraphs',
    'entity_clone',
    'edw_event',
    'edw_event_groups',
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
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['user', 'group']);

    $this->setUpCurrentUser();
    $this->createMeetingContentTypes();

    $this->createGroupType(['id' => 'event', 'label' => 'Event']);
    $this->createField('group', 'event', 'field_event', 'entity_reference', ['target_type' => 'node']);
    $this->createField('group', 'event', 'field_access', 'string');
    $this->createField('node', 'event_section', 'field_groups', 'entity_reference', [
      'target_type' => 'group',
    ], -1);
    $this->createField('node', 'event_section', 'field_content', 'entity_reference_revisions', [
      'target_type' => 'paragraph',
    ], -1);
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Tests that a restricted group is copied rather than shared.
   */
  public function testRestrictedGroupIsCopiedRatherThanShared(): void {
    $original = $this->createGroup(['type' => 'event', 'label' => 'Bureau', 'field_access' => 'restricted']);
    $meeting = $this->createMeeting('Sixth meeting');
    $section = $this->createMeetingSection($meeting, 'In-session', 'in_session');
    $section->set('field_groups', [$original->id()])->save();

    $this->dispatchClone($this->createMeeting('Fifth meeting'), $meeting);

    $groups = $this->reloadSection($section)->get('field_groups')->referencedEntities();
    $this->assertCount(1, $groups);
    $this->assertNotEquals($original->id(), $groups[0]->id());
    $this->assertSame('Cloned - Bureau', $groups[0]->label());
  }

  /**
   * Tests that the copied group belongs to the new meeting.
   */
  public function testTheCopiedGroupBelongsToTheNewMeeting(): void {
    $original = $this->createGroup(['type' => 'event', 'label' => 'Bureau', 'field_access' => 'restricted']);
    $meeting = $this->createMeeting('Sixth meeting');
    $section = $this->createMeetingSection($meeting, 'In-session', 'in_session');
    $section->set('field_groups', [$original->id()])->save();

    $this->dispatchClone($this->createMeeting('Fifth meeting'), $meeting);

    $groups = $this->reloadSection($section)->get('field_groups')->referencedEntities();
    $this->assertEquals($meeting->id(), $groups[0]->get('field_event')->target_id);
  }

  /**
   * Tests that a site-wide group is reused, not duplicated.
   *
   * @see hook_global_access_groups()
   */
  public function testSiteWideGroupIsReused(): void {
    \Drupal::service('module_installer')->install(['edw_event_test']);

    $shared = $this->createGroup(['type' => 'event', 'label' => 'All participants', 'field_access' => 'public']);
    $meeting = $this->createMeeting('Sixth meeting');
    $section = $this->createMeetingSection($meeting, 'In-session', 'in_session');
    $section->set('field_groups', [$shared->id()])->save();

    $this->dispatchClone($this->createMeeting('Fifth meeting'), $meeting);

    $groups = $this->reloadSection($section)->get('field_groups')->referencedEntities();
    $this->assertCount(1, $groups);
    $this->assertEquals($shared->id(), $groups[0]->id());
    $this->assertSame('All participants', $groups[0]->label());
  }

  /**
   * Tests that a group used by two sections is copied only once.
   *
   * Copying per section would split one access list into several, so people
   * added to the new meeting's Bureau would only reach one of its sections.
   */
  public function testGroupUsedByTwoSectionsIsCopiedOnlyOnce(): void {
    $original = $this->createGroup(['type' => 'event', 'label' => 'Bureau', 'field_access' => 'restricted']);
    $meeting = $this->createMeeting('Sixth meeting');
    $first = $this->createMeetingSection($meeting, 'In-session', 'in_session');
    $first->set('field_groups', [$original->id()])->save();
    $second = $this->createMeetingSection($meeting, 'Post-session', 'post_session');
    $second->set('field_groups', [$original->id()])->save();

    $this->dispatchClone($this->createMeeting('Fifth meeting'), $meeting);

    $firstGroups = $this->reloadSection($first)->get('field_groups')->referencedEntities();
    $secondGroups = $this->reloadSection($second)->get('field_groups')->referencedEntities();

    $this->assertEquals($firstGroups[0]->id(), $secondGroups[0]->id());
    $this->assertCount(2, \Drupal::entityTypeManager()->getStorage('group')->loadMultiple());
  }

  /**
   * Tests that the groups are left alone when the box is unticked.
   */
  public function testTheGroupsAreLeftAloneWhenNotAskedFor(): void {
    $original = $this->createGroup(['type' => 'event', 'label' => 'Bureau', 'field_access' => 'restricted']);
    $meeting = $this->createMeeting('Sixth meeting');
    $section = $this->createMeetingSection($meeting, 'In-session', 'in_session');
    $section->set('field_groups', [$original->id()])->save();

    $this->dispatchClone($this->createMeeting('Fifth meeting'), $meeting, ['meeting_groups' => 0]);

    $groups = $this->reloadSection($section)->get('field_groups')->referencedEntities();
    $this->assertEquals($original->id(), $groups[0]->id());
  }

  /**
   * Tests that deleting a group removes it from the sections using it.
   *
   * A reference to a deleted group would otherwise sit in field_groups and be
   * written as an access grant for a group that no longer exists.
   */
  public function testDeletingGroupRemovesItFromTheSectionsUsingIt(): void {
    $doomed = $this->createGroup(['type' => 'event', 'label' => 'Bureau', 'field_access' => 'restricted']);
    $kept = $this->createGroup(['type' => 'event', 'label' => 'Plenary', 'field_access' => 'restricted']);
    $meeting = $this->createMeeting('Sixth meeting');
    $section = $this->createMeetingSection($meeting, 'In-session', 'in_session');
    $section->set('field_groups', [$doomed->id(), $kept->id()])->save();

    $doomed->delete();

    $groups = $this->reloadSection($section)->get('field_groups')->getValue();
    $this->assertSame([$kept->id()], array_column($groups, 'target_id'));
  }

  /**
   * Tests that deleting a group leaves other sections untouched.
   */
  public function testDeletingGroupLeavesOtherSectionsUntouched(): void {
    $doomed = $this->createGroup(['type' => 'event', 'label' => 'Bureau', 'field_access' => 'restricted']);
    $other = $this->createGroup(['type' => 'event', 'label' => 'Plenary', 'field_access' => 'restricted']);
    $meeting = $this->createMeeting('Sixth meeting');
    $mine = $this->createMeetingSection($meeting, 'In-session', 'in_session');
    $mine->set('field_groups', [$doomed->id()])->save();
    $theirs = $this->createMeetingSection($meeting, 'Post-session', 'post_session');
    $theirs->set('field_groups', [$other->id()])->save();

    $doomed->delete();

    $this->assertSame(
      [$other->id()],
      array_column($this->reloadSection($theirs)->get('field_groups')->getValue(), 'target_id')
    );
  }

  /**
   * Dispatches the post-clone event the clone form would have raised.
   *
   * @param \Drupal\node\NodeInterface $original
   *   The meeting that was cloned.
   * @param \Drupal\node\NodeInterface $clone
   *   The new meeting.
   * @param array $properties
   *   The values submitted on the clone form.
   */
  protected function dispatchClone($original, $clone, array $properties = ['meeting_groups' => 1]): void {
    \Drupal::service('event_dispatcher')->dispatch(
      new EntityCloneEvent($original, $clone, $properties),
      EntityCloneEvents::POST_CLONE
    );
  }

  /**
   * Reloads a section from storage.
   *
   * @param \Drupal\node\NodeInterface $section
   *   The section to reload.
   *
   * @return \Drupal\node\NodeInterface
   *   The freshly loaded section.
   */
  protected function reloadSection($section) {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$section->id()]);

    return $storage->load($section->id());
  }

}
