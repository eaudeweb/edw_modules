<?php

namespace Drupal\edw_event\Services;

use Drupal\backup_migrate\Core\Translation\TranslatableTrait;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\TermInterface;

/**
 * A service class for document functionality related to meeting.
 */
class MeetingDocumentsService {

  use TranslatableTrait;

  const FIELD_DOCUMENT_PHASE = 'field_document_phase';

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The MeetingDocumentsService constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager) {
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentPhases() {
    $phases = [];
    $field_definitions = $this->entityFieldManager->getFieldDefinitions('media', 'document');
    if (isset($field_definitions[self::FIELD_DOCUMENT_PHASE])) {
      /** @var \Drupal\field\Entity\FieldConfig $field_definition */
      $field_definition = $field_definitions[self::FIELD_DOCUMENT_PHASE];
      $phases = $field_definition->getFieldStorageDefinition()->getSetting('allowed_values');
    }
    return $phases;
  }

  /**
   * Append a modal dialog.
   *
   * @param \Drupal\taxonomy\TermInterface $agenda
   *   The agenda selected for the document.
   * @param string $phase
   *   Route phase (pre_session|in_session|post_session).
   *
   * @return array
   *   Modal dialog.
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function addDocumentModal(TermInterface $agenda, string $phase) {
    return [
      '#type' => 'link',
      '#title' => $this->t('Add new document'),
      '#url' => Url::fromRoute('entity.media.add_form', [
        'media_type' => 'document',
        'nid' => $agenda->field_event->entity->id(),
        'field_agenda' => $agenda->id(),
        'field_document_phase' => $phase,
      ]),
      '#attributes' => [
        'class' => ['button button--action', 'button--primary', 'use-ajax'],
        'data-dialog-type' => 'dialog',
        'data-dialog-options' => Json::encode([
          'width' => '60%',
          'dialogClass' => 'views-ui-dialog js-views-ui-dialog',
        ]),
      ],
    ];
  }

  /**
   * Append a modal dialog for a given url.
   *
   * @param \Drupal\Core\Url $url
   *   The agenda selected for the document.
   *
   * @return array
   *   Modal dialog.
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function addButtonModal(Url $url) {
    return [
      '#type' => 'link',
      '#title' => $this->t('Add new document'),
      '#url' => $url,
      '#attributes' => [
        'class' => ['button button--action', 'button--primary', 'use-ajax'],
        'data-dialog-type' => 'dialog',
        'data-dialog-options' => Json::encode([
          'width' => '60%',
          'dialogClass' => 'views-ui-dialog js-views-ui-dialog',
        ]),
      ],
    ];
  }

}
