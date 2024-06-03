<?php

namespace Drupal\edw_event\Plugin\views\argument_default;

use Drupal\node\NodeInterface;
use Drupal\node\Plugin\views\argument_default\Node;

/**
 * Default argument plugin to extract a meeting.
 *
 * @ViewsArgumentDefault(
 *   id = "event_id",
 *   title = @Translation("Meeting ID from URL")
 * )
 */
class Meeting extends Node {

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    $node = $this->routeMatch->getParameter('node');
    if (!empty($node) && $node instanceof NodeInterface && $this->isAllowed($node)) {
      return $node->get('field_event')->target_id;
    }
  }

  /**
   * Checks whether this entity is explicitly allowed.
   *
   * @return bool
   *   Return TRUE if this entity has a meeting.
   */
  public function isAllowed(NodeInterface $node) {
    if ($node->hasField('field_event')
      && !$node->get('field_event')->isEmpty()) {
      return TRUE;
    }
    return FALSE;
  }

}
