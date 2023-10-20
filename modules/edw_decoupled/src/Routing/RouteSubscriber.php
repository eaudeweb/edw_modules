<?php

namespace Drupal\edw_decoupled\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * The route subscriber.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    $route = $collection->get('decoupled_router.path_translation');
    if (!empty($route)) {
      $route->setPath('api/v1/router');
    }
    $route = $collection->get('search_api_decoupled.search_results');
    if (!empty($route)) {
      $route->setPath('/api/v1/search/{search_api_endpoint}');
    }
    $route = $collection->get('oauth2_token.token');
    if (!empty($route)) {
      $route->setPath('/api/v1/oauth/token');
    }
    $route = $collection->get('simple_oauth.userinfo');
    if (!empty($route)) {
      $route->setPath('/api/v1/oauth/userinfo');
    }
  }

}
