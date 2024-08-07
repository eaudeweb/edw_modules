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

    $newMeeting = $event->getClonedEntity();
    $newMeetingSections = $this->getAllMeetingSections($newMeeting);
    $globalAccess = [];
    $this->moduleHandler->invokeAll('global_access_groups', [&$globalAccess]);
    $clonedGroups = [];

    foreach ($newMeetingSections as $newMeetingSection) {
      // Update field_groups on each newly created meeting section.
      $newGroups = [];
      $originalGroups = $newMeetingSection->get('field_groups')
        ->referencedEntities();
      foreach ($originalGroups as $originalGroup) {
        $fieldAccess = $originalGroup->get('field_access')->value;
        if (in_array($fieldAccess, $globalAccess)) {
          // Reuse global groups or previously cloned groups.
          $newGroups[] = $originalGroup;
          continue;
        }

        if (isset($clonedGroups[$originalGroup->id()])) {
          // Reuse previously cloned groups.
          $newGroups[] = $clonedGroups[$originalGroup->id()];
          continue;
        }

        // Clone normal groups.
        $newGroup = $originalGroup->createDuplicate();
        $newGroup->set('field_event', $newMeeting->id());
        $label = 'Cloned - ' . $newGroup->label();
        $newGroup->set('label', $label);
        $newGroup->save();
        $clonedGroups[$originalGroup->id()] = $newGroup;
        $newGroups[] = $newGroup;
      }

      $newMeetingSection->set('field_groups', $newGroups);
      $newMeetingSection->save();

      // Manually re-save the paragraphs to set the parent_id field.
      // See https://www.drupal.org/project/entity_clone/issues/3056580#comment-13118506.
      $originalEvent = $event->getEntity();
      $originalEventSections = $originalEvent->get('field_content')->referencedEntities();
      foreach ($originalEventSections as $eventSection) {
        $paragraphs = $eventSection->get('field_content')->referencedEntities();
        foreach ($paragraphs as $paragraph) {
          $paragraph->set('parent_id', $eventSection->id());
          $paragraph->save();
        }
      }
    }
  }

  /**
   * Gets meeting sections for a meeting.
   *
   * @param \Drupal\Core\Entity\EntityInterface $meeting
   *   The meeting.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The meeting section nodes.
   */
  protected function getAllMeetingSections(EntityInterface $meeting) {
    return $this->nodeStorage->loadByProperties([
      'type' => 'event_section',
      'field_event' => $meeting->id(),
    ]);
  }

}
