<?php

namespace Drupal\Tests\edw_event\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_event\Traits\EventTestTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\edw_event\Plugin\Derivative\DocumentsInSessionLocalTasks;
use Drupal\edw_event\Plugin\Derivative\MeetingDocumentsLocalTasks;
use Drupal\edw_event\Routing\DocumentsInSessionRoutes;
use Drupal\edw_event\Routing\ManageDocumentsRoutes;

/**
 * Tests the extension points edw_event.api.php documents.
 *
 * The manage-documents screens are meant to be extensible: a site adds a
 * phase of its own, or another way of grouping the in-session documents, by
 * implementing a hook. Those hooks are the module's published contract, so
 * they are exercised here through a real module implementing them rather
 * than through a mocked module handler.
 *
 * @group edw_event
 */
class MeetingDocumentsHooksTest extends KernelTestBase {

  use EventTestTrait;
  use MediaTypeCreationTrait;

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
    'file',
    'image',
    'media',
    'entity_clone',
    'edw_event',
    'edw_event_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'system', 'image', 'file', 'media']);

    $this->createMeetingContentTypes();
    $this->createMediaType('file', ['id' => 'document', 'label' => 'Document']);
    $this->createField('media', 'document', 'field_document_phase', 'list_string', [
      'allowed_values' => [
        'pre_session' => 'Pre-session',
        'in_session' => 'In-session',
        'post_session' => 'Post-session',
      ],
    ]);
  }

  /**
   * Tests that a site's extra phase gets a route of its own.
   *
   * @see hook_meeting_documents_phase_alter()
   */
  public function testSitesExtraPhaseGetsRoute(): void {
    $routes = $this->manageDocumentsRoutes()->routes();

    $this->assertArrayHasKey('edw_event.documents.annotated_agenda', $routes->all());
    $this->assertSame(
      '/node/{node}/documents/annotated-agenda',
      $routes->get('edw_event.documents.annotated_agenda')->getPath()
    );
  }

  /**
   * Tests that a site's extra phase gets a tab of its own.
   *
   * @see hook_meeting_documents_phase_alter()
   */
  public function testSitesExtraPhaseGetsTab(): void {
    $tasks = $this->localTasksDeriver()->getDerivativeDefinitions([]);

    $this->assertArrayHasKey('edw_event.documents.annotated_agenda', $tasks);
    $this->assertSame('Annotated agenda', $tasks['edw_event.documents.annotated_agenda']['title']);
  }

  /**
   * Tests that a site can remove a tab it does not want.
   *
   * @see hook_meeting_documents_local_tasks_alter()
   */
  public function testSiteCanRemoveTabItDoesNotWant(): void {
    $tasks = $this->localTasksDeriver()->getDerivativeDefinitions([]);

    $this->assertArrayNotHasKey('edw_event.documents.post_session', $tasks);
    // The others survive.
    $this->assertArrayHasKey('edw_event.documents.pre_session', $tasks);
  }

  /**
   * Tests that every derived tab points at a route that exists.
   *
   * A local task naming a route that was never registered is a fatal on every
   * page the tab renders on, so the two lists have to be built from the same
   * phases — including the ones a site added.
   */
  public function testEveryDerivedTabPointsAtRouteThatExists(): void {
    $routes = array_keys($this->manageDocumentsRoutes()->routes()->all());
    $tasks = $this->localTasksDeriver()->getDerivativeDefinitions([]);

    foreach ($tasks as $id => $task) {
      $this->assertContains($task['route_name'], $routes, "The '$id' tab points at a route that exists.");
    }
  }

  /**
   * Tests that a site's extra in-session grouping gets a route of its own.
   *
   * @see hook_meeting_documents_in_session_groups()
   */
  public function testSitesExtraInSessionGroupingGetsRoute(): void {
    $routes = $this->inSessionRoutes()->routes();

    $this->assertArrayHasKey('edw_event.documents.in_session.contact_group', $routes->all());
    $this->assertSame(
      '/node/{node}/documents/in-session/contact-group',
      $routes->get('edw_event.documents.in_session.contact_group')->getPath()
    );
  }

  /**
   * Tests that a site's extra in-session grouping gets a tab of its own.
   *
   * @see hook_meeting_documents_in_session_groups()
   */
  public function testSitesExtraInSessionGroupingGetsTab(): void {
    $deriver = new DocumentsInSessionLocalTasks(
      \Drupal::service('edw_event.meeting.documents.service'),
      \Drupal::service('module_handler')
    );

    $tasks = $deriver->getDerivativeDefinitions([]);

    $this->assertArrayHasKey('edw_event.documents.in_session.contact_group', $tasks);
  }

  /**
   * Builds the phase routes with the real module handler.
   *
   * @return \Drupal\edw_event\Routing\ManageDocumentsRoutes
   *   The route builder.
   */
  protected function manageDocumentsRoutes(): ManageDocumentsRoutes {
    return new ManageDocumentsRoutes(
      \Drupal::service('edw_event.meeting.documents.service'),
      \Drupal::service('module_handler')
    );
  }

  /**
   * Builds the in-session grouping routes with the real module handler.
   *
   * @return \Drupal\edw_event\Routing\DocumentsInSessionRoutes
   *   The route builder.
   */
  protected function inSessionRoutes(): DocumentsInSessionRoutes {
    return new DocumentsInSessionRoutes(
      \Drupal::service('edw_event.meeting.documents.service'),
      \Drupal::service('module_handler')
    );
  }

  /**
   * Builds the phase local task deriver with the real module handler.
   *
   * @return \Drupal\edw_event\Plugin\Derivative\MeetingDocumentsLocalTasks
   *   The deriver.
   */
  protected function localTasksDeriver(): MeetingDocumentsLocalTasks {
    return new MeetingDocumentsLocalTasks(
      \Drupal::service('edw_event.meeting.documents.service'),
      \Drupal::service('module_handler')
    );
  }

}
