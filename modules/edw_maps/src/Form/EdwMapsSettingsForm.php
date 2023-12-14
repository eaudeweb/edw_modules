<?php

namespace Drupal\edw_maps\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides settings for the Edw Maps module.
 */
class EdwMapsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'edw_maps_settings';
  }

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames() {
    return ['edw_maps_settings'];
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $formState = NULL) {
    $form = parent::buildForm($form, $formState);

    $config = $this->config('edw_maps.settings');
    $form['origin'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mapbox Token'),
      '#default_value' => $config->get('token'),
      '#description' => $this->t("The mapbox account token. If you don't have an account, you can create one <a href=\"https://account.mapbox.com/\" target=\"_blank\">here</a>"),
      '#required' => TRUE,
      '#config' => [
        'key' => 'edw_maps.settings:token',
      ],
    ];

    $form['default_style_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Mapbox Style URL'),
      '#default_value' => $config->get('default_style_url'),
      '#description' => $this->t("If set this will be used for all maps that do not specify a style"),
      '#required' => FALSE,
      '#config' => [
        'key' => 'edw_maps.settings:default_style_url',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $formState) {
    $token = $formState->getValue('token');
    if (!isset($token) || trim($token) == '') {
      $formState->setErrorByName('token', $this->t('Please enter a valid Mapbox Token.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState) {
    $config = $this->config('edw_maps.settings');
    $values = $formState->getValues();
    $config->set('token', $values['token']);
    $config->set('default_style_url', $values['default_style_url']);
    $config->save();

    $this->messenger()
      ->addMessage($this->t('EDW Mapbox settings have been saved.'));
  }

}
