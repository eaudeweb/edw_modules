<?php

namespace Drupal\edw_group;

use Drupal\Core\Session\AccountInterface;
use Drupal\edw_group\Services\MeetingService;
use Drupal\group\Entity\GroupMembership;
use Drupal\node\NodeInterface;
use Drupal\node_access_grants\NodeAccessGrantsInterface;

/**
 * Handles node access using grants.
 */
class NodeGrants implements NodeAccessGrantsInterface {

  /**
   * The realm used to check view access.
   */
  const EDW_VIEW_REALM = 'edw:group:view';

  /**
   * The realm used to check update access.
   */
  const EDW_UPDATE_REALM = 'edw:group:update';

  /**
   * The realm used to check delete access.
   */
  const EDW_DELETE_REALM = 'edw:group:delete';

  /**
   * The meeting service.
   *
   * @var \Drupal\edw_group\Services\MeetingService
   */
  protected $meetingService;

  /**
   * Constructs a NodeGrants object.
   */
  public function __construct(MeetingService $meetingService) {
    $this->meetingService = $meetingService;
  }

  /**
   * {@inheritdoc}
   */
  public function accessRecords(NodeInterface $node) {
    $grants = [];
    if ($node->bundle() == 'event_section') {
      $grants = array_merge($grants, $this->getNodeMeetingSectionAccessRecords($node));
    }
    return $grants;
  }

  /**
   * Returns the grants to be written for a given meeting node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return array
   *   The access grants records.
   */
  protected function getNodeMeetingAccessRecords(NodeInterface $node) {
    if ($node->bundle() != 'event') {
      throw new \InvalidArgumentException();
    }
    $grants = [];
    $groups = $this->meetingService->getNodeGroups($node, 'update');
    foreach ($groups as $group) {
      $grants[] = [
        'realm' => static::EDW_UPDATE_REALM,
        'gid' => $group->id(),
        'grant_view' => 1,
        'grant_update' => 1,
        'grant_delete' => 0,
      ];
      $grants[] = [
        'realm' => static::EDW_DELETE_REALM,
        'gid' => $group->id(),
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 1,
      ];
    }
    return $grants;
  }

  /**
   * Returns the grants to be written for a given meeting section node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return array
   *   The access grants records.
   */
  protected function getNodeMeetingSectionAccessRecords(NodeInterface $node) {
    if ($node->bundle() != 'event_section') {
      throw new \InvalidArgumentException();
    }
    $grants = [];
    $groups = $this->meetingService->getNodeGroups($node, 'view');
    foreach ($groups as $group) {
      $grants[] = [
        'realm' => static::EDW_VIEW_REALM,
        'gid' => $group->id(),
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
      ];
    }

    $groups = $this->meetingService->getNodeGroups($node, 'update');
    foreach ($groups as $group) {
      $grants[] = [
        'realm' => static::EDW_UPDATE_REALM,
        'gid' => $group->id(),
        'grant_view' => 1,
        'grant_update' => 1,
        'grant_delete' => 0,
      ];
      $grants[] = [
        'realm' => static::EDW_DELETE_REALM,
        'gid' => $group->id(),
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 1,
      ];
    }
    return $grants;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function grants(AccountInterface $account, $operation) {
    if (!$account->isAuthenticated()) {
      return [];
    }
    /** @var \Drupal\group\Entity\GroupMembershipInterface[] $memberships */
    $memberships = GroupMembership::loadByUser($account);
    $grants = [];
    foreach ($memberships as $membership) {
      $grants[static::EDW_VIEW_REALM][] = $membership->getGroupId();
      $grants[static::EDW_UPDATE_REALM][] = $membership->getGroupId();
      $grants[static::EDW_DELETE_REALM][] = $membership->getGroupId();
    }
    return $grants;
  }

}
