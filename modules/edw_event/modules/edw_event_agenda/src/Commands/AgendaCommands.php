<?php

namespace Drupal\edw_event_agenda\Commands;

use Drupal\edw_event\Services\Importer\MeetingSectionImporter;
use Drupal\edw_event_agenda\Services\MeetingAgendaService;
use Drush\Commands\DrushCommands;

/**
 * Class MeetingsCommands for meetings and meeting sections.
 */
class AgendaCommands extends DrushCommands {
  /**
   * The MeetingAgenda service.
   *
   * @var \Drupal\edw_event_agenda\Services\MeetingAgendaService
   */
  private MeetingAgendaService $meetingAgendaService;

  /**
   * Constructs a new MeetingsCommands object.
   *
   * @param \Drupal\edw_event_agenda\Services\MeetingAgendaService $meetingAgendaService
   *   The database connection.
   */
  public function __construct(MeetingAgendaService $meetingAgendaService) {
    parent::__construct();
    $this->meetingAgendaService = $meetingAgendaService;
  }

  /**
   * Create a meeting section with phase.
   *
   * @param int $meetingId
   *   The meeting id.
   * @param string $agendaName
   *   The agenda's name.
   *
   * @command edw_event_agenda:add-default-agenda
   * @aliases default-agenda-add
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createDefaultAgenda(int $meetingId, string $agendaName) {
    $this->meetingAgendaService->createDefaultAgenda($meetingId, $agendaName);
  }

}
