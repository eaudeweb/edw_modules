<?php

namespace Drupal\edw_event\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides a controller showing tabs for each phase of a meeting.
 */
class ManageDocumentsController extends ControllerBase {

  /**
   * Function used to render an empty page.
   *
   * @return array
   *   The render array.
   */
  public function blankPage() {
    return [];
  }

}
