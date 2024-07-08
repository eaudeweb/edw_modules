<?php

namespace Drupal\edw_event\Services\Importer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeInterface;

/**
 * The meeting section importer class.
 */
class MeetingSectionImporter {

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * The paragraph storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $paragraphStorage;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * MeetingSectionImporter constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $channelFactory
   *   The logger factory.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactoryInterface $channelFactory) {
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->paragraphStorage = $entityTypeManager->getStorage('paragraph');
    $this->logger = $channelFactory->get('edw_event.meeting.section.importer');
  }

  /**
   * Returns a meeting entity for a number.
   *
   * @param string $meetingNumber
   *   The meeting number.
   *
   * @return \Drupal\node\NodeInterface
   *   The meeting.
   */
  protected function getMeeting(string $meetingNumber): NodeInterface {
    $meetings = $this->nodeStorage->loadByProperties([
      'type' => 'event',
      'field_number' => $meetingNumber,
    ]);
    return reset($meetings);
  }

  /**
   * Create a meeting section.
   *
   * @param string|int $meetingId
   *   The meeting id.
   * @param string $phase
   *   The phase (pre_session|in_session|post_session).
   * @param string $title
   *   The page title.
   * @param bool $status
   *   The page status.
   * @param bool $checkConstraints
   *   Whether to check constraints or not.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The created section if the section does not exist or NULL if page exists
   *   or something went wrong.
   */
  public function createMeetingSection(string|int $meetingId, string $phase, string $title, bool $status = TRUE, bool $checkConstraints = TRUE): mixed {
    $entities = $this->nodeStorage->loadByProperties([
      'field_event' => $meetingId,
      'field_event_section_phase' => $phase,
    ]);
    if ($entities) {
      $this->logger->notice(sprintf('A meeting with this phase {%s} section already exists for meeting %s.', $phase, $meetingId));
      return NULL;
    }
    /** @var \Drupal\node\Entity\Node $section */
    $section = $this->nodeStorage->create([
      'type' => 'event_section',
      'title' => $title,
      'field_event' => $meetingId,
      'field_event_section_phase' => $phase,
      'status' => $status,
    ]);
    /** @var \Drupal\paragraphs\Entity\Paragraph $viewReference */
    $viewReference = $this->createViewReferenceParagraph();
    $section->set('field_content', [
      'target_id' => $viewReference->id(),
      'target_revision_id' => $viewReference->getRevisionId(),
    ]);
    $validation = $section->validate();
    if ($checkConstraints && $validation->count() > 0) {
      $this->logger->error(sprintf("Error creating meeting section: %s for meeting %s with fields: %s", $title, $meetingId, implode(', ', $validation->getFieldNames())));
      return NULL;
    }
    $section->save();
    $this->logger->notice(sprintf('Meeting section created: %s for meeting id: %s', $title, $meetingId));
    return $section;
  }

  /**
   * {@inheritdoc}
   */
  protected function createViewReferenceParagraph() {
    $paragraph = $this->paragraphStorage->create([
      'type' => 'edw_view',
      'field_view' => [
        'target_id' => 'documents',
        'display_id' => 'by_phase',
        'data' => serialize([
          'title' => '0',
          'pager' => NULL,
          'offset' => NULL,
          'limit' => NULL,
          'argument' => NULL,
        ]),
      ],
      'status' => 1,
    ]);
    $paragraph->save();
    return $paragraph;
  }

}
