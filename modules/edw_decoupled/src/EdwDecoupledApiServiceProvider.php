<?php

namespace Drupal\edw_decoupled;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\edw_decoupled\Authentication\Provider\SimpleOauthAuthenticationProvider;

/**
 * Modifies services related to decoupled functionalities.
 */
class EdwDecoupledApiServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('simple_oauth.authentication.simple_oauth')) {
      // Overwrite the simple oauth authentication service provider to get
      // rid of client scopes and use Drupal roles instead.
      $definition = $container->getDefinition('simple_oauth.authentication.simple_oauth');
      $definition->setClass(SimpleOauthAuthenticationProvider::class);
    }
  }

}
