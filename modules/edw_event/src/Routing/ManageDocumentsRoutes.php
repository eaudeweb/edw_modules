<?php

namespace Drupal\edw_event\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\edw_event\Services\MeetingDocumentsService;
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
   * Constructs a new Routes object.
   *
   * @param \Drupal\edw_event\Services\MeetingDocumentsService $meeting_documents
   *    The entity field manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The meeting document service.
   */
  public function __construct(MeetingDocumentsService $meeting_documents, ModuleHandlerInterface $module_handler) {
    $this->meetingDocuments = $meeting_documents;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('edw_event.meeting.documents.service'),
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
    $phases = $this->meetingDocuments->getDocumentPhases();
    $this->moduleHandler->alter('meeting_documents_phase', $phases);
    foreach ($phases as $phase => $title) {
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

}
