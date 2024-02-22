<?php

namespace Drupal\edw_event\Services;

use Drupal\Core\Entity\EntityInterface;
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
  protected $database;

  /**
   * The MeetingService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->database = \Drupal::database();
  }

  /**
   * Get all event sections for a meeting.
   *
   * @param \Drupal\node\NodeInterface $meeting
   *   The meeting.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The meeting sections.
   */
  public function getAllMeetingSections(NodeInterface $meeting) {
    return $this->nodeStorage->loadByProperties([
      'type' => 'event_section',
      'field_event' => $meeting->id(),
    ]);
  }

  /**
   * Get the weight of a given meeting section.
   *
   * @param \Drupal\Core\Entity\EntityInterface $meetingSection
   *   The meeting section node.
   *
   * @return int|mixed
   *   Returns the weight of the meeting section from the database.
   */
  function getMeetingSectionWeight(EntityInterface $meetingSection) {
    $query = \Drupal::database()->select('draggableviews_structure', 'd')
      ->fields('d', ['weight'])
      ->condition('view_name', 'meeting_sections')
      ->condition('view_display', 'order_meeting_sections')
      ->condition('entity_id', $meetingSection->id())
      ->execute()
      ->fetchCol();
    return $query[0] ?? 0;
  }

  /**
   * Build the URL to the first meeting section of a given meeting.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The meeting entity.
   *
   * @return string
   *   The URL of the first meeting section or empty string.
   */
  public function getMeetingUrl(NodeInterface $entity) {
    $meetingSections = $this->getAllMeetingSections($entity);
    $sortedSections = [];
    foreach ($meetingSections as $meetingSection) {
      $weight = $this->getMeetingSectionWeight($meetingSection);
      $sortedSections[$meetingSection->toUrl()->toString()] = intval($weight);
    }

    asort($sortedSections);
    return key($sortedSections);
  }


}
