<?php

namespace Drupal\edw_maps\Plugin\views\style;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\edw_maps\EdwMapsElementsTrait;
use Drupal\edw_maps\Services\EdwMapsDataService;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Style plugin to render rows as accordions.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "mapbox_map",
 *   title = @Translation("EDW Mapbox Map"),
 *   help = @Translation("Renders view results on mapbox map as pins or
 *   polygons"),
 *   theme = "views_view_mapbox_map",
 *   display_types = { "normal" }
 * )
 */
class MapboxMapStyle extends StylePluginBase {

  use EdwMapsElementsTrait;

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * Does the style plugin support custom css class for the rows.
   *
   * @var bool
   */
  protected $usesRowClass = TRUE;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  private ConfigFactory $configFactory;

  /**
   * The field type manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private EntityFieldManagerInterface $fieldTypeManager;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  private FormBuilder $formBuilder;

  /**
   * The EDW Maps data service.
   *
   * @var \Drupal\edw_maps\Services\EdwMapsDataService
   */
  private EdwMapsDataService $edwMapsDataService;

  /**
   * Plugin constructor.
   */
  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    ConfigFactory $configFactory,
    EntityFieldManagerInterface $fieldTypeManager,
    FormBuilder $formBuilder,
    EdwMapsDataService $edwMapsDataService
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $configFactory;
    $this->fieldTypeManager = $fieldTypeManager;
    $this->formBuilder = $formBuilder;
    $this->edwMapsDataService = $edwMapsDataService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_field.manager'),
      $container->get('form_builder'),
      $container->get('edw_maps.utils'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function evenEmpty() {
    // Render map even if there is no data.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $formState) {
    parent::validateOptionsForm($form, $formState);
    $this->validateFields($formState);

    $config = $this->configFactory->get('edw_maps.settings');
    $values = $formState->getValues()['style_options'];
    $mapType = $values['tile_options']['map_type'];

    if ($config->get('token') == NULL) {
      $formState->setErrorByName('', $this->t('No Mapbox TOKEN has been configured. Please visit modules settings and add one.'));
      return;
    }

    if ($mapType == 'custom' && $config->get('default_style_url') == NULL &&
      empty($values['tile_options']['style_url'])) {
      $formState->setErrorByName('style_options][tile_options][style_url', $this->t('Style URL is required for custom map type.'));
    }

    if ($mapType == 'carto_tile' && ($values['rendering_options']['render_items']['pin']
        || $values['rendering_options']['render_items']['area'])) {
      $formState->setErrorByName('style_options][render_items', $this->t('Carto tile supports only country highlight.'));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $formState) {
    parent::buildOptionsForm($form, $formState);
    $defaultMap = 'mapbox://styles/mapbox/streets-v11';
    $config = $this->configFactory->get('edw_maps.settings');
    if ($config !== NULL) {
      $defaultMap = $config->get('default_style_url');
    }

    $geofieldSources = $this->getAvailableDataSources('geofield');
    $iso3FieldSources = $this->getAvailableDataSources('string');
    $allFields = $this->getAvailableDataSources('all');

    $this->setFieldsets($form);
    $this->setTileOptions($form, $defaultMap);

    $this->setRenderingOptionsCheckbox($form, $geofieldSources, $iso3FieldSources);
    $this->setRenderingOptions($form, $geofieldSources, $iso3FieldSources);
    $this->setPopupOptions($form, $allFields);
    $this->setDisplayOptions($form);
  }

  /**
   * {@inheritDoc}
   */
  public function render() {
    $variables = parent::render();
    if (!isset($variables[0]['#view'])) {
      return [];
    }
    /** @var \Drupal\views\ViewExecutable $view */
    $view = $variables[0]['#view'];
    $options = $view->style_plugin->options;
    $containerId = 'map-container-' . $view->id() . '-' . $view->current_display;
    $config = $this->configFactory->get('edw_maps.settings');
    $renderPins = (boolean) $options['rendering_options']['render_items']['pin'];
    $renderCountries = (boolean) $options['rendering_options']['render_items']['country'];
    $renderAreas = (boolean) $options['rendering_options']['render_items']['area'];
    $pinsSourceField = $options['rendering_options']['pins_source'];
    $countrySourceField = $options['rendering_options']['country_source'];
    $areaSourceField = $options['rendering_options']['area_source'];
    $popupPinSource = $options['popup_options']['pin_popup_source'];
    $popupCountrySource = $options['popup_options']['country_popup_source'];
    $popupAreaSourceField = $options['popup_options']['area_popup_source'];

    $pinData = [];
    $countryData = [];
    $areaData = [];

    if ($renderPins) {
      $pinData = $this->edwMapsDataService->getPinData($view, $pinsSourceField, $popupPinSource);
    }
    if ($renderCountries) {
      $countryData = $this->edwMapsDataService->getCountryData($view, $countrySourceField, $popupCountrySource);
    }
    if ($renderAreas) {
      $areaData = $this->edwMapsDataService->getAreaData($view, $areaSourceField, $popupAreaSourceField);
    }
    $count = count($pinData);
    \Drupal::logger('edw_maps')->info("pins count: $count");

    $settings = [
      'containerId' => $containerId,
      'mapboxToken' => $config->get('token'),
      'mapType' => $options['tile_options']['map_type'],
      'mapboxStyleUrl' => empty($options['tile_options']['style_url']) ? $config->get('default_style_url') : $options['tile_options']['style_url'],
      'projection' => $options['display_options']['projection'],
      'center' => [
        (float) $options['display_options']['center']['long'],
        (float) $options['display_options']['center']['lat'],
      ],
      'pitch' => (float) $options['display_options']['pitch'],
      'zoom' => (float) $options['display_options']['zoom'],
      'disableScrollZoom' => (boolean) $options['display_options']['disable_scroll_zoom'],
      'worldCopies' => (boolean) $options['display_options']['world_copies'],
      'renderClusters' => (boolean) $options['display_options']['clusters'],
      'renderPins' => $renderPins,
      'renderCountries' => $renderCountries,
      'renderAreas' => $renderAreas,
      'countryColor' => $options['rendering_options']['country_color'],
      'areaColor' => $options['rendering_options']['area_color'],
      'pinData' => $pinData,
      'countryData' => $countryData,
      'areaData' => $areaData,
      'clearMapSource' => $this->edwMapsDataService->getClearMapSource(),
    ];

    return [
      '#theme' => 'views_view_mapbox_map',
      '#mapContainerId' => $containerId,
      '#exposedFilters' => !empty($view->exposed_data),
      '#attached' => [
        'library' => ['edw_maps/edw_map'],
        'drupalSettings' => [
          'edw_map' => $settings,
        ],
      ],
      '#cache' => [
        'tags' => ['config:edw_maps.settings'],
      ],
    ];
  }

  /**
   * Get a list of fields and a sublist of geo data fields in this view.
   *
   * @param string $type
   *   The field type to look for in views fields.
   *
   * @return array
   *   Available data sources.
   */
  protected function getAvailableDataSources(string $type) {
    $availableFields = [];
    /** @var \Drupal\views\Plugin\views\ViewsHandlerInterface $handler */
    $fieldHandlers = $this->displayHandler->getHandlers('field');
    foreach ($fieldHandlers as $fieldId => $handler) {
      $label = $handler->adminLabel() ?: $fieldId;
      if ($type == 'all') {
        $availableFields[$fieldId] = $label;
        continue;
      }

      $entityType = $handler->getEntityType();
      $allDefinitions = $this->fieldTypeManager->getFieldStorageDefinitions($entityType);
      if (isset($allDefinitions[$fieldId])) {
        $fieldType = $allDefinitions[$fieldId]->getType();
        if ($type == $fieldType) {
          $availableFields[$fieldId] = $label;
        }
      }
    }

    return $availableFields;
  }

  /**
   * Validates if a string is a correct latitude value.
   *
   * @param string $latitude
   *   The string to be verified upon.
   *
   * @return bool
   *   If it is valid or not.
   */
  private function isValidLatitude($latitude) {
    if (!is_numeric($latitude)) {
      return FALSE;
    }
    $latitude = floatval($latitude);
    return ($latitude >= -90.0 && $latitude <= 90.0);
  }

  /**
   * Validates if a string is a correct longitude value.
   *
   * @param string $longitude
   *   The string to be verified upon.
   *
   * @return bool
   *   If it is valid or not.
   */
  private function isValidLongitude($longitude) {
    if (!is_numeric($longitude)) {
      return FALSE;
    }
    $longitude = floatval($longitude);
    return ($longitude >= -90.0 && $longitude <= 90.0);
  }

  /**
   * Validates if a string is a correct HEX color value.
   *
   * @param string $color
   *   The string to be verified upon.
   *
   * @return bool
   *   If it is valid or not.
   */
  private function isValidHexColor($color) {
    $pattern = '/^[A-Fa-f0-9]{6}$/';
    return preg_match($pattern, $color) === 1;
  }

  /**
   * Validates basic fields for options form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  private function validateFields(FormStateInterface $formState) {
    $values = $formState->getValues()['style_options'];
    if (!$this->isValidLatitude($values['display_options']['center']['lat'])) {
      $formState->setErrorByName('style_options][display_options][center][lat', $this->t('Latitude value is wrong.'));
    }

    if (!$this->isValidLongitude($values['display_options']['center']['long'])) {
      $formState->setErrorByName('style_options][display_options][center][long', $this->t('Longitude value is wrong.'));
    }

    $countryColor = $values['rendering_options']['country_color'];
    $areaColor = $values['rendering_options']['area_color'];

    if (!empty($countryColor) && !$this->isValidHexColor($countryColor)) {
      $formState->setErrorByName('style_options][rendering_options][country_color', $this->t('Invalid HEX color for country.'));
    }

    if (!empty($areaColor) && !$this->isValidHexColor($areaColor)) {
      $formState->setErrorByName('style_options][rendering_options][area_color', $this->t('Invalid HEX color for area.'));
    }
  }

}
