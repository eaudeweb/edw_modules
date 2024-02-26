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
  public function orderMeetingAgendaIds(int|string $meetingId, string $view_name, string $view_display, array $ids = []): array {
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

}
