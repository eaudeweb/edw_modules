<?php

namespace Drupal\Tests\edw_event\Unit;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\edw_event\Routing\ManageDocumentsRoutes;
use Drupal\edw_event\Services\MeetingDocumentsService;

/**
 * Tests the per-phase "Manage documents" routes.
 *
 * A meeting's documents are managed one phase at a time, and the phases are
 * not fixed: they come from the allowed values of the document media type's
 * phase field, and a site can add more through an alter hook. So the routes
 * cannot be declared in edw_event.routing.yml — they are built from whatever
 * phases the site actually has.
 *
 * @coversDefaultClass \Drupal\edw_event\Routing\ManageDocumentsRoutes
 *
 * @group edw_event
 */
class ManageDocumentsRoutesTest extends UnitTestCase {

  /**
   * The module handler, which carries the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
  }

  /**
   * Tests that one route is built for each configured phase.
   *
   * @covers ::routes
   */
  public function testOneRouteIsBuiltPerPhase(): void {
    $routes = $this->routesFor([
      'pre_session' => 'Pre-session',
      'in_session' => 'In-session',
      'post_session' => 'Post-session',
    ]);

    $this->assertSame([
      'edw_event.documents.pre_session',
      'edw_event.documents.in_session',
      'edw_event.documents.post_session',
    ], array_keys($routes->all()));
  }

  /**
   * Tests that the phase's underscores become hyphens in the path.
   *
   * The phase id is a machine name, but it is also the last segment of a URL
   * an editor sees and bookmarks.
   *
   * @covers ::routes
   */
  public function testUnderscoresBecomeHyphensInThePath(): void {
    $routes = $this->routesFor(['pre_session' => 'Pre-session']);

    $this->assertSame(
      '/node/{node}/documents/pre-session',
      $routes->get('edw_event.documents.pre_session')->getPath()
    );
  }

  /**
   * Tests that each route is locked to editable meetings.
   *
   * These are admin screens for managing a meeting's documents, so they have
   * to be restricted to nodes of the 'event' bundle that the visitor may edit
   * — not to any node id that happens to be in the URL.
   *
   * @covers ::routes
   */
  public function testEachRouteIsRestrictedToEditableMeetings(): void {
    $route = $this->routesFor(['in_session' => 'In-session'])
      ->get('edw_event.documents.in_session');

    $this->assertSame('node.update', $route->getRequirement('_entity_access'));
    $this->assertSame('\d+', $route->getRequirement('node'));
    $this->assertSame([
      'node' => [
        'type' => 'entity:node',
        'bundle' => ['event'],
      ],
    ], $route->getOption('parameters'));
    $this->assertTrue($route->getOption('_admin_route'));
  }

  /**
   * Tests that the phase title becomes the page title.
   *
   * @covers ::routes
   */
  public function testThePhaseTitleBecomesThePageTitle(): void {
    $route = $this->routesFor(['post_session' => 'Post-session'])
      ->get('edw_event.documents.post_session');

    $this->assertSame('Post-session', $route->getDefault('_title'));
    $this->assertSame(
      'Drupal\edw_event\Controller\ManageDocumentsController::blankPage',
      $route->getDefault('_controller')
    );
  }

  /**
   * Tests that a site with no phase field gets no routes.
   *
   * The document media type is optional, so a site can install edw_event
   * without one. That must leave the route collection empty rather than
   * producing a broken route.
   *
   * @covers ::routes
   */
  public function testNoPhasesProducesNoRoutes(): void {
    $this->assertCount(0, $this->routesFor([]));
  }

  /**
   * Tests that sites are offered the chance to add phases.
   *
   * @covers ::routes
   */
  public function testSitesCanAlterThePhaseList(): void {
    $phases = ['pre_session' => 'Pre-session'];

    $this->moduleHandler->expects($this->once())
      ->method('alter')
      ->with('meeting_documents_phase', $phases);

    $this->routesFor($phases);
  }

  /**
   * Builds the route collection for a given set of phases.
   *
   * @param array $phases
   *   Phase id to title.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   The generated routes.
   */
  protected function routesFor(array $phases) {
    $documents = $this->createMock(MeetingDocumentsService::class);
    $documents->method('getDocumentPhases')->willReturn($phases);

    return (new ManageDocumentsRoutes($documents, $this->moduleHandler))->routes();
  }

}
