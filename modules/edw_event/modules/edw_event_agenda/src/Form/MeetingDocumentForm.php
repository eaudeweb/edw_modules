<?php

namespace Drupal\edw_event_agenda\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteProviderInterface;
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
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a MeetingAgendaForm form object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The current request.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(RequestStack $request_stack, EntityTypeManagerInterface $entity_type_manager, RouteProviderInterface $route_provider, ModuleHandlerInterface $module_handler) {
    $this->requestStack = $request_stack;
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->routeProvider = $route_provider;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
      $container->get('router.route_provider'),
      $container->get('module_handler')
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
    $formDocType = $form_state->getValue('field_document_types') ?? [];
    $docTypeId = $request->get('field_document_types') ?? (!empty($formDocType)
      ? reset($formDocType)['target_id'] : NULL);
    $docTypeId = is_array($docTypeId) ? reset($docTypeId) : $docTypeId;
    $options = [];

    $routeRedirect = "edw_event.documents.$phase";
    if (!empty($agendaId)) {
      $options['fragment'] = "$agendaId";
    }
    if (!empty($docTypeId) && !$agendaId) {
      $options['fragment'] = "$docTypeId";
      $routeRedirect = "edw_event.documents.$phase.document_type";
    }

    if (count($this->routeProvider->getRoutesByNames([$routeRedirect])) === 0) {
      unset($options['fragment']);
      $routeRedirect = "edw_event.documents.$phase";
    }
    $this->moduleHandler->invokeAll('meeting_document_form_alter_route_redirect', [
      $form_state, $request, &$routeRedirect, &$options,
    ]);
    $form_state->setRedirect($routeRedirect, ['node' => $meetingId], $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentPhase($phase, FormStateInterface $form_state) {
    $selectedPhase = $form_state->getUserInput()['field_document_phase'] ?? NULL;
    if (empty($selectedPhase)) {
      return $phase;
    }
    if (!is_array($selectedPhase)) {
      return $selectedPhase;
    }

    if (!empty($phase) && in_array($phase, $selectedPhase, TRUE)) {
      return $phase;
    }
    return reset($selectedPhase);
  }

}
