<?php

namespace Drupal\edw_event\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Populate a meeting agenda.
 */
class MeetingAgendaForm implements ContainerInjectionInterface {

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * Constructs a MeetingAgendaForm form object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $current_request
   *   The current request.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(Request $current_request, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentRequest = $current_request;
    $this->nodeStorage = $entity_type_manager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alter(&$form, FormStateInterface $form_state) {
    $meetingId = $this->currentRequest->get('nid');
    if ($form['#form_id'] == 'taxonomy_term_event_agendas_form' && $meetingId) {
      $meeting = $this->nodeStorage->load($meetingId);
      $form['field_event']['widget'][0]['target_id']['#default_value'] = $meeting;
      $form['field_event']['widget'][0]['#disabled'] = TRUE;
      $form['path']['#access'] = FALSE;
      unset($form['actions']['overview']);
      $form['actions']['submit']['#submit'][] = [$this, 'formRedirect'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function formRedirect(array &$form, FormStateInterface $form_state) {
    $meetingId = $this->currentRequest->get('nid');
    $form_state->setRedirect('edw_event.meeting_agenda.settings', ['node' => $meetingId]);
  }

}
