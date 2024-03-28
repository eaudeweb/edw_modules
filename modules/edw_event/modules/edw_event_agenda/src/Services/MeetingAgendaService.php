<?php

namespace Drupal\edw_event_agenda\Services;

use Drupal\edw_event\Services\MeetingService;
use Drupal\node\NodeInterface;

/**
 * The Meeting service class.
 */
class MeetingAgendaService extends MeetingService {

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
    return $this->termStorage->loadMultiple($ids);
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
  public function orderMeetingAgendaIds(int|string $meetingId, string $view_name, string $view_display, array $ids = []): array {
    return parent::getOrderIds($meetingId, $view_name, $view_display, $ids);
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

  /**
   * Creates default agenda for a meeting.
   *
   * @param int $meetingId
   *   The meeting id.
   * @param string $agendaName
   *   The agenda's name.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createDefaultAgenda(int $meetingId, string $agendaName) {
    $defaultAgenda = $this->termStorage->getQuery()->accessCheck(FALSE)
      ->condition('vid', 'event_agendas')
      ->condition('field_event', $meetingId)
      ->condition('field_is_default_agenda', TRUE)
      ->execute();
    $defaultAgenda = reset($defaultAgenda);

    if (!empty($defaultAgenda)) {
      return;
    }

    // If there is a non-default agenda with that name make it default.
    $properties = [
      'vid' => 'event_agendas',
      'field_event' => $meetingId,
      'name' => $agendaName,
    ];
    $term = $this->termStorage->loadByProperties($properties);
    $term = reset($term);

    if (!empty($term)) {
      $term->set('field_is_default_agenda', TRUE);
      $term->save();
      return;
    }


    // Otherwise, create a new default agenda.
    $properties['field_is_default_agenda'] = TRUE;
    $defaultAgenda = $this->termStorage->create($properties);
    $defaultAgenda->save();
  }

}
