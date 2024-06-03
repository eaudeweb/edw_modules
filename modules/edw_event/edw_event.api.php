<?php

/**
 * @file
 * Hooks and documentation related to the event and its tabs.
 */

/**
 * Alter Document local tasks.
 *
 * @param array $local_tasks
 *   The array of local tasks plugin definitions, keyed by route.
 *
 * @see \Drupal\edw_event\Plugin\Derivative\MeetingDocumentsLocalTasks::getDerivativeDefinitions()
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function hook_meeting_documents_local_tasks_alter(&$local_tasks) {
}

/**
 * Alters tabs and add new section specific for the website.
 *
 * @param array $tabs
 *   An associative array containing list of tabs as key and title.
 *
 * @see \Drupal\edw_event\Plugin\Derivative\MeetingDocumentsLocalTasks::getDerivativeDefinitions()
 * @see \Drupal\edw_event\Routing\ManageDocumentsRoutes::getDocumentPhase()
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function hook_meeting_documents_phase_alter(array &$tabs) {
  $tabs['annotated_agenda'] = 'Annotated agenda';
}
