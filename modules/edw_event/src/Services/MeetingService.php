<?php

namespace Drupal\edw_event\Services;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * The Meeting service class.
 */
class MeetingService {

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * The term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The MeetingService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   A database connection.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, Connection $connection) {
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
    $this->connection = $connection;
  }

  /**
   * Get all event sections for a meeting.
   *
   * @param \Drupal\node\NodeInterface $meeting
   *   The meeting.
   * @param bool $checkStatus
   *   (optional) Indicates that access check and status are required.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The meeting sections.
   */
  public function getAllMeetingSections(NodeInterface $meeting, bool $checkStatus) {
    $ids = $this->getAllMeetingSectionsIds($meeting, $checkStatus);
    return $this->nodeStorage->loadMultiple($ids);
  }

  /**
   * Get all event sections IDs for a meeting.
   *
   * @param \Drupal\node\NodeInterface $meeting
   *   The meeting.
   * @param bool $checkStatus
   *   (optional) Indicates that access check and status are required.
   *
   * @return array
   *   The meeting sections ids as array.
   */
  public function getAllMeetingSectionsIds(NodeInterface $meeting, bool $checkStatus) {
    $ids = $this->nodeStorage->getQuery()
      ->accessCheck($checkStatus)
      ->condition('type', 'event_section')
      ->condition('field_event', $meeting->id());
    if ($checkStatus) {
      $ids->condition('status', 1);
    }
    return $ids->execute();
  }

  /**
   * Loads one or more sections.
   *
   * @param array $ids
   *   An array of entity IDs.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entity objects indexed by their IDs. Returns an empty array
   *   if no matching entities are found.
   */
  public function loadMultipleSections(array $ids) {
    return $this->nodeStorage->loadMultiple($ids);
  }

  /**
   * Loads one or more terms.
   *
   * @param array $ids
   *   An array of entity IDs.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entity objects indexed by their IDs. Returns an empty array
   *   if no matching entities are found.
   */
  public function loadMultipleTerms(array $ids) {
    return $this->termStorage->loadMultiple($ids);
  }

  /**
   * Get sections for a meeting, ordered.
   *
   * @param int|string $meetingId
   *   The meeting id.
   * @param string $view_name
   *   The view name to check against.
   * @param string $view_display
   *   The ID of the active view's display.
   * @param array $ids
   *   Array with entity ids.
   *
   * @return array
   *   Array with agenda items ids ordered by weight.
   */
  public function orderMeetingSectionIds(int|string $meetingId, string $view_name, string $view_display, array $ids = []): array {
    return $this->getOrderIds($meetingId, $view_name, $view_display, $ids);
  }

  /**
   * Get order from draggableviews table.
   *
   * @param int|string $meetingId
   *   The meeting id.
   * @param string $view_name
   *   The view name to check against.
   * @param string $view_display
   *   The ID of the active view's display.
   * @param array $ids
   *   Array with entity ids.
   *
   * @return array
   *   Array with agenda items ids ordered by weight.
   */
  public function getOrderIds(int|string $meetingId, string $view_name, string $view_display, array $ids = []): array {
    $args = json_encode([$meetingId]);
    $query = $this->connection->select('draggableviews_structure', 'd')
      ->fields('d', ['entity_id'])
      ->condition('view_name', $view_name)
      ->condition('view_display', $view_display)
      ->condition('d.args', $args);
    if ($ids) {
      $query->condition('d.entity_id', $ids, 'IN');
    }
    $query->orderBy('d.weight');
    // Draggableviews doesn't store the new items unless the form is saved.
    return array_unique(array_merge_recursive($query->execute()->fetchCol(), $ids));
  }

  /**
   * Get document types in use for a phase of the meeting.
   *
   * @param int|string $meetingId
   *   The meeting id.
   * @param string $phase
   *   Route phase (pre_session|in_session|post_session).
   *
   * @return array
   *   Array with document types ids.
   */
  public function getDocumentTypesIdsInUse(int|string $meetingId, string $phase) {
    $query = $this->connection->select('media__field_document_types', 'types');
    $query->addField('types', 'field_document_types_target_id', 'type');
    $query->innerJoin('media__field_meetings', 'meeting', 'meeting.entity_id = types.entity_id');
    $query->innerJoin('media__field_document_phase', 'phase', 'types.entity_id = phase.entity_id');
    $query->condition('types.bundle', 'document');
    $query->condition('meeting.field_meetings_target_id', [$meetingId], 'IN');
    $query->condition('phase.field_document_phase_value', $phase);
    $query->innerJoin('media_field_data', 'media', 'media.mid = types.entity_id');
    $query->condition('media.status', 1);
    return $query->execute()->fetchCol();
  }

  /**
   * Get entity reference ids in use for a field, for a given phase and meeting.
   *
   * For examples, a field Entity reference "Contact groups".
   *
   * @param int|string $meetingId
   *   The meeting id.
   * @param string $phase
   *   Route phase (pre_session|in_session|post_session).
   * @param string $field_name
   *   Field machine name.
   *
   * @return array
   *   Array with ids.
   */
  public function getEntityReferenceIdsInUse(int|string $meetingId, string $phase, string $field_name) {
    $query = $this->connection->select("media__{$field_name}", 'types');
    $query->addField('types', "{$field_name}_target_id", 'type');
    $query->innerJoin('media__field_meetings', 'meeting', 'meeting.entity_id = types.entity_id');
    $query->innerJoin('media__field_document_phase', 'phase', 'types.entity_id = phase.entity_id');
    $query->condition('types.bundle', 'document');
    $query->condition('meeting.field_meetings_target_id', [$meetingId], 'IN');
    $query->condition('phase.field_document_phase_value', $phase);
    $query->innerJoin('media_field_data', 'media', 'media.mid = types.entity_id');
    $query->condition('media.status', 1);
    return $query->execute()->fetchCol();
  }

}
