<?php

namespace Drupal\edw_event_agenda\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_clone\Event\EntityCloneEvent;
use Drupal\entity_clone\Event\EntityCloneEvents;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Meeting Clone event subscriber.
 */
class MeetingAgendaCloneSubscriber implements EventSubscriberInterface {

  /**
   * The term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * Class constructor, injects the services.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
  }

  /**
   * Clone meetings agenda when a meeting is cloned.
   *
   * Dispatched after an entity is cloned and saved.
   *
   * @see \Drupal\entity_clone\Event\EntityCloneEvents::POST_CLONE
   */
  public function postCloneMeetingAgenda(EntityCloneEvent $event): void {
    $entity = $event->getEntity();
    if (!$entity instanceof NodeInterface || $entity->bundle() != 'event') {
      return;
    }
    $properties = $event->getProperties();
    $agendas = array_filter($properties['referenced']['meeting_agenda'], function ($value) {
      return $value == TRUE;
    });
    $agendas = array_keys($agendas);
    $newMeeting = $event->getClonedEntity();
    $agendas = $this->termStorage->loadMultiple($agendas);
    /** @var \Drupal\taxonomy\Entity\Term $agenda */
    foreach ($agendas as $agenda) {
      // Clone agenda and set the new meeting.
      $newAgenda = $agenda->createDuplicate();
      $newAgenda->set('field_event', $newMeeting->id());
      $newAgenda->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[EntityCloneEvents::POST_CLONE][] = ['postCloneMeetingAgenda'];
    return $events;
  }

}
