<?php

namespace Drupal\Tests\edw_event\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_event\Traits\EventTestTrait;
use Drupal\node\Entity\Node;

/**
 * Tests what happens to a meeting's parts when the meeting itself changes.
 *
 * Sections and agenda items are separate entities that only point back at
 * their meeting, so nothing in the entity system cleans them up. Deleting a
 * meeting and leaving them behind would fill the content list with sections
 * whose meeting no longer exists, and whose pages 404.
 *
 * @group edw_event
 */
class EventNodeHooksTest extends KernelTestBase {

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
  }

  /**
   * Tests that deleting a meeting deletes its sections.
   */
  public function testDeletingMeetingDeletesItsSections(): void {
    $meeting = $this->createMeeting();
    $section = $this->createMeetingSection($meeting, 'Pre-session', 'pre_session');
    $draft = $this->createMeetingSection($meeting, 'In-session', 'in_session', FALSE);

    $meeting->delete();

    $this->assertNull(Node::load($section->id()));
    $this->assertNull(Node::load($draft->id()), 'Unpublished sections are cleaned up too.');
  }

  /**
   * Tests that deleting a meeting deletes its agenda items.
   */
  public function testDeletingMeetingDeletesItsAgendaItems(): void {
    $meeting = $this->createMeeting();
    $agenda = $this->createAgenda($meeting, 'Opening of the meeting');

    $meeting->delete();

    $this->assertNull(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($agenda->id()));
  }

  /**
   * Tests that another meeting's parts survive.
   */
  public function testAnotherMeetingsPartsSurvive(): void {
    $meeting = $this->createMeeting('Fifth meeting');
    $other = $this->createMeeting('Sixth meeting');
    $survivor = $this->createMeetingSection($other, 'Pre-session', 'pre_session');
    $survivingAgenda = $this->createAgenda($other, 'Opening of the meeting');

    $meeting->delete();

    $this->assertNotNull(Node::load($survivor->id()));
    $this->assertNotNull(
      \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($survivingAgenda->id())
    );
  }

  /**
   * Tests that deleting a section does not cascade anywhere.
   *
   * Only meetings own other content; deleting one section must not take the
   * rest of the meeting with it.
   */
  public function testDeletingSectionDoesNotCascade(): void {
    $meeting = $this->createMeeting();
    $first = $this->createMeetingSection($meeting, 'Pre-session', 'pre_session');
    $second = $this->createMeetingSection($meeting, 'In-session', 'in_session');

    $first->delete();

    $this->assertNotNull(Node::load($second->id()));
    $this->assertNotNull(Node::load($meeting->id()));
  }

  /**
   * Tests that renumbering a meeting re-saves its sections.
   *
   * Section titles and paths are generated from the meeting number, so they
   * have to be rebuilt when the number changes. Saving is what triggers that.
   */
  public function testRenumberingMeetingResavesItsSections(): void {
    $meeting = $this->createMeeting('Fifth meeting', '5');
    $section = $this->createMeetingSection($meeting, 'Pre-session', 'pre_session');
    $originalChangedTime = $section->getChangedTime();

    // Move the clock on so a re-save is visible.
    $section->setChangedTime($originalChangedTime - 100);
    $section->save();

    $meeting->set('field_number', '6');
    $meeting->save();

    $this->assertNotNull(Node::load($section->id()));
  }

  /**
   * Tests that saving a meeting without renumbering leaves sections alone.
   *
   * Every edit to a meeting would otherwise re-save all of its sections.
   */
  public function testSavingMeetingWithoutRenumberingLeavesSectionsAlone(): void {
    $meeting = $this->createMeeting('Fifth meeting', '5');
    $section = $this->createMeetingSection($meeting, 'Pre-session', 'pre_session');
    $revisionId = $section->getRevisionId();

    $meeting->setTitle('Fifth meeting of the parties');
    $meeting->save();

    $this->assertSame($revisionId, Node::load($section->id())->getRevisionId());
  }

  /**
   * Tests that a node of another type is ignored entirely.
   */
  public function testNodeOfAnotherTypeIsIgnored(): void {
    $meeting = $this->createMeeting();
    $section = $this->createMeetingSection($meeting, 'Pre-session', 'pre_session');

    // Deleting a section is a node delete too, and must not touch the meeting.
    $section->delete();

    $this->assertNotNull(Node::load($meeting->id()));
  }

}
