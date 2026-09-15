<?php

namespace Drupal\Tests\edw_paragraphs\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the configurable one and two column layouts.
 *
 * These back the building blocks editors assemble pages from, so their
 * configuration is written by hand on a form and read back on every render.
 * A key the form writes but the defaults never declare is invisible until an
 * editor opens a section that predates it.
 *
 * @group edw_paragraphs
 */
class ColumnLayoutTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'layout_discovery',
    'edw_paragraphs',
    'edw_paragraphs_container',
  ];

  /**
   * The layout plugin manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected $layoutManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['user']);

    $this->layoutManager = \Drupal::service('plugin.manager.core.layout');
  }

  /**
   * Tests that a one column layout starts at the default width.
   */
  public function testOneColumnLayoutStartsAtTheDefaultWidth(): void {
    $configuration = $this->layoutManager->createInstance('one_column')->getConfiguration();

    $this->assertSame('default', $configuration['width']);
    $this->assertSame([], $configuration['extra_classes']);
  }

  /**
   * Tests that a one column layout keeps what the editor chose.
   */
  public function testOneColumnLayoutKeepsWhatTheEditorChose(): void {
    $layout = $this->layoutManager->createInstance('one_column');
    $formState = new FormState();
    $formState->setValues(['width' => 'full', 'extra_classes' => 'hero dark']);
    $form = [];

    $layout->submitConfigurationForm($form, $formState);
    $configuration = $layout->getConfiguration();

    $this->assertSame('full', $configuration['width']);
    $this->assertSame(['hero', 'dark'], $configuration['extra_classes']);
  }

  /**
   * Tests what stray spacing in the class list turns into.
   *
   * The list is split on single spaces and only trimmed afterwards, so double
   * spaces and a trailing space each leave an empty entry behind. Those reach
   * the template's class attribute, where they render as doubled separators.
   * Pinned because it is the shape stored in config, not just a display quirk.
   */
  public function testStraySpacingInTheClassListLeavesEmptyEntries(): void {
    $layout = $this->layoutManager->createInstance('one_column');
    $formState = new FormState();
    $formState->setValues(['width' => 'default', 'extra_classes' => ' hero  dark ']);
    $form = [];

    $layout->submitConfigurationForm($form, $formState);

    $this->assertSame(['', 'hero', '', 'dark', ''], $layout->getConfiguration()['extra_classes']);
  }

  /**
   * Tests that the offered widths are the ones the theme supports.
   */
  public function testTheOfferedWidthsAreTheOnesTheThemeSupports(): void {
    $options = $this->layoutManager->createInstance('one_column')->getWidthOptions();

    $this->assertSame(['default', 'small', 'full'], array_keys($options));
  }

  /**
   * Tests that a two column layout starts evenly split.
   */
  public function testTwoColumnLayoutStartsEvenlySplit(): void {
    $configuration = $this->layoutManager->createInstance('two_column')->getConfiguration();

    $this->assertSame('50-50', $configuration['column_widths']);
    $this->assertArrayHasKey('column_1', $configuration);
    $this->assertArrayHasKey('column_2', $configuration);
    $this->assertFalse($configuration['column_1']['fills_page_width']);
    $this->assertSame('div', $configuration['column_1']['wrapper']);
  }

  /**
   * Tests that the two column settings survive a round trip through the form.
   */
  public function testTheTwoColumnSettingsSurviveRoundTrip(): void {
    $layout = $this->layoutManager->createInstance('two_column');
    $formState = new FormState();
    $formState->setValues([
      'column_widths' => '33-67',
      'extra_classes' => 'feature',
      'column_1' => [
        'fills_page_width' => 1,
        'wrapper' => 'aside',
        'grid_columns' => ['grid_column_start' => 1, 'grid_column_end' => 4],
        'background_color' => 'grey',
      ],
      'column_2' => [
        'fills_page_width' => 0,
        'wrapper' => 'section',
        'grid_columns' => ['grid_column_start' => 5, 'grid_column_end' => 12],
        'background_color' => '',
      ],
    ]);
    $form = [];

    $layout->submitConfigurationForm($form, $formState);
    $configuration = $layout->getConfiguration();

    $this->assertSame('33-67', $configuration['column_widths']);
    $this->assertSame(['feature'], $configuration['extra_classes']);
    $this->assertSame('aside', $configuration['column_1']['wrapper']);
    $this->assertSame(1, $configuration['column_1']['grid_column_start']);
    $this->assertSame(4, $configuration['column_1']['grid_column_end']);
    $this->assertSame('grey', $configuration['column_1']['background_color']);
    $this->assertSame('section', $configuration['column_2']['wrapper']);
  }

  /**
   * Tests that a freshly placed two column section can be configured.
   *
   * The settings form reads a background colour out of the configuration for
   * each column, but the layout's defaults never declare one — so the very
   * first time an editor opens the settings of a new two column section,
   * before anything has been saved, the key is not there.
   */
  public function testFreshTwoColumnSectionCanBeConfigured(): void {
    $layout = $this->layoutManager->createInstance('two_column');

    $configuration = $layout->getConfiguration();

    $this->assertArrayHasKey('background_color', $configuration['column_1']);
    $this->assertArrayHasKey('background_color', $configuration['column_2']);
  }

  /**
   * Tests that a saved two column section can be configured again.
   */
  public function testSavedTwoColumnSectionCanBeConfiguredAgain(): void {
    $layout = $this->layoutManager->createInstance('two_column', [
      'column_1' => ['background_color' => 'grey'],
      'column_2' => ['background_color' => ''],
    ]);
    $formState = new FormState();

    $form = $layout->buildConfigurationForm([], $formState);

    $this->assertSame('grey', $form['column_1']['background_color']['#default_value']);
  }

}
