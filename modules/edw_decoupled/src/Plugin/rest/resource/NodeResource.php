<?php

namespace Drupal\edw_decoupled\Plugin\rest\resource;

use Drupal\rest\Plugin\rest\resource\EntityResource;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Represents nodes as resources.
 *
 * @RestResource (
 *   id = "edw_node",
 *   label = @Translation("EDW: Node"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/node/{node}",
 *   }
 * )
 *
 * @see \Drupal\rest\Plugin\rest\resource\EntityResource
 */
class NodeResource extends EntityResource {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin_definition['entity_type'] = 'node';
    return parent::create($container, $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  protected function requestMethods() {
    return [
      'GET',
    ];
  }

}
