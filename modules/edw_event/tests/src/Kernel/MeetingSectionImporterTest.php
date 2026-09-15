<?php

namespace Drupal\Tests\edw_event\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_event\Traits\EventTestTrait;
use Drupal\edw_event\Commands\MeetingCommands;
use Drupal\paragraphs\Entity\ParagraphsType;

/**
 * Tests creating a meeting's phase sections.
 *
 * Every meeting needs the same handful of sections — one per phase, each
 * holding a view of that phase's documents — so they are created by a drush
 * command rather than by hand. The section is only useful with the view
 * paragraph inside it, and creating a second section for a phase that already
 * has one would give the meeting two competing pages at the same place.
 *
 * @coversDefaultClass \Drupal\edw_event\Services\Importer\MeetingSectionImporter
 *
 * @group edw_event
 */
class MeetingSectionImporterTest extends KernelTestBase {

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
    'views',
    'file',
    'entity_reference_revisions',
    'paragraphs',
    'viewsreference',
    'entity_clone',
    'edw_event',
  ];

  /**
   * The importer under test.
   *
   * @var \Drupal\edw_event\Services\Importer\MeetingSectionImporter
   */
  protected $importer;

  /**
   * The meeting the sections are created for.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $meeting;

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

    // The section holds its documents view in a paragraph.
    ParagraphsType::create(['id' => 'edw_view', 'label' => 'View'])->save();
    $this->createField('paragraph', 'edw_view', 'field_view', 'viewsreference', [
      'target_type' => 'view',
    ]);
    $this->createField('node', 'event_section', 'field_content', 'entity_reference_revisions', [
      'target_type' => 'paragraph',
    ], -1);

    $this->importer = \Drupal::service('edw_event.meeting_section.importer');
    $this->meeting = $this->createMeeting();
  }

  /**
   * Tests that a section is created for the meeting and phase.
   *
   * @covers ::createMeetingSection
   */
  public function testSectionIsCreatedForTheMeetingAndPhase(): void {
    $section = $this->importer
      ->createMeetingSection($this->meeting->id(), 'in_session', 'In-session documents');

    $this->assertNotNull($section);
    $this->assertSame('event_section', $section->bundle());
    $this->assertSame('In-session documents', $section->label());
    $this->assertSame($this->meeting->id(), $section->get('field_event')->target_id);
    $this->assertSame('in_session', $section->get('field_event_section_phase')->value);
    $this->assertTrue($section->isPublished());
  }

  /**
   * Tests that the section is given a documents view to render.
   *
   * An empty section is a blank admin page; the paragraph is what makes it
   * show the phase's documents.
   *
   * @covers ::createMeetingSection
   * @covers ::createViewReferenceParagraph
   */
  public function testTheSectionIsGivenDocumentsViewToRender(): void {
    $section = $this->importer
      ->createMeetingSection($this->meeting->id(), 'in_session', 'In-session documents');

    $paragraphs = $section->get('field_content')->referencedEntities();

    $this->assertCount(1, $paragraphs);
    $paragraph = reset($paragraphs);
    $this->assertSame('edw_view', $paragraph->bundle());
    $this->assertSame('documents', $paragraph->get('field_view')->target_id);
    $this->assertSame('by_phase', $paragraph->get('field_view')->display_id);
  }

  /**
   * Tests that a section can be created unpublished.
   *
   * Sections are built ahead of a meeting and only revealed when the phase
   * begins.
   *
   * @covers ::createMeetingSection
   */
  public function testSectionCanBeCreatedUnpublished(): void {
    $section = $this->importer
      ->createMeetingSection($this->meeting->id(), 'pre_session', 'Pre-session', FALSE);

    $this->assertFalse($section->isPublished());
  }

  /**
   * Tests that a phase is not given a second section.
   *
   * @covers ::createMeetingSection
   */
  public function testPhaseIsNotGivenSecondSection(): void {
    $this->importer->createMeetingSection($this->meeting->id(), 'in_session', 'In-session');

    $second = $this->importer
      ->createMeetingSection($this->meeting->id(), 'in_session', 'In-session again');

    $this->assertNull($second);
    $this->assertCount(1, $this->sectionsOf($this->meeting->id()));
  }

  /**
   * Tests that the other phases of the same meeting are unaffected.
   *
   * @covers ::createMeetingSection
   */
  public function testEachPhaseOfMeetingGetsItsOwnSection(): void {
    $this->importer->createMeetingSection($this->meeting->id(), 'pre_session', 'Pre-session');
    $this->importer->createMeetingSection($this->meeting->id(), 'in_session', 'In-session');
    $this->importer->createMeetingSection($this->meeting->id(), 'post_session', 'Post-session');

    $this->assertCount(3, $this->sectionsOf($this->meeting->id()));
  }

  /**
   * Tests that another meeting may use the same phase.
   *
   * The uniqueness is per meeting: every meeting has an in-session section.
   *
   * @covers ::createMeetingSection
   */
  public function testAnotherMeetingMayUseTheSamePhase(): void {
    $other = $this->createMeeting('Sixth meeting');

    $this->importer->createMeetingSection($this->meeting->id(), 'in_session', 'In-session');
    $second = $this->importer->createMeetingSection($other->id(), 'in_session', 'In-session');

    $this->assertNotNull($second);
    $this->assertSame($other->id(), $second->get('field_event')->target_id);
  }

  /**
   * Tests that an invalid phase is refused rather than stored.
   *
   * The phase arrives as a bare string from the command line, so a typo is
   * the expected failure. Storing it would produce a section that no phase
   * tab ever shows.
   *
   * @covers ::createMeetingSection
   */
  public function testAnInvalidPhaseIsRefused(): void {
    $section = $this->importer
      ->createMeetingSection($this->meeting->id(), 'mid_session', 'Mid-session');

    $this->assertNull($section);
    $this->assertCount(0, $this->sectionsOf($this->meeting->id()));
  }

  /**
   * Tests that the constraint check can be waived deliberately.
   *
   * Sites that add phases of their own may need to create a section before
   * the field's allowed values catch up.
   *
   * @covers ::createMeetingSection
   */
  public function testTheConstraintCheckCanBeWaived(): void {
    $section = $this->importer
      ->createMeetingSection($this->meeting->id(), 'mid_session', 'Mid-session', TRUE, FALSE);

    $this->assertNotNull($section);
    $this->assertSame('mid_session', $section->get('field_event_section_phase')->value);
    $this->assertCount(1, $this->sectionsOf($this->meeting->id()));
  }

  /**
   * Tests that the drush command creates the section.
   *
   * @covers \Drupal\edw_event\Commands\MeetingCommands::createMeetingSection
   */
  public function testTheDrushCommandCreatesTheSection(): void {
    $commands = new MeetingCommands($this->importer);

    $commands->createMeetingSection((int) $this->meeting->id(), 'in_session', 'In-session', TRUE);

    $sections = $this->sectionsOf($this->meeting->id());
    $this->assertCount(1, $sections);
    $this->assertSame('In-session', reset($sections)->label());
  }

  /**
   * Returns the sections belonging to a meeting.
   *
   * @param string|int $meetingId
   *   The meeting id.
   *
   * @return \Drupal\node\NodeInterface[]
   *   The section nodes.
   */
  protected function sectionsOf($meetingId): array {
    return \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'event_section',
      'field_event' => $meetingId,
    ]);
  }

}
