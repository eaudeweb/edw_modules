<?php

namespace Drupal\edw_maps;

/**
 * Class EdwMapsElementsTrait.
 *
 * Provide common functions for Mapbox Style view config elements.
 *
 * @package Drupal\leaflet
 */
trait EdwMapsElementsTrait {

  /**
   * Sets fieldsets for options form.
   *
   * @param array $form
   *   The form.
   */
  protected function setFieldsets(array &$form) {
    $form['tile_options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Base map tile options'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['rendering_options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Rendering options'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['popup_options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Popup options'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['display_options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Mapbox display options'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];
  }

  /**
   * Sets tile options for the form.
   *
   * @param array $form
   *   The form.
   * @param string $defaultMap
   *   Default map style URL from config.
   */
  protected function setTileOptions(array &$form, string $defaultMap) {
    $form['tile_options']['map_type'] = [
      '#title' => $this->t('Map type'),
      '#description' => $this->t('Map type to be rendered.'),
      '#type' => 'select',
      '#options' => [
        'custom' => $this->t('Custom'),
        'clear_map' => $this->t('Clear Map (UN GIS)'),
        'carto_tile' => $this->t('Carto Tile (UN GIS)'),
      ],
      '#default_value' => $this->options['tile_options']['map_type'] ?? 'custom',
      '#required' => TRUE,
    ];

    $form['tile_options']['style_url'] = [
      '#title' => $this->t('Custom style URL'),
      '#description' => $this->t('Mapbox style URL. Will be used if "Custom" map type is chosen'),
      '#type' => 'textfield',
      '#default_value' => $this->options['tile_options']['style_url'] ?? $defaultMap,
      '#states' => [
        'visible' => [
          ':input[name="style_options[tile_options][map_type]"]' => ['value' => 'custom'],
        ],
      ],
    ];

    $form['tile_options']['clear_map_disclaimer'] = [
      '#type' => 'container',
      'message' => [
        '#type' => 'markup',
        '#markup' => $this->t('<div class="warning"">Clear map has a maximum zoom level of 5. Some pins may appear <b> overlapped </b> even at maximum zoom.'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="style_options[tile_options][map_type]"]' => ['value' => 'clear_map'],
        ],
      ],
    ];
  }

  /**
   * Sets tile options for the form.
   *
   * @param array $form
   *   The form.
   * @param array $geoFieldSources
   *   The view's geofields.
   * @param array $iso3FieldSources
   *   The view's string fields.
   */
  protected function setRenderingOptionsCheckbox(array &$form, array $geoFieldSources, array $iso3FieldSources) {
    $form['rendering_options']['render_items'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Data visualization'),
      '#description' => $this->t('Select how to display data on the map.'),
      '#options' => [
        'pin' => $this->t('Pins (needs latitude/longitude data source)'),
        'area' => $this->t('Highlight area (needs geofield polygon data source)'),
        'country' => $this->t('Highlight countries (needs ISO3 code data source)'),
      ],
      '#default_value' => $this->options['rendering_options']['render_items'],
    ];

    if (!count($geoFieldSources)) {
      $form['rendering_options']['geofield_warning'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('You haven not added any GeoField, to render pins or areas add one to fields and come back here to set it as Data Source.'),
        '#attributes' => [
          'class' => ['warning'],
        ],
      ];
    }

    if (!count($iso3FieldSources)) {
      $form['rendering_options']['string_warning'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('You haven not added any plain Text fields, to render colored countries add one to fields and come back here to set it as Data Source.'),
        '#attributes' => [
          'class' => ['warning'],
        ],
      ];
    }
  }

  /**
   * Sets tile options for the form.
   *
   * @param array $form
   *   The form.
   * @param array $geoFieldSources
   *   The view's geofields.
   * @param array $iso3FieldSources
   *   The view's string fields.
   */
  protected function setRenderingOptions(array &$form, array $geoFieldSources, array $iso3FieldSources) {
    $form['rendering_options']['pins_source'] = [
      '#title' => $this->t('Data Source for Pins'),
      '#type' => 'select',
      '#description' => $this->t('Which geofield do you want to use to draw pins?'),
      '#options' => $geoFieldSources,
      '#default_value' => $this->options['rendering_options']['pins_source'] ?? NULL,
      '#states' => [
        'visible' => [
          ':input[name="style_options[rendering_options][render_items][pin]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="style_options[rendering_options][render_items][pin]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['rendering_options']['area_source'] = [
      '#title' => $this->t('Data Source for Area'),
      '#type' => 'select',
      '#description' => $this->t('Which geofield do you want to use to draw areas?'),
      '#options' => $geoFieldSources,
      '#default_value' => $this->options['rendering_options']['area_source'] ?? NULL,
      '#states' => [
        'visible' => [
          ':input[name="style_options[rendering_options][render_items][area]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="style_options[rendering_options][render_items][area]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['rendering_options']['country_source'] = [
      '#title' => $this->t('Data Source for Country'),
      '#type' => 'select',
      '#description' => $this->t('Which string field (ISO3) do you want to use to draw countries?'),
      '#options' => $iso3FieldSources,
      '#default_value' => $this->options['rendering_options']['country_source'] ?? NULL,
      '#states' => [
        'visible' => [
          ':input[name="style_options[rendering_options][render_items][country]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="style_options[rendering_options][render_items][country]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['rendering_options']['area_color'] = [
      '#title' => $this->t('Color to highlight areas (HEX)'),
      '#type' => 'textfield',
      '#default_value' => $this->options['rendering_options']['area_color'] ?? NULL,
      '#field_prefix' => '#',
      '#states' => [
        'visible' => [
          ':input[name="style_options[rendering_options][render_items][area]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="style_options[rendering_options][render_items][area]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['rendering_options']['country_color'] = [
      '#title' => $this->t('Color to highlight countries (HEX)'),
      '#type' => 'textfield',
      '#default_value' => $this->options['rendering_options']['country_color'] ?? NULL,
      '#field_prefix' => '#',
      '#states' => [
        'visible' => [
          ':input[name="style_options[rendering_options][render_items][country]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="style_options[rendering_options][render_items][country]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['rendering_options']['area_hover_color'] = [
      '#title' => $this->t('Hover color to highlight areas (HEX)'),
      '#description' => $this->t('If not set a lighter shade of main color will be used.'),
      '#type' => 'textfield',
      '#default_value' => $this->options['rendering_options']['area_hover_color'] ?? NULL,
      '#field_prefix' => '#',
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="style_options[rendering_options][render_items][area]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['rendering_options']['country_hover_color'] = [
      '#title' => $this->t('Hover Color to highlight countries (HEX)'),
      '#description' => $this->t('If not set a lighter shade of main color will be used.'),
      '#type' => 'textfield',
      '#default_value' => $this->options['rendering_options']['country_hover_color'] ?? NULL,
      '#field_prefix' => '#',
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="style_options[rendering_options][render_items][country]"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * Sets tile options for the form.
   *
   * @param array $form
   *   The form.
   * @param array $allFields
   *   View's fields.
   */
  protected function setPopupOptions(array &$form, array $allFields) {
    $form['popup_options']['pin_popup_source'] = [
      '#title' => $this->t('Data Source for pins Pop-ups'),
      '#type' => 'select',
      '#description' => $this->t('Which field you want to be rendered in popup at pin click ?'),
      '#options' => $allFields,
      '#default_value' => $this->options['popup_options']['pin_popup_source'] ?? NULL,
      '#empty_option' => t('- None -'),
      '#empty_value' => '_none',
      '#states' => [
        'visible' => [
          ':input[name="style_options[rendering_options][render_items][pin]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['popup_options']['area_popup_source'] = [
      '#title' => $this->t('Data Source for area Pop-ups'),
      '#type' => 'select',
      '#description' => $this->t('Which field you want to be rendered in popup at area click ?'),
      '#options' => $allFields,
      '#default_value' => $this->options['popup_options']['area_popup_source'] ?? NULL,
      '#empty_option' => t('- None -'),
      '#empty_value' => '_none',
      '#states' => [
        'visible' => [
          ':input[name="style_options[rendering_options][render_items][country]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['popup_options']['country_popup_source'] = [
      '#title' => $this->t('Data Source for country Pop-ups'),
      '#type' => 'select',
      '#description' => $this->t('Which field you want to be rendered in popup when clicking on a country ?'),
      '#options' => $allFields,
      '#default_value' => $this->options['popup_options']['country_popup_source'] ?? NULL,
      '#empty_option' => t('- None -'),
      '#empty_value' => '_none',
    ];
  }

  /**
   * Sets target link options at click on a country.
   *
   * @param array $form
   *   The form.
   * @param array $fields
   *   The link fields available.
   */
  protected function setContryClickOptions(&$form, array $fields) {
    $form['country_click'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Country target links at click'),
      '#description' => 'Here you can choose to open a new tab when clicking on a country. This only works well if popups are not displayed or are displayed at hover.',
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="style_options[rendering_options][render_items][country]"]' => ['checked' => TRUE],
        ],
      ],
      'country_click_source' => [
        '#title' => $this->t('Data Source for country redirects'),
        '#type' => 'select',
        '#description' => $this->t('Which link field you want to use as target when clicking on a country ?'),
        '#options' => $fields,
        '#default_value' => $this->options['country_click']['country_click_source'] ?? NULL,
        '#empty_option' => t('- None -'),
        '#empty_value' => '_none',
      ],
    ];
  }

  /**
   * Sets tile options for the form.
   *
   * @param array $form
   *   The form.
   */
  protected function setDisplayOptions(&$form) {
    $form['display_options']['projection'] = [
      '#title' => $this->t('Projection'),
      '#description' => $this->t('Sets which projection a map is rendered in.'),
      '#type' => 'select',
      '#options' => [
        'mercator' => $this->t('Mercator'),
        'globe' => $this->t('Globe'),
      ],
      '#default_value' => $this->options['display_options']['projection'] ?? 'mercator',
      '#required' => TRUE,
    ];

    $form['display_options']['zoom'] = [
      '#title' => $this->t('Default zoom'),
      '#type' => 'number',
      '#min' => 0,
      '#step' => 0.1,
      '#default_value' => $this->options['display_options']['zoom'] ?? 1.5,
      '#required' => TRUE,
    ];

    $form['display_options']['max_zoom'] = [
      '#title' => $this->t('Maximum zoom'),
      '#type' => 'number',
      '#min' => 0,
      '#max' => 22,
      '#step' => 1,
      '#default_value' => $this->options['display_options']['max_zoom'] ?? 22,
      '#required' => FALSE,
      '#description' => $this->t('<b>Do not change this unless you really need it!</b> It also affects the maximum level of pin clustering, so you can see overlapping pins.</br>
If Clear Map is use the max zoom level will be<b> 5 </b>unless you select a lower value.'),
    ];

    $form['display_options']['pitch'] = [
      '#title' => $this->t('Pitch'),
      '#description' => $this->t('Angle towards the horizon'),
      '#type' => 'number',
      '#min' => 0,
      '#step' => 0.1,
      '#default_value' => $this->options['display_options']['pitch'] ?? 1.5,
      '#required' => TRUE,
    ];

    $form['display_options']['center'] = [
      '#type' => 'fieldset',
      '#collapsible' => FALSE,
      '#title' => $this->t('Default map center'),
      '#description' => $this->t('The reference longitude and latitude of the projection between -180-90 and 18090 inclusive.'),
      'long' => [
        '#title' => $this->t('Longitude'),
        '#type' => 'textfield',
        '#default_value' => $this->options['display_options']['center']['long'] ?? 0,
        '#required' => TRUE,
      ],
      'lat' => [
        '#title' => $this->t('Latitude'),
        '#type' => 'textfield',
        '#default_value' => $this->options['display_options']['center']['lat'] ?? 0,
        '#required' => TRUE,
      ],
    ];

    $form['display_options']['disable_scroll_zoom'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable scroll zoom'),
      '#default_value' => $this->options['display_options']['disable_scroll_zoom'] ?? 0,
    ];

    $form['display_options']['world_copies'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Render world copies'),
      '#default_value' => $this->options['display_options']['world_copies'] ?? 0,
    ];

    $form['display_options']['clusters'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable pin clustering'),
      '#default_value' => $this->options['display_options']['clusters'] ?? 1,
      '#states' => [
        'visible' => [
          ':input[name="style_options[rendering_options][render_items][pin]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['display_options']['hover_popups'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show popups at hover'),
      '#description' => $this->t('If selected, popups will be shown at hover instead of click'),
      '#default_value' => $this->options['display_options']['hover_popups'] ?? 0,
    ];
  }

}
