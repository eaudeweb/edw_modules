<?php

namespace Drupal\edw_decoupled\Normalizer;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem;

/**
 * Adds the country label to address field value.
 */
class EntityReferenceRevisionsItemNormalizer extends EntityReferenceFieldItemNormalizer {

  /**
   * The paragraph storage.
   *
   * @var \Drupal\Core\Entity\RevisionableStorageInterface
   */
  protected $paragraphStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityRepositoryInterface $entityRepository, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($entityRepository);
    $this->entityTypeManager = $entityTypeManager;
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $paragraphStorage */
    $paragraphStorage = $entityTypeManager->getStorage('paragraph');
    $this->paragraphStorage = $paragraphStorage;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|NULL {
    /** @var \Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem $field_item */
    $values = parent::normalize($field_item, $format, $context);
    $target = $field_item->getValue();
    if (empty($target['target_id']) || empty($target['target_revision_id'])) {
      return $values;
    }
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
    $paragraph = $this->paragraphStorage->loadRevision($target['target_revision_id']);
    $values['entity'] = json_decode($this->serializer->serialize($paragraph, 'json'), TRUE);
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [
      EntityReferenceRevisionsItem::class => TRUE,
    ];
  }

}
