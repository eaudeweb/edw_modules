<?php

namespace Drupal\edw_event_daily_schedule\Services;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * The Meeting service class.
 */
class ScheduleService {

  /**
   * The paragraph storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $paragraphStorage;

  /**
   * The MeetingService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->paragraphStorage = $entityTypeManager->getStorage('paragraph');
  }

  /**
   * Get all schedule items for a parent.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $parent
   *   The schedule day.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The schedule items.
   */
  public function getScheduleItems(Paragraph $parent) {
    if ($parent->isNew()) {
      $scheduleItems = [];
      foreach ($parent->get('field_paragraphs') as $paragraph) {
        /** @var Paragraph $paragraphEntity */
        $paragraphEntity = $paragraph->entity;
        if ($paragraphEntity->bundle() == 'edw_daily_schedule_item' && $paragraphEntity->isPublished()) {
          $scheduleItems[] = $paragraphEntity;
        }
      }
      return $scheduleItems;
    }
    return $this->paragraphStorage->loadByProperties([
      'parent_id' => $parent->id(),
      'type' => 'edw_daily_schedule_item',
      'status' => 1,
    ]);
  }

  /**
   * Update status for a schedule paragraph.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   The paragraph.
   * @param bool $status
   *   Status, TRUE for Publish, FALSE for Unpublished.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   The paragraph.
   */
  public function updateParagraphStatus(Paragraph &$paragraph, bool $status) {
    return ($status)
      ? $paragraph->setPublished()
      : $paragraph->setUnpublished();
  }

}
