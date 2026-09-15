<?php

namespace Drupal\Tests\edw_utilities\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests telling an entity browser what is already selected.
 *
 * An entity browser opens without knowing what the field it is filling
 * already holds, so its view cannot exclude those items and an editor is
 * offered things they have already picked. This alter passes the current
 * selection along as widget context, in the '1+2+3' form views arguments use.
 *
 * @group edw_utilities
 */
class EntityBrowserWidgetContextTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'edw_utilities',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
  }

  /**
   * Tests that the current selection is passed to the browser.
   */
  public function testTheCurrentSelectionIsPassedToTheBrowser(): void {
    $first = $this->createPage('First');
    $second = $this->createPage('Second');

    $element = $this->alterWidget([$first, $second]);

    $this->assertSame(
      $first->id() . '+' . $second->id(),
      $element['entity_browser']['#widget_context']['current_ids']
    );
  }

  /**
   * Tests that an empty field passes an empty selection.
   *
   * The key still has to be there: a view argument that is simply absent
   * behaves differently from one that is empty.
   */
  public function testAnEmptyFieldPassesAnEmptySelection(): void {
    $element = $this->alterWidget([]);

    $this->assertSame('', $element['entity_browser']['#widget_context']['current_ids']);
  }

  /**
   * Tests that widgets which are not entity browsers are left alone.
   */
  public function testOtherWidgetsAreLeftAlone(): void {
    $element = ['target_id' => ['#type' => 'entity_autocomplete']];
    $formState = new FormState();
    $context = [];

    edw_utilities_field_widget_single_element_form_alter($element, $formState, $context);

    $this->assertSame(['target_id' => ['#type' => 'entity_autocomplete']], $element);
  }

  /**
   * Runs the alter over an entity browser widget holding the given entities.
   *
   * @param \Drupal\node\NodeInterface[] $entities
   *   The entities the field currently holds.
   *
   * @return array
   *   The altered element.
   */
  protected function alterWidget(array $entities): array {
    $element = [
      'entity_browser' => [
        '#type' => 'entity_browser',
        '#default_value' => $entities,
      ],
    ];
    $formState = new FormState();
    $context = [];

    edw_utilities_field_widget_single_element_form_alter($element, $formState, $context);

    return $element;
  }

  /**
   * Creates a page.
   *
   * @param string $title
   *   The page title.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved page.
   */
  protected function createPage(string $title) {
    $node = Node::create(['type' => 'page', 'title' => $title]);
    $node->save();

    return $node;
  }

}
