<?php

namespace Drupal\Tests\edw_blocks\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\edw_blocks\Plugin\Block\EdwSearchBlock;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Symfony\Component\Routing\Route;

/**
 * Tests hiding the page title and breadcrumb behind a hero banner.
 *
 * A page with a banner image renders its own title and breadcrumb inside the
 * banner. Leaving the site-wide title and breadcrumb blocks in place as well
 * would show both, one above the other — so those blocks are suppressed on
 * exactly the pages the banner takes over.
 *
 * @group edw_blocks
 */
class HeroBannerBlockVisibilityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'file',
    'image',
    'node',
    'views',
    'edw_blocks',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('node', ['node_access']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_banner_image',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'page',
      'field_name' => 'field_banner_image',
      'label' => 'Banner image',
    ])->save();
  }

  /**
   * Tests that the title and breadcrumb give way to a banner.
   */
  public function testTheTitleAndBreadcrumbGiveWayToBanner(): void {
    $node = $this->createPage(['field_banner_image' => 'banner.jpg']);

    foreach (['page_title_block', 'system_breadcrumb_block'] as $pluginId) {
      $variables = $this->preprocess($pluginId, $node);
      $this->assertFalse($variables['content']['#access'], "The '$pluginId' block is hidden.");
    }
  }

  /**
   * Tests that a page without a banner keeps its title and breadcrumb.
   */
  public function testPageWithoutBannerKeepsItsTitleAndBreadcrumb(): void {
    $node = $this->createPage();

    $variables = $this->preprocess('page_title_block', $node);

    $this->assertArrayNotHasKey('#access', $variables['content']);
  }

  /**
   * Tests that content types without the field are unaffected.
   */
  public function testContentTypesWithoutTheFieldAreUnaffected(): void {
    $article = Node::create(['type' => 'article', 'title' => 'An article']);
    $article->save();

    $variables = $this->preprocess('page_title_block', $article);

    $this->assertArrayNotHasKey('#access', $variables['content']);
  }

  /**
   * Tests that pages which are not nodes are unaffected.
   *
   * The title block renders on every route, including admin screens where
   * there is no node at all.
   */
  public function testPagesWhichAreNotNodesAreUnaffected(): void {
    $variables = $this->preprocess('page_title_block', NULL);

    $this->assertArrayNotHasKey('#access', $variables['content']);
  }

  /**
   * Tests that other blocks are never touched.
   */
  public function testOtherBlocksAreNeverTouched(): void {
    $node = $this->createPage(['field_banner_image' => 'banner.jpg']);

    $variables = $this->preprocess('system_menu_block:main', $node);

    $this->assertArrayNotHasKey('#access', $variables['content']);
  }

  /**
   * Tests that the search views block is swapped for the EDW one.
   *
   * The extra class adds a content type filter to the block's settings; the
   * swap is what makes an ordinary views block pick it up.
   */
  public function testTheSearchBlockIsSwappedForTheEdwOne(): void {
    $definitions = [
      'views_block:search-block_1' => ['class' => 'Drupal\views\Plugin\Block\ViewsBlock'],
      'views_block:other-block_1' => ['class' => 'Drupal\views\Plugin\Block\ViewsBlock'],
    ];

    edw_blocks_block_alter($definitions);

    $this->assertSame(EdwSearchBlock::class, $definitions['views_block:search-block_1']['class']);
    $this->assertSame(
      'Drupal\views\Plugin\Block\ViewsBlock',
      $definitions['views_block:other-block_1']['class'],
      'Other views blocks keep their own class.'
    );
  }

  /**
   * Tests that a site without the search view is left alone.
   */
  public function testSiteWithoutTheSearchViewIsLeftAlone(): void {
    $definitions = ['views_block:other-block_1' => ['class' => 'Drupal\views\Plugin\Block\ViewsBlock']];

    edw_blocks_block_alter($definitions);

    $this->assertSame(['views_block:other-block_1'], array_keys($definitions));
  }

  /**
   * Tests that the hero banner has a template to render into.
   */
  public function testTheHeroBannerHasTemplate(): void {
    $registry = \Drupal::service('theme.registry')->get();

    $this->assertArrayHasKey('edw_hero_banner_block', $registry);
    $this->assertArrayHasKey('title', $registry['edw_hero_banner_block']['variables']);
    $this->assertArrayHasKey('banner_image', $registry['edw_hero_banner_block']['variables']);
  }

  /**
   * Runs the block preprocess for a plugin on a page showing a node.
   *
   * @param string $pluginId
   *   The block plugin being rendered.
   * @param \Drupal\node\NodeInterface|null $node
   *   The node in the route, or NULL when the page is not a node.
   *
   * @return array
   *   The preprocess variables.
   */
  protected function preprocess(string $pluginId, ?NodeInterface $node): array {
    $parameters = $node ? ['node' => $node] : [];
    $raw = $node ? ['node' => $node->id()] : [];
    \Drupal::service('current_route_match')->resetRouteMatch();
    \Drupal::requestStack()->getCurrentRequest()->attributes->set('_route_object', new Route('/node/{node}'));
    if ($node) {
      \Drupal::requestStack()->getCurrentRequest()->attributes->set('node', $node);
    }

    $variables = [
      'plugin_id' => $pluginId,
      'content' => ['#markup' => 'Block content'],
    ];
    edw_blocks_preprocess_block($variables);

    return $variables;
  }

  /**
   * Creates a page.
   *
   * @param array $values
   *   Extra field values, such as the banner image.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved page.
   */
  protected function createPage(array $values = []): NodeInterface {
    $node = Node::create(['type' => 'page', 'title' => 'Fifth meeting'] + $values);
    $node->save();

    return $node;
  }

}
