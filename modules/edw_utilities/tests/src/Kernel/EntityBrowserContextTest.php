<?php

namespace Drupal\Tests\edw_utilities\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\edw_utilities\Plugin\views\argument_default\EntityBrowserWidgetContextNode;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\views\ViewExecutable;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests carrying the host node into an entity browser's view.
 *
 * An entity browser opens in an iframe or a modal, so the request behind it is
 * for the browser's own route, not for the page the editor was on. The node
 * they were editing survives only as the `original_path` query parameter —
 * which is what lets a browser show, say, only the documents of the meeting
 * being edited instead of every document on the site.
 *
 * @coversDefaultClass \Drupal\edw_utilities\Plugin\views\argument_default\EntityBrowserWidgetContextNode
 *
 * @group edw_utilities
 */
class EntityBrowserContextTest extends KernelTestBase {

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
    'views',
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
   * Tests that the node being edited becomes the view's argument.
   *
   * @covers ::getArgument
   */
  public function testTheNodeBeingEditedBecomesTheArgument(): void {
    $node = Node::create(['type' => 'page', 'title' => 'Fifth meeting']);
    $node->save();

    $this->assertEquals($node->id(), $this->argumentFor('/node/' . $node->id() . '/edit'));
  }

  /**
   * Tests that the node is found wherever it sits in the path.
   *
   * The original path can be an edit form, a layout screen or a sub-tab.
   *
   * @covers ::getArgument
   */
  public function testTheNodeIsFoundWhereverItSitsInThePath(): void {
    $node = Node::create(['type' => 'page', 'title' => 'Fifth meeting']);
    $node->save();
    $id = $node->id();

    foreach (["/node/$id", "/node/$id/edit", "/node/$id/documents/in-session", "/en/node/$id/edit"] as $path) {
      $this->assertEquals($id, $this->argumentFor($path), "The node is found in '$path'.");
    }
  }

  /**
   * Tests that a browser opened outside a node yields no argument.
   *
   * Returning NULL is what lets the view fall back to its own default rather
   * than filtering on a node that is not there.
   *
   * @covers ::getArgument
   */
  public function testBrowserOpenedOutsideNodeYieldsNoArgument(): void {
    $this->assertNull($this->argumentFor('/admin/content'));
    $this->assertNull($this->argumentFor(NULL));
    $this->assertNull($this->argumentFor(''));
  }

  /**
   * Tests that a node id that no longer exists yields no argument.
   *
   * @covers ::getArgument
   */
  public function testNodeIdThatNoLongerExistsYieldsNoArgument(): void {
    $this->assertNull($this->argumentFor('/node/999/edit'));
  }

  /**
   * Tests that the plugin only applies to entity browser displays.
   *
   * It reads a query parameter only entity browser sets, so offering it on an
   * ordinary page display would give site builders a silently empty argument.
   *
   * @covers ::access
   */
  public function testThePluginOnlyAppliesToEntityBrowserDisplays(): void {
    $this->assertTrue($this->pluginForDisplay('entity_browser')->access());
    $this->assertFalse($this->pluginForDisplay('page')->access());
    $this->assertFalse($this->pluginForDisplay('block')->access());
  }

  /**
   * Returns the argument derived for an original path.
   *
   * @param string|null $originalPath
   *   The path the editor came from, or NULL if it was not passed.
   *
   * @return string|null
   *   The node id, or NULL.
   */
  protected function argumentFor(?string $originalPath) {
    $query = $originalPath === NULL ? [] : ['original_path' => $originalPath];
    $request = Request::create('/entity-browser/modal/documents', 'GET', $query);

    $view = $this->createMock(ViewExecutable::class);
    $view->method('getRequest')->willReturn($request);

    $plugin = $this->plugin();
    $plugin->view = $view;

    return $plugin->getArgument();
  }

  /**
   * Returns the plugin attached to a view of the given display type.
   *
   * @param string $pluginId
   *   The display plugin id.
   *
   * @return \Drupal\edw_utilities\Plugin\views\argument_default\EntityBrowserWidgetContextNode
   *   The plugin.
   */
  protected function pluginForDisplay(string $pluginId): EntityBrowserWidgetContextNode {
    $display = new \stdClass();
    $display->pluginId = $pluginId;

    $view = $this->createMock(ViewExecutable::class);
    $view->method('getDisplay')->willReturn($display);

    $plugin = $this->plugin();
    $plugin->view = $view;

    return $plugin;
  }

  /**
   * Builds the plugin under test.
   *
   * @return \Drupal\edw_utilities\Plugin\views\argument_default\EntityBrowserWidgetContextNode
   *   The plugin.
   */
  protected function plugin(): EntityBrowserWidgetContextNode {
    return new EntityBrowserWidgetContextNode(
      [],
      'entity_browser_widget_context_nid',
      [],
      \Drupal::entityTypeManager()->getStorage('node')
    );
  }

}
