<?php

namespace Drupal\edw_decoupled\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityStorageBase;
use http\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Holds functionalities regarding the API for content entities.
 */
class EntityApiController extends ControllerBase {

  /**
   * Returns the permissions the current user has on the provided entity.
   *
   * @param string $entityType
   *   The entity type id.
   * @param int $entityId
   *   The entity id.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response containing an array with operations available for entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getEntityAccess($entityType, $entityId) {
    if (!$this->entityTypeManager()->hasDefinition($entityType)) {
      throw new InvalidArgumentException("The entity type does not exist.");
    }
    if (!is_numeric($entityId)) {
      throw new InvalidArgumentException("The entity id needs to be numeric.");
    }

    $storage = $this->entityTypeManager()->getStorage($entityType);
    if (!$storage instanceof ContentEntityStorageBase) {
      throw new InvalidArgumentException("This entity type is not supported.");
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $storage->load($entityId);
    if (empty($entity->id()) || !$entity->access('view')) {
      throw new NotFoundHttpException("The entity does not exist.");
    }

    return new JsonResponse([
      'view' => $entity->access('view'),
      'create' => $entity->access('create'),
      'update' => $entity->access('update'),
      'delete' => $entity->access('delete'),
    ]);
  }

}
