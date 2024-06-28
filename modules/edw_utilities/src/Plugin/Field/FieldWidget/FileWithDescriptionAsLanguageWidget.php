<?php

namespace Drupal\edw_utilities\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Render\Renderer;
use Drupal\file\Plugin\Field\FieldWidget\FileWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'file_with_description_as_language' widget.
 *
 * Extends File Widget with the possibility to display the Language
 * selected from a dropdown with languages instead of description.
 *
 * @FieldWidget(
 *   id = "file_with_description_as_language",
 *   label = @Translation("File with description as language"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class FileWithDescriptionAsLanguageWidget extends FileWidget {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public function __construct($pluginId, $pluginDefinition, FieldDefinitionInterface $fieldDefinition, array $settings, array $thirdPartySettings, ElementInfoManagerInterface $elementInfo, Renderer $renderer) {
    parent::__construct($pluginId, $pluginDefinition, $fieldDefinition, $settings, $thirdPartySettings, $elementInfo);
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition) {
    return new static(
      $pluginId,
      $pluginDefinition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('element_info'),
      $container->get('renderer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $formState) {
    $element = parent::formElement($items, $delta, $element, $form, $formState);

    $defaults = [
      'language' => '',
    ];

    // Field stores FID value in a single mode, so we need to transform it for
    // form element to recognize it correctly.
    if (!isset($items[$delta]->fids) && isset($items[$delta]->target_id)) {
      $items[$delta]->fids = [$items[$delta]->target_id];
    }
    $element['#default_value'] = $items[$delta]->getValue() + $defaults;

    return $element;
  }

  /**
   * Overrides \Drupal\Core\Field\WidgetBase::formMultipleElements().
   *
   * Special handling for draggable multiple widgets and 'add more' button.
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $formState) {
    $elements = parent::formMultipleElements($items, $form, $formState);

    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $fileUploadHelp = [
      '#theme' => 'file_upload_help',
      '#description' => '',
      '#upload_validators' => $elements[0]['#upload_validators'] ?? NULL,
      '#cardinality' => $cardinality,
    ];
    if ($cardinality == 1) {
      // If there's only one field, return it as delta 0.
      if (empty($elements[0]['#default_value']['fids'])) {
        $fileUploadHelp['#description'] = $this->getFilteredDescription();
        $elements[0]['#description'] = $this->renderer->renderPlain($fileUploadHelp);
      }
    }
    // Add some properties that will eventually be added to the file upload
    // field. These are added here so that they may be referenced easily
    // through a hook_form_alter().
    $elements['#file_upload_description'] = $fileUploadHelp;

    return $elements;
  }

  /**
   * Form API callback: Processes a file_generic_language field element.
   *
   * Expands the file_generic type to include the language.
   *
   * This method is assigned as a #process callback in formElement() method.
   */
  public static function process($element, FormStateInterface $formState, $form) {
    $element = parent::process($element, $formState, $form);
    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];

    $languages = \Drupal::languageManager()->getStandardLanguageList();
    \Drupal::moduleHandler()->alter('file_languages', $languages);

    $options = ['' => t('- None -')];
    foreach ($languages as $iso => $language) {
      $options[$iso] = $language[0];
    }

    if ($element['#description_field'] && $item['fids']) {
      $element['description'] = [
        '#type' => 'select',
        '#title' => t('Language'),
        '#options' => $options,
        '#default_value' => $item['description'] ?? '',
        '#value' => $item['description'] ?? '',
      ];
    }

    return $element;
  }

}
