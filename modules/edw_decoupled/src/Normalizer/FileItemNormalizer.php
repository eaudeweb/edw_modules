<?php

namespace Drupal\edw_decoupled\Normalizer;

use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\serialization\Normalizer\EntityReferenceFieldItemNormalizer as EntityReferenceFieldItemNormalizerBase;

/**
 * Adds the entity label to embedded entities.
 */
class FileItemNormalizer extends EntityReferenceFieldItemNormalizerBase {

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|NULL {
    /** @var \Drupal\file\Plugin\Field\FieldType\FileItem $field_item */
    $values = parent::normalize($field_item, $format, $context);
    $file = $field_item->entity;
    if (!$file instanceof FileInterface || empty($file->id())) {
      return $values;
    }
    $values['filename'] = $file->getFilename();
    $values['filemime'] = $file->getMimeType();
    $values['filesize'] = $file->getSize();
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [
      FileItem::class => TRUE,
    ];
  }

}
