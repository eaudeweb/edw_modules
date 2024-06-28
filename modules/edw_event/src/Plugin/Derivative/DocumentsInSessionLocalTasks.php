<?php

namespace Drupal\edw_event\Plugin\Derivative;

/**
 * Generates local tasks to manage documents under In-session.
 */
class DocumentsInSessionLocalTasks extends MeetingDocumentsLocalTasks {

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.LongVariable)
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $groups = [
      'document_type' => 'In-session (documents)',
    ];
    $this->moduleHandler->invokeAll('meeting_documents_in_session_groups', $groups);
    foreach ($groups as $groupBy => $title) {
      $this->derivatives["edw_event.documents.in_session.$groupBy"] = [
        'route_name' => "edw_event.documents.in_session.$groupBy",
        'title' => $title,
        'parent_id' => "edw_event.documents.settings",
        'weight' => 2,
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
