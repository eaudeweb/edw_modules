<?php

namespace Drupal\edw_paragraphs_container\Plugin\Layout;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Configurable one column layout plugin class.
 *
 * @internal
 *   Plugin classes are internal.
 */
class OneColumnLayout extends LayoutDefault implements PluginFormInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
        'extra_classes' => [],
        'width' => 'default',
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $configuration = $this->getConfiguration();

    $form['extra_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Extra classes'),
      '#default_value' => implode(' ', $configuration['extra_classes']),
    ];

    $form['width'] = [
      '#type' => 'select',
      '#title' => $this->t('Width'),
      '#options' => $this->getWidthOptions(),
      '#default_value' => $configuration['width'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $extraClasses = $form_state->getValue('extra_classes');
    // Explode the string into an array and trim the values.
    $this->configuration['extra_classes'] = array_map('trim', explode(' ', $extraClasses));

    $this->configuration['width'] = $form_state->getValue('width');
  }

  /**
   * Gets the width options for the layout.
   *
   * @return array
   *   An array of width options.
   */
  public function getWidthOptions() {
    return [
      'default' => $this->t('Default width'),
      'small' => $this->t('Narrower width'),
      'full' => $this->t('Full width'),
    ];
  }

}
