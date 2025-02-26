<?php

namespace Drupal\edw_paragraphs_container\Plugin\Layout;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
/**
 * Configurable two column layout plugin class.
 *
 * @internal
 *   Plugin classes are internal.
 */


class TwoColumnLayout extends LayoutDefault implements PluginFormInterface, ContainerFactoryPluginInterface {

  protected ModuleHandlerInterface $moduleHandler;

  public function __construct(array $configuration, $pluginId, $pluginDefinition, ModuleHandlerInterface $moduleHandler) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->moduleHandler = $moduleHandler;
  }

  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $columnSettings = [];

    foreach (array_keys($this->getColumns()) as $column) {
      $columnSettings[$column] = [
        'fills_page_width' => FALSE,
        'wrapper' => 'div',
        'grid_column_start' => NULL,
        'grid_column_end' => NULL,
      ];
    }

    return parent::defaultConfiguration() + [
        'extra_classes' => [],
        'column_widths' => '50-50',
      ] + $columnSettings;
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
      '#description' => $this->t('Space separated list of extra classes.'),
    ];

    $form['column_widths'] = [
      '#type' => 'select',
      '#title' => $this->t('Column widths'),
      '#options' => [
        '50-50' => $this->t('50% / 50%'),
        '33-67' => $this->t('33% / 67%'),
        '67-33' => $this->t('67% / 33%'),
        '25-75' => $this->t('25% / 75%'),
        '75-25' => $this->t('75% / 25%'),
      ],
      '#default_value' => $configuration['column_widths'],
      '#description' => $this->t('Select the width of the columns.'),
    ];

    foreach (array_keys($this->getColumns()) as $column) {
      $form += $this->getColumnForm($column, $configuration);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Save the column widths.
    $this->configuration['column_widths'] = $form_state->getValue('column_widths');

    $extraClasses = $form_state->getValue('extra_classes');
    // Explode the string into an array and trim the values.
    $this->configuration['extra_classes'] = array_map('trim', explode(' ', $extraClasses));

    // Save the column settings.
    foreach (array_keys($this->getColumns()) as $column) {
      $this->configuration[$column]['fills_page_width'] = $form_state->getValue($column)['fills_page_width'];
      $this->configuration[$column]['wrapper'] = $form_state->getValue($column)['wrapper'];
      $this->configuration[$column]['grid_column_start'] = $form_state->getValue($column)['grid_columns']['grid_column_start'];
      $this->configuration[$column]['grid_column_end'] = $form_state->getValue($column)['grid_columns']['grid_column_end'];
      $this->configuration[$column]['background_color'] = $form_state->getValue($column)['background_color'];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getColumns() {
    return [
      'column_1' => $this->t('First column'),
      'column_2' => $this->t('Second column'),
    ];
  }

  /**
   * Builds the form for a column config.
   *
   * @param string $column
   *   The column id.
   * @param array $configuration
   *   The configuration.
   *
   * @return array
   *   The form.
   */
  protected function getColumnForm($column, array $configuration) {
    $columns = $this->getColumns();
    $form = [];

    $form[$column] = [
      '#type' => 'details',
      '#title' => $columns[$column],
      '#open' => FALSE,
      '#access' => TRUE,
    ];

    $form[$column]['fills_page_width'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Fills page width'),
      '#default_value' => $configuration[$column]['fills_page_width'],
      '#description' => $this->t('If checked, the column will span the full width of the page.'),
    ];

    $configureColumnLayoutAccess = $this->currentUser()->hasPermission('configure column layout options');
    $form[$column]['wrapper'] = [
      '#type' => 'select',
      '#title' => $this->t('Wrapper'),
      '#options' => [
        'div' => $this->t('Div'),
        'span' => $this->t('Span'),
        'section' => $this->t('Section'),
        'article' => $this->t('Article'),
        'header' => $this->t('Header'),
        'footer' => $this->t('Footer'),
        'aside' => $this->t('Aside'),
      ],
      '#default_value' => $configuration[$column]['wrapper'],
      '#description' => $this->t('Select the HTML element to wrap the column.'),
      '#access' => $configureColumnLayoutAccess,
    ];

    $form[$column]['grid_columns'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline'],
      ],
      '#access' => $configureColumnLayoutAccess,
    ];

    $form[$column]['grid_columns']['grid_column_start'] = [
      '#type' => 'number',
      '#title' => $this->t('Grid column start'),
      '#min' => 1,
      '#max' => 12,
      '#default_value' => $configuration[$column]['grid_column_start'],
      '#description' => $this->t('The column start position in the grid.'),
      '#access' => $configureColumnLayoutAccess,
    ];

    $form[$column]['grid_columns']['grid_column_end'] = [
      '#type' => 'number',
      '#title' => $this->t('Grid column end'),
      '#min' => 1,
      '#max' => 12,
      '#default_value' => $configuration[$column]['grid_column_end'],
      '#description' => $this->t('The column end position in the grid.'),
      '#access' => $configureColumnLayoutAccess,
    ];

    $form[$column]['background_color'] = [
      '#type' => 'select',
      '#title' => $this->t('Background color'),
      '#options' => [
        '' => $this->t('None'),
      ],
      '#default_value' => $configuration[$column]['background_color'],
    ];
    foreach (array_keys($this->getColumns()) as $column) {
      if (!isset($form[$column])) {
        continue;
      }
      $this->moduleHandler->invokeAll('edw_paragraphs_container_column_alter', [&$form[$column]]);
    }

    return $form;
  }

  /**
   * Dependency injection is not applicable for layout plugins.
   *
   * Returns the current user.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   The current user.
   */
  protected function currentUser() {
    return \Drupal::currentUser();
  }

}
