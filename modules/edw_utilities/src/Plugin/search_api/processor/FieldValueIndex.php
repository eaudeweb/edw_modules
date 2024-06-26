<?php

namespace Drupal\edw_utilities\Plugin\search_api\processor;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Add content to index depending on entity's field value.
 *
 * @SearchApiProcessor(
 *   id = "field_value",
 *   label = @Translation("Filter entities"),
 *   description = @Translation("Add content to index depending on entity's field value."),
 *   stages = {
 *     "alter_items" = 0,
 *   },
 * )
 */
class FieldValueIndex extends ProcessorPluginBase implements PluginFormInterface {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();

    $configuration += [
      'filters' => '',
      'negate' => FALSE,
    ];

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['filters'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Filters'),
      '#description' => $this->t('Add field=value. Only fieldable entities with the matching field value get indexed.<b>For now, only one line is supported</b>.'),
      '#default_value' => $this->configuration['filters'],
    ];
    $form['negate'] = [
      '#title' => $this->t('Negate condition'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['negate'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $filters = trim($form_state->getValue('filters'));
    $parts = explode('=', $filters);
    if (count($parts) != 2) {
      $form_state->setErrorByName('filters', $this->t('Use field_name=field_value format for each line.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    $interface = FieldableEntityInterface::class;
    foreach ($index->getDatasources() as $datasource) {
      $entity_type_id = $datasource->getEntityTypeId();
      if (!$entity_type_id) {
        continue;
      }
      // We support users and any entities that implement
      // \Drupal\Core\Entity\EntityPublishedInterface.
      if ($entity_type_id === 'user') {
        return TRUE;
      }
      $entity_type = \Drupal::entityTypeManager()
        ->getDefinition($entity_type_id);
      if ($entity_type && $entity_type->entityClassImplements($interface)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items) {
    $config = trim($this->configuration['filters']);
    $parts = explode('=', $config);
    $field_name = $parts[0];
    $filter_value = $parts[1];
    // Annoyingly, this doc comment is needed for PHPStorm. See
    // http://youtrack.jetbrains.com/issue/WI-23586
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item_id => $item) {
      $object = $item->getOriginalObject()->getValue();
      if ($object instanceof FieldableEntityInterface) {
        if ($object->hasField($field_name)) {
          $field_type = $object->get($field_name)->getFieldDefinition()->getType();
          switch ($field_type) {
            case 'entity_reference':
              $field_values = array_column($object->get($field_name)->getValue(), 'target_id');
              break;

            default:
              $field_values = array_column($object->get($field_name)->getValue(), 'value');
              break;
          }
          $enabled = in_array($filter_value, $field_values);
          if ($this->configuration['negate']) {
            $enabled = !$enabled;
          }
          // Not all content types has same field.
          if (!$enabled) {
            unset($items[$item_id]);
          }
        }
      }
    }
  }

}
