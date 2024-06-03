<?php

namespace Drupal\edw_group\Services;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupType;
use Drupal\node\NodeInterface;

/**
 * Meeting related functionalities.
 */
class MeetingService {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * The group storage.
   *
   * @var \Drupal\group\Entity\Storage\GroupStorage|\Drupal\Core\Entity\EntityStorageInterface
   */
  protected $groupStorage;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a MeetingService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity field manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactoryInterface $loggerChannelFactory, EntityFieldManagerInterface $entityFieldManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->groupStorage = $entityTypeManager->getStorage('group');
    $this->logger = $loggerChannelFactory->get('edw_group');
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * Check if all required elements are available.
   *
   * @return bool
   *   If group type and field exists and are correct, return TRUE, otherwise
   *   FALSE.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function isAllowed(): bool {
    $groupType = $this->entityTypeManager->getStorage('group_type')->load('event');
    if (!$groupType instanceof GroupType) {
      $this->logger->error('Group type event is missing.');
      return FALSE;
    }
    $field_definitions = $this->entityFieldManager->getFieldDefinitions('group', 'event');
    if (!isset($field_definitions['field_event'])) {
      $this->logger->error('Field field_event is missing.');
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Get groups that are linked to the node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to get the groups for.
   * @param string $operation
   *   The operation that is to be performed on $entity. Usually one of:
   *   - "view"
   *   - "update"
   *   - "delete".
   *
   * @return \Drupal\group\Entity\GroupInterface[]
   *   An array with the groups linked to the node.
   */
  public function getNodeGroups(NodeInterface $node, $operation): array {
    if (!in_array($node->bundle(), ['event', 'event_section'])) {
      throw new \InvalidArgumentException();
    }
    if (!in_array($operation, ['view', 'update', 'delete'])) {
      throw new \InvalidArgumentException();
    }
    $field = $operation == 'view' ? 'field_groups' : 'field_moderator_groups';
    if (!$node->hasField($field)) {
      return [];
    }
    $groupIds = $node->get($field)->getValue();
    $groups = [];
    foreach ($groupIds as $groupInfo) {
      if (empty($groupInfo['target_id'])) {
        continue;
      }
      $group = $this->groupStorage->load($groupInfo['target_id']);
      if ($group instanceof GroupInterface) {
        $groups[] = $group;
      }
    }
    return $groups;
  }

  /**
   * Get the group(s) that were created for the meeting.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The meeting node.
   *
   * @return \Drupal\group\Entity\GroupInterface[]
   *   The group entities.
   */
  public function getGroupsCreatedForMeeting(NodeInterface $node): array {
    if ($node->bundle() != 'event') {
      throw new \InvalidArgumentException();
    }
    /** @var \Drupal\group\Entity\GroupInterface[] $groups */
    $groups = $this->groupStorage->loadByProperties([
      'type' => 'event',
      'field_event' => $node->id(),
    ]);
    return $groups;
  }

  /**
   * Create the group for the meeting.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The meeting node.
   */
  public function createGroupForMeeting(NodeInterface $node): void {
    $group = $this->groupStorage->create([
      'uid' => $node->getOwnerId() ?: 1,
      'type' => 'event',
      'label' => $node->label(),
      'field_event' => $node->id(),
    ]);
    $group->save();
  }

  /**
   * Deletes the groups associated with a meeting section.
   *
   * @param \Drupal\node\NodeInterface $section
   *   The meeting $section.
   */
  public function deleteMeetingSectionGroups(NodeInterface $section): void {
    if ($section->bundle() != 'event_section') {
      return;
    }
    $viewGroups = $section->get('field_groups')->referencedEntities();
    $moderatorGroups = $section->get('field_moderator_groups')
      ->referencedEntities();
    $groups = array_merge($viewGroups, $moderatorGroups);
    foreach ($groups as $group) {
      $group->delete();
    }
  }


}
