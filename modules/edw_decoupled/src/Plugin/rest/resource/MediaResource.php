<?php

namespace Drupal\edw_decoupled\Plugin\rest\resource;

use Drupal\rest\Plugin\rest\resource\EntityResource;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Represents nodes as resources.
 *
 * @RestResource (
 *   id = "edw_media",
 *   label = @Translation("EDW: Media"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/media/{media}",
 *   }
 * )
 *
 * @see \Drupal\rest\Plugin\rest\resource\EntityResource
 */
class MediaResource extends EntityResource {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin_definition['entity_type'] = 'media';
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
