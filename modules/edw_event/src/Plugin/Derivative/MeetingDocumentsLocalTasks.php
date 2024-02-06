<?php

namespace Drupal\edw_event\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates local tasks to manage documents.
 */
class MeetingDocumentsLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new MeetingDocumentsLocalTasks object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, ModuleHandlerInterface $module_handler) {
    $this->entityFieldManager = $entity_field_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.LongVariable)
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $tabs = $this->getLocalSubTasks();
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

  /**
   * {@inheritdoc}
   *
   * @todo Move this later in MeetingService because is called twice.
   */
  protected function getLocalSubTasks() {
    $tabs = [];
    $fieldName = 'field_document_phase';
    $field_definitions = $this->entityFieldManager->getFieldDefinitions('media', 'document');
    if (isset($field_definitions[$fieldName])) {
      /** @var \Drupal\field\Entity\FieldConfig $field_definition */
      $field_definition = $field_definitions[$fieldName];
      $tabs = $field_definition->getFieldStorageDefinition()->getSetting('allowed_values');
    }
    $this->moduleHandler->alter('meeting_documents_phase', $tabs);
    return $tabs;
  }

}
