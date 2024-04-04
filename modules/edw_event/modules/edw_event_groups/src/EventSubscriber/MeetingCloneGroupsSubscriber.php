<?php

namespace Drupal\edw_event_groups\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_clone\Event\EntityCloneEvent;
use Drupal\entity_clone\Event\EntityCloneEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Meeting Clone event subscriber.
 */
class MeetingCloneGroupsSubscriber implements EventSubscriberInterface {

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private EntityStorageInterface $groupStorage;

  /**
   * Class constructor, injects the services.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->groupStorage = $entityTypeManager->getStorage('group');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[EntityCloneEvents::POST_CLONE][] = ['postCloneMeetingGroups'];
    return $events;
  }

  /**
   * Clone meetings groups when a meeting is cloned.
   *
   * Dispatched after an entity is cloned and saved.
   *
   * @see \Drupal\entity_clone\Event\EntityCloneEvents::POST_CLONE
   */
  public function postCloneMeetingGroups(EntityCloneEvent $event): void {
    $properties = $event->getProperties();
    if (!isset($properties['meeting_groups']) || !$properties['meeting_groups']) {
      return;
    }
    $originalMeeting = $event->getEntity();
    $meetingGroups = $this->getAllMeetingGroups($originalMeeting);
    $newMeeting = $event->getClonedEntity();
    /** @var \Drupal\group\Entity\GroupInterface $meetingGroup */
    foreach ($meetingGroups as $meetingGroup) {
      // Clone the meeting group.
      $newGroup = $meetingGroup->createDuplicate();
      $newGroup->set('field_event', $newMeeting->id());
      $label = 'Cloned - ' . $newGroup->label();
      $newGroup->set('label', $label);
      $newGroup->save();
    }
  }

  /**
   * Loads all groups for a meeting.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The meeting.
   *
   * @return array|\Drupal\Core\Entity\EntityInterface[]
   *   List of meeting groups.
   */
  protected function getAllMeetingGroups(EntityInterface $entity) {
    if ($entity->bundle() != 'event') {
      return [];
    }

    return $this->groupStorage->loadByProperties([
      'type' => 'event',
      'field_event' => $entity->id(),
    ]);
  }

}
