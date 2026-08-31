<?php

namespace Drupal\Tests\edw_event\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;

/**
 * Builds the meeting fixtures the edw_event tests need.
 *
 * The content model lives in the site's own config rather than in this module,
 * so the tests recreate the parts of it the code actually reads — a meeting
 * ('event'), its sections ('event_section' pointing back with field_event),
 * and the agenda vocabulary. Building it here rather than installing a
 * submodule's config keeps the tests about edw_event's behaviour.
 */
trait EventTestTrait {

  /**
   * Creates the 'event' and 'event_section' content types and their fields.
   */
  protected function createMeetingContentTypes(): void {
    NodeType::create(['type' => 'event', 'name' => 'Event'])->save();
    NodeType::create(['type' => 'event_section', 'name' => 'Event section'])->save();

    // The meeting's own number, which edw_event_node_update() watches.
    $this->createField('node', 'event', 'field_number', 'string');

    // The section's back-reference to its meeting. Everything that finds a
    // meeting's sections filters on this.
    $this->createField('node', 'event_section', 'field_event', 'entity_reference', [
      'target_type' => 'node',
    ]);

    // Which phase of the meeting the section belongs to.
    $this->createField('node', 'event_section', 'field_event_section_phase', 'list_string', [
      'allowed_values' => [
        'pre_session' => 'Pre-session',
        'in_session' => 'In-session',
        'post_session' => 'Post-session',
      ],
    ]);
  }

  /**
   * Creates the 'event_agendas' vocabulary and its fields.
   */
  protected function createAgendaVocabulary(): void {
    Vocabulary::create(['vid' => 'event_agendas', 'name' => 'Event agendas'])->save();

    $this->createField('taxonomy_term', 'event_agendas', 'field_event', 'entity_reference', [
      'target_type' => 'node',
    ]);
    $this->createField('taxonomy_term', 'event_agendas', 'field_is_default_agenda', 'boolean');
  }

  /**
   * Creates a field storage and instance in one go.
   *
   * @param string $entityType
   *   The entity type to attach the field to.
   * @param string $bundle
   *   The bundle to attach the field to.
   * @param string $fieldName
   *   The field name.
   * @param string $type
   *   The field type.
   * @param array $storageSettings
   *   Storage-level settings, such as an entity reference target type.
   * @param int $cardinality
   *   The field cardinality.
   */
  protected function createField(string $entityType, string $bundle, string $fieldName, string $type, array $storageSettings = [], int $cardinality = 1): void {
    if (!FieldStorageConfig::loadByName($entityType, $fieldName)) {
      FieldStorageConfig::create([
        'entity_type' => $entityType,
        'field_name' => $fieldName,
        'type' => $type,
        'cardinality' => $cardinality,
        'settings' => $storageSettings,
      ])->save();
    }

    if (!FieldConfig::loadByName($entityType, $bundle, $fieldName)) {
      FieldConfig::create([
        'entity_type' => $entityType,
        'bundle' => $bundle,
        'field_name' => $fieldName,
        'label' => $fieldName,
      ])->save();
    }
  }

  /**
   * Creates a meeting.
   *
   * @param string $title
   *   The meeting title.
   * @param string|null $number
   *   The meeting number, which is what edw_event_node_update() compares.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved meeting.
   */
  protected function createMeeting(string $title = 'Fifth meeting', ?string $number = NULL): NodeInterface {
    $values = ['type' => 'event', 'title' => $title];
    if ($number !== NULL) {
      $values['field_number'] = $number;
    }

    $meeting = Node::create($values);
    $meeting->save();

    return $meeting;
  }

  /**
   * Creates a section belonging to a meeting.
   *
   * @param \Drupal\node\NodeInterface $meeting
   *   The meeting the section belongs to.
   * @param string $title
   *   The section title.
   * @param string|null $phase
   *   The phase, or NULL to leave it unset.
   * @param bool $status
   *   Whether the section is published.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved section.
   */
  protected function createMeetingSection(NodeInterface $meeting, string $title = 'Documents', ?string $phase = NULL, bool $status = TRUE): NodeInterface {
    $values = [
      'type' => 'event_section',
      'title' => $title,
      'field_event' => $meeting->id(),
      'status' => $status,
    ];
    if ($phase !== NULL) {
      $values['field_event_section_phase'] = $phase;
    }

    $section = Node::create($values);
    $section->save();

    return $section;
  }

  /**
   * Creates an agenda item for a meeting.
   *
   * @param \Drupal\node\NodeInterface $meeting
   *   The meeting the agenda belongs to.
   * @param string $name
   *   The agenda name.
   * @param bool $isDefault
   *   Whether this is the meeting's default agenda.
   * @param bool $status
   *   Whether the agenda is published.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   The saved agenda term.
   */
  protected function createAgenda(NodeInterface $meeting, string $name = 'Opening of the meeting', bool $isDefault = FALSE, bool $status = TRUE): TermInterface {
    $agenda = Term::create([
      'vid' => 'event_agendas',
      'name' => $name,
      'field_event' => $meeting->id(),
      'field_is_default_agenda' => $isDefault,
      'status' => $status,
    ]);
    $agenda->save();

    return $agenda;
  }

}
