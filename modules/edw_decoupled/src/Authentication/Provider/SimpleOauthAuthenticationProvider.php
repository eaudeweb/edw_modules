<?php

namespace Drupal\edw_decoupled\Authentication\Provider;

use Drupal\simple_oauth\Authentication\Provider\SimpleOauthAuthenticationProvider as SimpleOauthAuthenticationProviderBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Authentication provider via Simple Oauth tokens.
 *
 * @phpstan-ignore-next-line (because parent class has @internal annotation)
 */
class SimpleOauthAuthenticationProvider extends SimpleOauthAuthenticationProviderBase {

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    $account = parent::authenticate($request);
    if (!empty($account->id())) {
      // The default simple oauth authentication provides uses client scopes
      // as the current user roles instead of the actual user roles so we
      // overwrite the returned TokenAuthUser with the core User object.
      $userStorage = $this->entityTypeManager->getStorage('user');
      $account = $userStorage->load($account->id());
    }
    return $account;
  }

}
