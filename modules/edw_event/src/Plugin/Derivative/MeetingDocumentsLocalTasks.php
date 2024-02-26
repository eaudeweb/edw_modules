<?php

namespace Drupal\edw_event\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\edw_event\Services\MeetingDocumentsService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates local tasks to manage documents.
 */
class MeetingDocumentsLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The meeting document service.
   *
   * @var \Drupal\edw_event\Services\MeetingDocumentsService
   */
  protected $meetingDocuments;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new MeetingDocumentsLocalTasks object.
   *
   * @param \Drupal\edw_event\Services\MeetingDocumentsService $meeting_documents
   *   The meeting document service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(MeetingDocumentsService $meeting_documents, ModuleHandlerInterface $module_handler) {
    $this->meetingDocuments = $meeting_documents;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('edw_event.meeting.documents.service'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.LongVariable)
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $tabs = $this->meetingDocuments->getDocumentPhases();
    $this->moduleHandler->alter('meeting_documents_phase', $tabs);
    $weight = 1;
    foreach ($tabs as $id => $title) {
      $this->derivatives["edw_event.documents.$id"] = [
        'route_name' => "edw_event.documents.$id",
        'title' => $title,
        'parent_id' => "edw_event.documents.settings",
        'weight' => $weight++,
      ] + $base_plugin_definition;
    }
    $this->moduleHandler->alter('meeting_documents_local_tasks', $this->derivatives);

    return $this->derivatives;
  }

}
