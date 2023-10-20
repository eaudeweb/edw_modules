<?php

namespace Drupal\edw_decoupled\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Holds functionalities regarding the API for the users.
 */
class UserApiController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->userStorage = $instance->entityTypeManager->getStorage('user');
    return $instance;
  }

  /**
   * Returns current user account information.
   */
  public function userInfo() {
    if ($this->currentUser()->isAnonymous()) {
      throw new AccessDeniedHttpException();
    }

    $userObject = $this->userStorage->load($this->currentUser()->id());
    return new JsonResponse($userObject->toArray());
  }

}
