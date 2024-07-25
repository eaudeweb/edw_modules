<?php

namespace Drupal\edw_event\Routing;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines dynamic routes for In-session grouping.
 *
 * Each group type will result in a route at:
 * /node/{node}/documents/in-session/{group-by}
 */
class DocumentsInSessionRoutes extends ManageDocumentsRoutes {

  /**
   * Returns an array of route objects.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   An array of route objects.
   */
  public function routes() {
    $routes = new RouteCollection();
    $groups = [
      'agenda_item' => 'Agenda item',
      'document_type' => 'Document type',
    ];
    $this->moduleHandler->invokeAll('meeting_documents_in_session_groups', $groups);
    foreach ($groups as $groupBy => $title) {
      $path = str_replace('_', '-', $groupBy);
      $route = new Route("/node/{node}/documents/in-session/$path");

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
      $routes->add("edw_event.documents.in_session.$groupBy", $route);
    }

    return $routes;
  }

}
