<?php

namespace Drupal\Tests\edw_group\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;

/**
 * Builds the meeting-and-groups fixtures the edw_group tests need.
 *
 * This module sits between two content models it does not own: the meeting
 * content types from edw_event, and the group entities from the group module.
 * The tests recreate only the fields it actually reads.
 */
trait GroupMeetingTestTrait {

  /**
   * Creates the meeting content types and the fields edw_group reads.
   */
  protected function createMeetingContentTypes(): void {
    NodeType::create(['type' => 'event', 'name' => 'Event'])->save();
    NodeType::create(['type' => 'event_section', 'name' => 'Event section'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    // The section's link back to its meeting, which decides which meeting's
    // contributors may manage it.
    $this->createField('node', 'event_section', 'field_event', 'entity_reference', [
      'target_type' => 'node',
    ]);
    // Who may view the section, and who may moderate it.
    $this->createField('node', 'event_section', 'field_groups', 'entity_reference', [
      'target_type' => 'group',
    ], -1);
    $this->createField('node', 'event_section', 'field_moderator_groups', 'entity_reference', [
      'target_type' => 'group',
    ], -1);
    // The fallback used when no group is set at all.
    $this->createField('node', 'event_section', 'field_access', 'string');
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
   * @param bool $status
   *   Whether the meeting is published.
   * @param string $title
   *   The meeting title.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved meeting.
   */
  protected function createMeeting(bool $status = TRUE, string $title = 'Fifth meeting'): NodeInterface {
    $meeting = Node::create([
      'type' => 'event',
      'title' => $title,
      'status' => $status,
    ]);
    $meeting->save();

    return $meeting;
  }

  /**
   * Creates a meeting section.
   *
   * @param \Drupal\node\NodeInterface|null $meeting
   *   The meeting the section belongs to, or NULL to leave it unset.
   * @param array $values
   *   Extra field values, such as the groups or the access level.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved section.
   */
  protected function createSection(?NodeInterface $meeting, array $values = []): NodeInterface {
    $section = Node::create([
      'type' => 'event_section',
      'title' => 'In-session documents',
      'field_event' => $meeting ? $meeting->id() : NULL,
    ] + $values);
    $section->save();

    return $section;
  }

  /**
   * Returns the grants matching a realm.
   *
   * @param array $grants
   *   The access records.
   * @param string $realm
   *   The realm to filter on.
   *
   * @return array
   *   The matching grants, re-indexed.
   */
  protected function grantsInRealm(array $grants, string $realm): array {
    return array_values(array_filter($grants, function ($grant) use ($realm) {
      return $grant['realm'] === $realm;
    }));
  }

}
