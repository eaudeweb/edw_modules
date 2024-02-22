<?php

namespace Drupal\edw_event_agenda\Services;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * The Meeting service class.
 */
class MeetingAgendaService {

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
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
  }

  /**
   * Get agenda for a meeting.
   *
   * @param \Drupal\node\NodeInterface $meeting
   *   The meeting.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Agenda for a meeting.
   */
  public function getAllMeetingAgendas(NodeInterface $meeting) {
    return $this->termStorage->loadByProperties([
      'vid' => 'event_agendas',
      'field_event' => $meeting->id(),
    ]);
  }

}
