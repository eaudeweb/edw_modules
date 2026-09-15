<?php

namespace Drupal\Tests\edw_event\Unit;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\edw_event\Plugin\Derivative\DocumentsInSessionLocalTasks;
use Drupal\edw_event\Plugin\Derivative\MeetingDocumentsLocalTasks;
use Drupal\edw_event\Services\MeetingDocumentsService;

/**
 * Tests the tabs shown on a meeting's "Manage documents" screens.
 *
 * The tabs have to line up one-for-one with the dynamically generated routes:
 * a tab whose route_name does not exist is a fatal on every page it renders
 * on, and a route with no tab is a screen editors cannot reach.
 *
 * @coversDefaultClass \Drupal\edw_event\Plugin\Derivative\MeetingDocumentsLocalTasks
 *
 * @group edw_event
 */
class MeetingDocumentsLocalTasksTest extends UnitTestCase {

  /**
   * The module handler, which carries the alter hooks.
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
   * Tests that one tab is derived for each phase.
   *
   * @covers ::getDerivativeDefinitions
   */
  public function testOneTabIsDerivedPerPhase(): void {
    $tasks = $this->tasksFor([
      'pre_session' => 'Pre-session',
      'in_session' => 'In-session',
      'post_session' => 'Post-session',
    ]);

    $this->assertSame([
      'edw_event.documents.pre_session',
      'edw_event.documents.in_session',
      'edw_event.documents.post_session',
    ], array_keys($tasks));
  }

  /**
   * Tests that each tab points at the route of the same name.
   *
   * @covers ::getDerivativeDefinitions
   */
  public function testEachTabPointsAtItsOwnRoute(): void {
    $tasks = $this->tasksFor(['in_session' => 'In-session']);

    $this->assertSame('edw_event.documents.in_session', $tasks['edw_event.documents.in_session']['route_name']);
    $this->assertSame('In-session', $tasks['edw_event.documents.in_session']['title']);
  }

  /**
   * Tests that the tabs hang off the Manage Documents tab.
   *
   * @covers ::getDerivativeDefinitions
   */
  public function testTheTabsHangOffTheManageDocumentsTab(): void {
    $tasks = $this->tasksFor(['pre_session' => 'Pre-session']);

    $this->assertSame(
      'edw_event.documents.settings',
      $tasks['edw_event.documents.pre_session']['parent_id']
    );
  }

  /**
   * Tests that the tabs keep the order the phases were declared in.
   *
   * Local tasks are rendered by weight, so without an increasing weight the
   * phases would come out in an arbitrary order — and "post-session" showing
   * before "pre-session" is a confusing screen.
   *
   * @covers ::getDerivativeDefinitions
   */
  public function testTheTabsKeepThePhaseOrder(): void {
    $tasks = $this->tasksFor([
      'pre_session' => 'Pre-session',
      'in_session' => 'In-session',
      'post_session' => 'Post-session',
    ]);

    $this->assertSame(1, $tasks['edw_event.documents.pre_session']['weight']);
    $this->assertSame(2, $tasks['edw_event.documents.in_session']['weight']);
    $this->assertSame(3, $tasks['edw_event.documents.post_session']['weight']);
  }

  /**
   * Tests that the base plugin definition is carried into each tab.
   *
   * @covers ::getDerivativeDefinitions
   */
  public function testTheBasePluginDefinitionIsCarriedThrough(): void {
    $tasks = $this->tasksFor(['pre_session' => 'Pre-session'], ['class' => 'SomeLocalTaskClass']);

    $this->assertSame('SomeLocalTaskClass', $tasks['edw_event.documents.pre_session']['class']);
  }

  /**
   * Tests that sites are offered the chance to alter the derived tabs.
   *
   * Both hooks fire: one shapes the phase list, the other the finished tabs.
   *
   * @covers ::getDerivativeDefinitions
   */
  public function testSitesCanAlterThePhasesAndTheTabs(): void {
    $altered = [];
    $this->moduleHandler->expects($this->exactly(2))
      ->method('alter')
      ->willReturnCallback(function ($hook) use (&$altered) {
        $altered[] = $hook;
      });

    $this->tasksFor(['pre_session' => 'Pre-session']);

    $this->assertSame(['meeting_documents_phase', 'meeting_documents_local_tasks'], $altered);
  }

  /**
   * Tests the in-session grouping tab.
   *
   * There is one tab per grouping, sitting alongside the phase tabs.
   *
   * @covers \Drupal\edw_event\Plugin\Derivative\DocumentsInSessionLocalTasks::getDerivativeDefinitions
   */
  public function testTheInSessionGroupingGetsItsOwnTab(): void {
    $deriver = new DocumentsInSessionLocalTasks(
      $this->createMock(MeetingDocumentsService::class),
      $this->moduleHandler
    );

    $tasks = $deriver->getDerivativeDefinitions([]);

    $this->assertSame(['edw_event.documents.in_session.document_type'], array_keys($tasks));
    $task = $tasks['edw_event.documents.in_session.document_type'];
    $this->assertSame('edw_event.documents.in_session.document_type', $task['route_name']);
    $this->assertSame('In-session (documents)', $task['title']);
    $this->assertSame('edw_event.documents.settings', $task['parent_id']);
  }

  /**
   * Derives the local tasks for a given set of phases.
   *
   * @param array $phases
   *   Phase id to title.
   * @param array $basePluginDefinition
   *   The base local task plugin definition.
   *
   * @return array
   *   The derivative definitions.
   */
  protected function tasksFor(array $phases, array $basePluginDefinition = []): array {
    $documents = $this->createMock(MeetingDocumentsService::class);
    $documents->method('getDocumentPhases')->willReturn($phases);

    $deriver = new MeetingDocumentsLocalTasks($documents, $this->moduleHandler);

    return $deriver->getDerivativeDefinitions($basePluginDefinition);
  }

}
