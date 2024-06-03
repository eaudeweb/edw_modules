<?php

namespace Drupal\edw_event\Commands;

use Drupal\edw_event\Services\Importer\MeetingSectionImporter;
use Drush\Commands\DrushCommands;

/**
 * Class MeetingsCommands for meetings and meeting sections.
 */
class MeetingCommands extends DrushCommands {

  /**
   * The Meeting section importer.
   *
   * @var \Drupal\edw_event\Services\Importer\MeetingSectionImporter
   */
  protected $sectionImporter;

  /**
   * Constructs a new MeetingsCommands object.
   *
   * @param \Drupal\edw_event\Services\Importer\MeetingSectionImporter $sectionImporter
   *   The database connection.
   */
  public function __construct(MeetingSectionImporter $sectionImporter) {
    parent::__construct();
    $this->sectionImporter = $sectionImporter;
  }

  /**
   * Create a meeting section with phase.
   *
   * @param int $meetingId
   *   The meeting id.
   * @param string $phase
   *   The phase (pre_session|in_session|post_session).
   * @param string $title
   *   The page title.
   * @param bool $status
   *   (optional) The page status.
   *
   * @command edw_event:add-meeting-section
   * @aliases section-add
   */
  public function createMeetingSection(int $meetingId, string $phase, string $title, bool $status) {
    $this->sectionImporter->createMeetingSection($meetingId, $phase, $title, $status);
  }

}
