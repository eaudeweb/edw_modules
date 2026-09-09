<?php

/**
 * @file
 * Hooks provided by the edw_document module.
 */

/**
 * Alters field machine name used to get external links.
 *
 * @param string $machine_name
 *   The field's machine name.
 *
 * @see \Drupal\edw_document\Form\DownloadDocumentsForm::alterExternalLinkField()
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function hook_field_external_links_alter(string &$machine_name) {
}

/**
 * Alters field machine name used to get files.
 *
 * @param string $machine_name
 *   The field's machine name.
 *
 * @see \Drupal\edw_document\Form\DownloadDocumentsForm::alterExternalLinkField()
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function hook_field_files_alter(string &$machine_name) {
}

/**
 * Alters the document formats used by the download modal.
 *
 * Each format is a group of file extensions shown as one checkbox in the
 * download-documents modal, keyed by format id:
 * @code
 * 'document' => [
 *   'label' => 'DOC',
 *   'icon' => '/modules/custom/edw_modules/.../x-office-document.png',
 *   'extensions' => ['csv', 'doc', 'docx', 'odt', ...],
 * ]
 * @endcode
 *
 * @param array $formats
 *   The format definitions, keyed by format id.
 *
 * @see \Drupal\edw_document\Services\DocumentManager::getFormats()
 */
function hook_edw_document_formats_alter(array &$formats) {
  // One checkbox per office extension instead of grouped DOC/XLS/PPT.
  \Drupal\edw_document\Services\DocumentManager::splitFormatsByExtension($formats, [
    'doc', 'docx', 'docm', 'rtf', 'txt', 'xls', 'xlsx', 'ppt', 'pptx',
  ]);
}