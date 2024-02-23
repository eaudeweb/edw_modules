<?php

namespace Drupal\edw_event_agenda\Services;

use Drupal\Core\Database\Connection;
use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * The Meeting service class.
 */
class MeetingAgendaService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

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
   * The MeetingService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, Connection $connection) {
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
    $this->connection = $connection;
  }

  /**
   * Get agenda for a meeting.
   *
   * @param \Drupal\node\NodeInterface $meeting
   *   The meeting.
   * @param bool $checkStatus
   *   (optional) Indicates that access check and status are required.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Agenda for a meeting.
   */
  public function getAllMeetingAgendas(NodeInterface $meeting, bool $checkStatus) {
    $ids = $this->getMeetingAgendaIds($meeting->id(), $checkStatus);
    return $this->nodeStorage->loadMultiple($ids);
  }

  /**
   * Get agenda for a meeting.
   *
   * @param int|string $meetingId
   *   The meeting id.
   * @param bool $checkStatus
   *   (optional) Indicates that access check and status are required.
   *
   * @return array
   *   Agenda for a meeting.
   */
  public function getMeetingAgendaIds(int|string $meetingId, bool $checkStatus) {
    $ids = $this->termStorage->getQuery()
      ->accessCheck($checkStatus)
      ->condition('vid', 'event_agendas')
      ->condition('field_event', $meetingId);
    if ($checkStatus) {
      $ids->condition('status', 1);
    }
    return $ids->execute();
  }

  /**
   * Get agenda with documents for a meeting.
   *
   * @param int|string $meetingId
   *   The meeting id.
   * @param string $phase
   *   Route phase (pre_session|in_session|post_session).
   * @param bool $checkStatus
   *   (optional) Indicates that access check and status are required.
   *
   * @return array
   *   Agenda for a meeting.
   */
  public function getMeetingAgendaIdsInUse(int|string $meetingId, string $phase, bool $checkStatus) {
    $query = $this->connection->select('media__field_agenda', 'agenda');
    $query->addField('agenda', 'field_agenda_target_id');
    $query->innerJoin('media__field_document_phase', 'phase', 'agenda.entity_id = phase.entity_id');
    $query->innerJoin('media__field_meetings', 'meeting', 'meeting.entity_id = agenda.entity_id');
    if ($checkStatus) {
      $query->innerJoin('taxonomy_term_field_data', 'term', 'term.tid = agenda.field_agenda_target_id');
      $query->innerJoin('media_field_data', 'document', 'document.mid = agenda.entity_id');
      $query->condition('term.status', 1);
      $query->condition('document.status', 1);
    }
    $query->condition('meeting.field_meetings_target_id', [$meetingId], 'IN');
    $query->condition('phase.field_document_phase_value', $phase);
    $query->distinct();
    return $query->execute()->fetchCol();
  }

  /**
   * Get agenda items for a meeting, ordered.
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
  public function orderMeetingAgendaIds(int|string $meetingId, string $view_name, string $view_display, array $ids = NULL): array {
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
   * Loads one or more agenda items.
   *
   * @param array $ids
   *   An array of entity IDs.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entity objects indexed by their IDs. Returns an empty array
   *   if no matching entities are found.
   */
  public function loadMultipleAgendaItems(array $ids) {
    return $this->termStorage->loadMultiple($ids);
  }

}
