<?php

namespace Drupal\edw_event\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines dynamic routes.
 *
 * Each Phase will result in a route at:
 * /node/{node}/documents/{document-phase-id}
 */
class ManageDocumentsRoutes implements ContainerInjectionInterface {

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
   * Constructs a new Routes object.
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
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('module_handler')
    );
  }

  /**
   * Returns an array of route objects.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   An array of route objects.
   */
  public function routes() {
    $routes = new RouteCollection();
    foreach ($this->getDocumentPhase() as $phase => $title) {
      $path = str_replace('_', '-', $phase);
      $route = new Route("/node/{node}/documents/{$path}");

      $defaults = [
        '_title' => $title,
        '_controller' => 'Drupal\edw_event\Controller\ManageDocumentsController::blankPage',
      ];
      $route->setDefaults($defaults);
      $route->setRequirement('node', '\d+');
      $route->setRequirement('_entity_access', 'node.update');
      $route->setOption('parameters', [
        'node' => [
          'type' => 'entity:node',
          'bundle' => ['event'],
        ],
      ]);
      $route->setOption('_admin_route', TRUE);
      $routes->add("edw_event.documents.$phase", $route);
    }

    return $routes;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Move this later in MeetingService because is called twice.
   */
  protected function getDocumentPhase() {
    $phase = [];
    $fieldName = 'field_document_phase';
    $field_definitions = $this->entityFieldManager->getFieldDefinitions('media', 'document');
    if (isset($field_definitions[$fieldName])) {
      /** @var \Drupal\field\Entity\FieldConfig $field_definition */
      $field_definition = $field_definitions[$fieldName];
      $phase = $field_definition->getFieldStorageDefinition()->getSetting('allowed_values');
    }
    $this->moduleHandler->alter('meeting_documents_phase', $phase);
    return $phase;
  }

}
