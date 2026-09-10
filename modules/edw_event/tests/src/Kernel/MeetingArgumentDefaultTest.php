<?php

namespace Drupal\Tests\edw_event\Kernel;

use Drupal\Core\Routing\RouteMatch;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_event\Traits\EventTestTrait;
use Drupal\edw_event\Plugin\views\argument_default\Meeting;
use Drupal\node\Entity\Node;
use Symfony\Component\Routing\Route;

/**
 * Tests the "Meeting ID from URL" views argument.
 *
 * The manage-documents screens live at /node/{node}/documents/..., where the
 * node in the URL is the meeting. But the views embedded in a *section* are
 * rendered at the section's own URL, and they need the meeting behind it —
 * so this argument follows field_event rather than taking the node id
 * directly, which is what core's Node argument would give.
 *
 * @coversDefaultClass \Drupal\edw_event\Plugin\views\argument_default\Meeting
 *
 * @group edw_event
 */
class MeetingArgumentDefaultTest extends KernelTestBase {

  use EventTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'options',
    'filter',
    'node',
    'taxonomy',
    'views',
    'entity_clone',
    'edw_event',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);

    $this->createMeetingContentTypes();
  }

  /**
   * Tests that a section resolves to the meeting it belongs to.
   *
   * @covers ::getArgument
   * @covers ::isAllowed
   */
  public function testSectionResolvesToItsMeeting(): void {
    $meeting = $this->createMeeting();
    $section = $this->createMeetingSection($meeting, 'In-session', 'in_session');

    $this->assertEquals($meeting->id(), $this->argumentFor($section));
  }

  /**
   * Tests that a node with no meeting field yields no argument.
   *
   * The same view can be placed on a page whose node is not a section. An
   * argument of NULL is what makes views fall back to its "no results"
   * behaviour rather than listing every meeting's documents.
   *
   * @covers ::getArgument
   * @covers ::isAllowed
   */
  public function testNodeWithNoMeetingFieldYieldsNoArgument(): void {
    $meeting = $this->createMeeting();

    $this->assertNull($this->argumentFor($meeting));
  }

  /**
   * Tests that a section whose meeting is unset yields no argument.
   *
   * @covers ::getArgument
   * @covers ::isAllowed
   */
  public function testSectionWithNoMeetingYieldsNoArgument(): void {
    $orphan = Node::create([
      'type' => 'event_section',
      'title' => 'Orphaned section',
    ]);
    $orphan->save();

    $this->assertNull($this->argumentFor($orphan));
  }

  /**
   * Tests that a route with no node yields no argument.
   *
   * @covers ::getArgument
   */
  public function testRouteWithNoNodeYieldsNoArgument(): void {
    $routeMatch = new RouteMatch('some.route', new Route('/some/path'), [], []);
    $plugin = new Meeting([], 'event_id', [], $routeMatch);

    $this->assertNull($plugin->getArgument());
  }

  /**
   * Tests that a node parameter that is not a node yields no argument.
   *
   * Upcasting can be skipped — on an admin route with a raw id, for instance
   * — leaving a bare string where the node was expected.
   *
   * @covers ::getArgument
   */
  public function testNodeParameterThatIsNotNodeYieldsNoArgument(): void {
    $routeMatch = new RouteMatch(
      'entity.node.canonical',
      new Route('/node/{node}'),
      ['node' => '7'],
      ['node' => '7']
    );
    $plugin = new Meeting([], 'event_id', [], $routeMatch);

    $this->assertNull($plugin->getArgument());
  }

  /**
   * Returns the argument the plugin derives for a node in the route.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node in the route.
   *
   * @return string|null
   *   The meeting id, or NULL.
   */
  protected function argumentFor($node) {
    $routeMatch = new RouteMatch(
      'entity.node.canonical',
      new Route('/node/{node}'),
      ['node' => $node],
      ['node' => $node->id()]
    );

    return (new Meeting([], 'event_id', [], $routeMatch))->getArgument();
  }

}
