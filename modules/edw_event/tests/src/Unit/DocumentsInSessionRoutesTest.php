<?php

namespace Drupal\Tests\edw_event\Unit;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\edw_event\Routing\DocumentsInSessionRoutes;
use Drupal\edw_event\Services\MeetingDocumentsService;

/**
 * Tests the in-session document grouping routes.
 *
 * The in-session phase holds far more documents than the others, so it gets
 * its own set of screens that group them — by agenda item, or by document
 * type — rather than one flat list.
 *
 * @coversDefaultClass \Drupal\edw_event\Routing\DocumentsInSessionRoutes
 *
 * @group edw_event
 */
class DocumentsInSessionRoutesTest extends UnitTestCase {

  /**
   * The routes under test.
   *
   * @var \Drupal\edw_event\Routing\DocumentsInSessionRoutes
   */
  protected $routes;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->routes = new DocumentsInSessionRoutes(
      $this->createMock(MeetingDocumentsService::class),
      $this->createMock(ModuleHandlerInterface::class)
    );
  }

  /**
   * Tests that both built-in groupings get a route.
   *
   * @covers ::routes
   */
  public function testBothBuiltInGroupingsGetRoute(): void {
    $routes = $this->routes->routes();

    $this->assertSame([
      'edw_event.documents.in_session.agenda_item',
      'edw_event.documents.in_session.document_type',
    ], array_keys($routes->all()));
  }

  /**
   * Tests that the groupings sit under the in-session path.
   *
   * MeetingDocumentForm redirects to these route names after a document is
   * saved, so the names and paths are a contract, not an implementation
   * detail.
   *
   * @covers ::routes
   */
  public function testTheGroupingsSitUnderTheInSessionPath(): void {
    $routes = $this->routes->routes();

    $this->assertSame(
      '/node/{node}/documents/in-session/agenda-item',
      $routes->get('edw_event.documents.in_session.agenda_item')->getPath()
    );
    $this->assertSame(
      '/node/{node}/documents/in-session/document-type',
      $routes->get('edw_event.documents.in_session.document_type')->getPath()
    );
  }

  /**
   * Tests that the grouping routes carry the same access rules as the phases.
   *
   * @covers ::routes
   */
  public function testTheGroupingRoutesAreRestrictedToEditableMeetings(): void {
    $route = $this->routes->routes()->get('edw_event.documents.in_session.document_type');

    $this->assertSame('node.update', $route->getRequirement('_entity_access'));
    $this->assertSame('\d+', $route->getRequirement('node'));
    $this->assertSame(['event'], $route->getOption('parameters')['node']['bundle']);
    $this->assertTrue($route->getOption('_admin_route'));
    $this->assertSame('Document type', $route->getDefault('_title'));
  }

}
