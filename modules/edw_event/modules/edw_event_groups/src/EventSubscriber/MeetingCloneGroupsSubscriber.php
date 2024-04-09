<?php

namespace Drupal\edw_event_groups\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\entity_clone\Event\EntityCloneEvent;
use Drupal\entity_clone\Event\EntityCloneEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Meeting Clone event subscriber.
 */
class MeetingCloneGroupsSubscriber implements EventSubscriberInterface {

  /**
   * The group storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private EntityStorageInterface $groupStorage;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private EntityStorageInterface $nodeStorage;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private ModuleHandlerInterface $moduleHandler;

  /**
   * Class constructor, injects the services.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ModuleHandlerInterface $moduleHandler) {
    $this->groupStorage = $entityTypeManager->getStorage('group');
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    // The clone group needs to be executed after other post clone events.
    $events[EntityCloneEvents::POST_CLONE][] = [
      'postCloneMeetingGroups',
      -1000,
    ];
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
    $newMeetingGroups = [];

    /** @var \Drupal\group\Entity\GroupInterface $meetingGroup */
    foreach ($meetingGroups as $meetingGroup) {
      // Clone the meeting group.
      $newGroup = $meetingGroup->createDuplicate();
      $newGroup->set('field_event', $newMeeting->id());
      $label = 'Cloned - ' . $newGroup->label();
      $newGroup->set('label', $label);
      $newGroup->save();
      $newMeetingGroups[] = $newGroup;
    }
    $meetingSections = $this->getAllMeetingSections($newMeeting);
    foreach ($meetingSections as $meetingSection) {
      // Update field_groups on each newly created meeting section.
      $fieldAccess = $meetingSection->get('field_access')->value;
      $access = ['participants'];
      $this->moduleHandler->invokeAll('private_access_roles', [&$access]);
      if (in_array($fieldAccess, ['participants', 'production_sector'])) {
        $groups = $this->getGroups($fieldAccess, $newMeetingGroups);
        $meetingSection->set('field_groups', $groups);
        $meetingSection->save();
      }
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

  /**
   * Gets meeting sections for a meeting.
   *
   * @param $meeting
   *   The meeting.
   *
   * @return array
   *   The meeting section nodes.
   */
  protected function getAllMeetingSections($meeting) {
    return $this->nodeStorage->loadByProperties([
      'type' => 'event_section',
      'field_event' => $meeting->id(),
    ]);
  }

  /**
   * Gets groups with a given access value from a list.
   *
   * @param $accessValue
   *   The field_access value.
   *
   * @param array $meetingGroups
   *   The meeting groups.
   *
   * @return array
   *   The filtered groups ids.
   */
  private function getGroups($accessValue, array $meetingGroups) {
    $groupsIds = [];
    foreach ($meetingGroups as $group) {
      if ($group->get('field_access')->value == $accessValue) {
        $groupsIds = $group->id();
      }
    }
    return $groupsIds;
  }

}
