<?php

namespace Drupal\Tests\edw_event\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_event\Traits\EventTestTrait;
use Drupal\entity_clone\Event\EntityCloneEvent;
use Drupal\entity_clone\Event\EntityCloneEvents;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;

/**
 * Tests carrying a meeting's structure over when it is cloned.
 *
 * Meetings repeat: the sixth session of a body has the same sections and the
 * same agenda as the fifth, with new content. So cloning a meeting has to
 * bring its sections and agenda items along, re-pointed at the new meeting —
 * and it must deep-copy the paragraphs inside each section, or the two
 * meetings would share one set of content and editing either would change
 * both.
 *
 * @group edw_event
 */
class MeetingCloneTest extends KernelTestBase {

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
    'draggableviews',
    'entity_reference_revisions',
    'paragraphs',
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
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);

    $this->createMeetingContentTypes();
    $this->createAgendaVocabulary();

    ParagraphsType::create(['id' => 'edw_text', 'label' => 'Text'])->save();
    $this->createField('paragraph', 'edw_text', 'field_body', 'string');
    $this->createField('node', 'event_section', 'field_content', 'entity_reference_revisions', [
      'target_type' => 'paragraph',
    ], -1);
  }

  /**
   * Tests that a cloned meeting gets its own copies of the sections.
   */
  public function testClonedMeetingGetsItsOwnSections(): void {
    $original = $this->createMeeting('Fifth meeting');
    $this->createMeetingSection($original, 'Pre-session', 'pre_session');
    $this->createMeetingSection($original, 'In-session', 'in_session');
    $clone = $this->createMeeting('Sixth meeting');

    $this->dispatchClone($original, $clone, ['meeting_sections' => 1]);

    $this->assertEqualsCanonicalizing(
      ['Pre-session', 'In-session'],
      array_map(fn ($section) => $section->label(), array_values($this->sectionsOf($clone)))
    );
    // The original keeps its own, unchanged.
    $this->assertCount(2, $this->sectionsOf($original));
  }

  /**
   * Tests that the sections are skipped when the box is unticked.
   *
   * The clone form offers this as a choice, so an editor starting a meeting
   * from scratch must be able to decline.
   */
  public function testTheSectionsAreSkippedWhenNotAskedFor(): void {
    $original = $this->createMeeting('Fifth meeting');
    $this->createMeetingSection($original, 'Pre-session', 'pre_session');
    $clone = $this->createMeeting('Sixth meeting');

    $this->dispatchClone($original, $clone, ['meeting_sections' => 0]);

    $this->assertCount(0, $this->sectionsOf($clone));
  }

  /**
   * Tests that each cloned section gets its own content paragraphs.
   *
   * Sharing paragraph entities between the two meetings would mean editing
   * the new meeting's page silently rewrote the old meeting's.
   */
  public function testEachClonedSectionGetsItsOwnParagraphs(): void {
    $original = $this->createMeeting('Fifth meeting');
    $section = $this->createMeetingSection($original, 'Pre-session', 'pre_session');
    $paragraph = Paragraph::create(['type' => 'edw_text', 'field_body' => 'Original text']);
    $paragraph->save();
    $section->set('field_content', [$paragraph]);
    $section->save();

    $clone = $this->createMeeting('Sixth meeting');
    $this->dispatchClone($original, $clone, ['meeting_sections' => 1]);

    $clonedSections = $this->sectionsOf($clone);
    $clonedSection = reset($clonedSections);
    $clonedParagraphs = $clonedSection->get('field_content')->referencedEntities();

    $this->assertCount(1, $clonedParagraphs);
    $clonedParagraph = reset($clonedParagraphs);
    $this->assertNotSame($paragraph->id(), $clonedParagraph->id());
    $this->assertSame('Original text', $clonedParagraph->get('field_body')->value);
  }

  /**
   * Tests that the editor chooses which sections go live.
   *
   * A cloned meeting is not announced yet, so its sections start hidden
   * unless the editor ticked them on the clone form.
   */
  public function testTheEditorChoosesWhichSectionsGoLive(): void {
    $original = $this->createMeeting('Fifth meeting');
    $live = $this->createMeetingSection($original, 'Pre-session', 'pre_session');
    $hidden = $this->createMeetingSection($original, 'In-session', 'in_session');
    $clone = $this->createMeeting('Sixth meeting');

    $this->dispatchClone($original, $clone, [
      'meeting_sections' => 1,
      $live->id() => 1,
      $hidden->id() => 0,
    ]);

    $byTitle = [];
    foreach ($this->sectionsOf($clone) as $section) {
      $byTitle[$section->label()] = $section->isPublished();
    }

    $this->assertTrue($byTitle['Pre-session']);
    $this->assertFalse($byTitle['In-session']);
  }

  /**
   * Tests that a cloned meeting gets its own agenda items.
   */
  public function testClonedMeetingGetsItsOwnAgendaItems(): void {
    $original = $this->createMeeting('Fifth meeting');
    $wanted = $this->createAgenda($original, 'Opening of the meeting');
    $clone = $this->createMeeting('Sixth meeting');

    $this->dispatchClone($original, $clone, [
      'referenced' => ['meeting_agenda' => [$wanted->id() => 1]],
    ]);

    $agendas = \Drupal::service('edw_event_agenda.meeting.agenda.service')
      ->getAllMeetingAgendas($clone, FALSE);

    $this->assertCount(1, $agendas);
    $this->assertSame('Opening of the meeting', reset($agendas)->label());
  }

  /**
   * Tests that unticked agenda items are not carried over.
   */
  public function testUntickedAgendaItemsAreNotCarriedOver(): void {
    $original = $this->createMeeting('Fifth meeting');
    $wanted = $this->createAgenda($original, 'Opening of the meeting');
    $unwanted = $this->createAgenda($original, 'Any other business');
    $clone = $this->createMeeting('Sixth meeting');

    $this->dispatchClone($original, $clone, [
      'referenced' => [
        'meeting_agenda' => [
          $wanted->id() => 1,
          $unwanted->id() => 0,
        ],
      ],
    ]);

    $agendas = \Drupal::service('edw_event_agenda.meeting.agenda.service')
      ->getAllMeetingAgendas($clone, FALSE);

    $this->assertSame(['Opening of the meeting'], array_map(fn ($a) => $a->label(), array_values($agendas)));
  }

  /**
   * Tests that cloning something other than a meeting is ignored.
   *
   * The clone event fires for every entity type on the site, so the
   * subscriber has to recognise the ones that are not its business.
   */
  public function testCloningSomethingOtherThanMeetingIsIgnored(): void {
    $original = $this->createMeeting('Fifth meeting');
    $section = $this->createMeetingSection($original, 'Pre-session', 'pre_session');
    $this->createAgenda($original, 'Opening of the meeting');

    // Clone the section, not the meeting.
    $clonedSection = $section->createDuplicate();
    $clonedSection->save();
    $this->dispatchClone($section, $clonedSection, [
      'referenced' => ['meeting_agenda' => [1 => 1]],
    ]);

    // The original meeting's agenda is untouched.
    $agendas = \Drupal::service('edw_event_agenda.meeting.agenda.service')
      ->getAllMeetingAgendas($original, FALSE);
    $this->assertCount(1, $agendas);
  }

  /**
   * Dispatches the post-clone event the clone form would have raised.
   *
   * @param \Drupal\Core\Entity\EntityInterface $original
   *   The entity that was cloned.
   * @param \Drupal\Core\Entity\EntityInterface $clone
   *   The new entity.
   * @param array $properties
   *   The values submitted on the clone form.
   */
  protected function dispatchClone($original, $clone, array $properties): void {
    \Drupal::service('event_dispatcher')->dispatch(
      new EntityCloneEvent($original, $clone, $properties),
      EntityCloneEvents::POST_CLONE
    );
  }

  /**
   * Returns the sections belonging to a meeting.
   *
   * @param \Drupal\node\NodeInterface $meeting
   *   The meeting.
   *
   * @return \Drupal\node\NodeInterface[]
   *   The section nodes.
   */
  protected function sectionsOf($meeting): array {
    return \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'event_section',
      'field_event' => $meeting->id(),
    ]);
  }

}
