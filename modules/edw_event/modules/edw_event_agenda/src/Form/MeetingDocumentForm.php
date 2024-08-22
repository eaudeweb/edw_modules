<?php

namespace Drupal\edw_event_agenda\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Document form.
 */
class MeetingDocumentForm implements ContainerInjectionInterface {

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
   * Keep only the query parameters of the current request in order to avoid
   * serializing the entire request object.
   */
  public function __sleep() {
    foreach (get_object_vars($this) as $key => $value) {
      if ($value instanceof Request) {
        $this->{$key} = $value->query;
      }
    }
    return ['currentRequest', 'nodeStorage'];
  }

  /**
   * {@inheritdoc}
   */
  public function alter(&$form, FormStateInterface $form_state) {
    $meetingId = $this->currentRequest->get('nid');
    if (empty($meetingId)) {
      return;
    }
    $form['field_meetings']['widget']['target_id']['#default_value'] = 'node:' . $meetingId;
    $form['field_meetings']['widget']['#disabled'] = TRUE;
    $agendaId = $this->currentRequest->get('field_agenda');
    if (isset($agendaId)) {
      $form['field_agenda']['widget']['#default_value'] = [$agendaId];
      $form['field_agenda']['widget']['#disabled'] = TRUE;
    }
    $documentType = $this->currentRequest->get('field_document_types');
    if (isset($documentType)) {
      $form['field_document_types']['widget']['#default_value'] = [$documentType];
    }
    $phase = $this->currentRequest->get('field_document_phase');
    if (isset($phase)) {
      $form['field_document_phase']['widget']['#default_value'] = [$phase];
      $form['actions']['submit']['#submit'][] = [$this, 'formRedirect'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function formRedirect(array &$form, FormStateInterface $form_state) {
    $meetingId = $this->currentRequest->get('nid');
    $phase = $this->currentRequest->get('field_document_phase');
    $phase = $this->getDocumentPhase($phase, $form_state);
    $agendaId = $this->currentRequest->get('field_agenda');
    $formDocType = $form_state->getValue('field_document_types') ?? [];
    $docTypeId = $this->currentRequest->get('field_document_types') ?? (!empty($formDocType)
      ? reset($formDocType)['target_id'] : NULL);
    $options = [];
    if (!empty($agendaId)) {
      $options['fragment'] = "$agendaId";
    }
    elseif (!empty($docTypeId)) {
      $options['fragment'] = "$docTypeId";
      $form_state->setRedirect("edw_event.documents.$phase.document_type", ['node' => $meetingId], $options);
      return;
    }
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
