<?php

namespace Drupal\edw_event\EventSubscriber;

use Drupal\entity_clone\Event\EntityCloneEvent;
use Drupal\entity_clone\Event\EntityCloneEvents;
use Drupal\edw_event\Services\MeetingService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Meeting Clone event subscriber.
 */
class MeetingCloneSubscriber implements EventSubscriberInterface {

  /**
   * The custom meeting service.
   *
   * @var \Drupal\edw_event\Services\MeetingService
   */
  protected $meetingService;

  /**
   * Class constructor, injects the services.
   *
   * @param \Drupal\edw_event\Services\MeetingService $meetingService
   *   The custom meeting service.
   */
  public function __construct(MeetingService $meetingService) {
    $this->meetingService = $meetingService;
  }

  /**
   * Clone meetings section when a meeting is cloned.
   *
   * Dispatched after an entity is cloned and saved.
   *
   * @see \Drupal\entity_clone\Event\EntityCloneEvents::POST_CLONE
   */
  public function postCloneMeetingSections(EntityCloneEvent $event): void {
    $properties = $event->getProperties();
    if (!isset($properties['meeting_sections']) || !$properties['meeting_sections']) {
      return;
    }
    $original = $event->getEntity();
    // Get all sections from the original meeting.
    $originalSections = $this->meetingService->getAllMeetingSections($original, FALSE);
    // Get the cloned meeting.
    $newEntity = $event->getClonedEntity();
    /** @var \Drupal\node\Entity\Node $section */
    foreach ($originalSections as $section) {
      // Clone the section and set the new meeting.
      $newSection = $section->createDuplicate();
      $newSection->set('field_event', $newEntity->id());
      // Unpublished the section by user selection.
      if (isset($properties[$section->id()]) && !$properties[$section->id()]) {
        $newSection->setUnpublished();
      }
      $newSection->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[EntityCloneEvents::POST_CLONE][] = ['postCloneMeetingSections'];
    return $events;
  }

}
