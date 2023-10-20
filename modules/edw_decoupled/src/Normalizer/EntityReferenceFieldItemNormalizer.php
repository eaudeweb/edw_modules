<?php

namespace Drupal\edw_decoupled\Normalizer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\serialization\Normalizer\EntityReferenceFieldItemNormalizer as EntityReferenceFieldItemNormalizerBase;
use Drupal\user\UserInterface;

/**
 * Adds the entity label to embedded entities.
 */
class EntityReferenceFieldItemNormalizer extends EntityReferenceFieldItemNormalizerBase {

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|NULL {
    $values = parent::normalize($field_item, $format, $context);
    $entity = $field_item->get('entity')->getValue();
    if ($entity instanceof UserInterface) {
      // We don't want to expose users emails.
      return $values;
    }
    if ($entity instanceof EntityInterface) {
      $values['target_label'] = $entity->label();
    }
    return $values;
  }

}
