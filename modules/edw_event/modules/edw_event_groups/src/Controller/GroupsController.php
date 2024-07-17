<?php

namespace Drupal\edw_event_groups\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Url;
use Drupal\group\Entity\Group;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for extra groups routes.
 */
class GroupsController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The message.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  private ?Request $currentRequest;

  /**
   * GroupsController constructor.
   *
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The messenger.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   THe current request stack.
   */
  public function __construct(Messenger $messenger, RequestStack $requestStack) {
    $this->messenger = $messenger;
    $this->currentRequest = $requestStack->getCurrentRequest();
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('request_stack')
    );
  }

  /**
   * Removes a group from a section.
   *
   * @param \Drupal\node\NodeInterface $section
   *   The meeting section.
   * @param \Drupal\group\Entity\Group $group
   *   The group.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response that redirects to the destination route.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function removeGroupFromSection(NodeInterface $section, Group $group) {
    $fieldGroups = $section->get('field_groups')->getValue();
    $targetIds = array_column($fieldGroups, 'target_id');
    $index = array_search($group->id(), $targetIds);

    if ($index !== FALSE) {
      unset($fieldGroups[$index]);
      $section->set('field_groups', $fieldGroups);
      $section->save();
      $this->messenger->addMessage('Successfully removed group from section.');
    }

    $params = [];
    if ($this->currentRequest) {
      $params = $this->currentRequest->query->all();
    }

    $redirectTo = $params['destination'] ?? '/admin/content';
    $url = Url::fromUserInput($redirectTo, ['absolute' => TRUE]);
    $response = new AjaxResponse();
    $response->addCommand(new RedirectCommand($url->toString()));

    return $response;
  }

}
