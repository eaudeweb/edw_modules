<?php

namespace Drupal\edw_event_agenda\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Document form.
 */
class MeetingDocumentForm implements ContainerInjectionInterface {

  use DependencySerializationTrait;

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a MeetingAgendaForm form object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The current request.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(RequestStack $request_stack, EntityTypeManagerInterface $entity_type_manager) {
    $this->requestStack = $request_stack;
    $this->nodeStorage = $entity_type_manager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alter(&$form, FormStateInterface $form_state) {
    $request = $this->requestStack->getCurrentRequest();
    $meetingId = $request->get('nid');
    if (empty($meetingId)) {
      return;
    }
    if ($this->requestStack->getCurrentRequest()->query->has(FormBuilderInterface::AJAX_FORM_REQUEST)) {
      $form_state->setRebuild();
    }
    $form['field_meetings']['widget']['target_id']['#default_value'] = 'node:' . $meetingId;
    $form['field_meetings']['widget']['#disabled'] = TRUE;
    $agendaId = $request->get('field_agenda');
    if (isset($agendaId)) {
      $form['field_agenda']['widget']['#default_value'] = [$agendaId];
      $form['field_agenda']['widget']['#disabled'] = TRUE;
    }
    $documentType = $request->get('field_document_types');
    if (isset($documentType)) {
      $form['field_document_types']['widget']['#default_value'] = [$documentType];
    }
    $phase = $request->get('field_document_phase');
    if (isset($phase)) {
      $form['field_document_phase']['widget']['#default_value'] = [$phase];
      $form['actions']['submit']['#submit'][] = [$this, 'formRedirect'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function formRedirect(array &$form, FormStateInterface $form_state) {
    $request = $this->requestStack->getCurrentRequest();
    $meetingId = $request->get('nid');
    $phase = $request->get('field_document_phase');
    $phase = $this->getDocumentPhase($phase, $form_state);
    $agendaId = $request->get('field_agenda');
    $options = [
      'fragment' => "$agendaId",
    ];
    $form_state->setRedirect("edw_event.documents.$phase", ['node' => $meetingId], $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentPhase($phase, FormStateInterface $form_state) {
    $selectedPhase = $form_state->getUserInput()['field_document_phase'];
    if (empty($selectedPhase)) {
      return $phase;
    }
    return (is_array($selectedPhase)) ? reset($selectedPhase) : $selectedPhase;
  }

}
