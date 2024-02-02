<?php

namespace Drupal\edw_event\Services;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

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
   * The MeetingService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->nodeStorage = $entityTypeManager->getStorage('node');
  }

  /**
   * Get all event sections for a meeting.
   *
   * @param \Drupal\Core\Entity\EntityInterface $meeting
   *   The meeting.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The meeting sections.
   */
  public function getAllMeetingSections(EntityInterface $meeting) {
    return $this->nodeStorage->loadByProperties([
      'type' => 'event_section',
      'field_event' => $meeting->id(),
    ]);
  }

}
