<?php

namespace Drupal\edw_utilities\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a route controller for entity autocomplete form elements.
 */
class TermsAutocompleteController extends ControllerBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * TermsAutocompleteController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, EntityRepositoryInterface $entity_repository) {
    $this->termStorage = $entity_type_manager->getStorage('taxonomy_term');
    $this->moduleHandler = $module_handler;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('entity.repository')
    );
  }

  /**
   * Handler for autocomplete request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $vid
   *   The vocabulary id.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function autocomplete(Request $request, string $vid) {
    $results = [];
    // A list with additional properties related for each project.
    $properties = ($request->query->has('properties')) ? $request->query->all()['properties'] : [];
    $input = $request->query->get('q');
    // Get the typed string from the URL, if it exists.
    if (!$input) {
      return new JsonResponse($results);
    }
    $input = Xss::filter($input);
    $query = $this->termStorage->getQuery()
      ->accessCheck()
      ->condition('vid', $vid);
    $this->moduleHandler->invokeAll('terms_autocomplete_query_alter', [$vid, $input, &$query, $properties]);
    $ids = $query->execute();
    $terms = $ids ? $this->termStorage->loadMultiple($ids) : [];
    foreach ($terms as $term) {
      $label = sprintf('<a href="%s">%s</a>', Url::fromRoute('entity.taxonomy_term.canonical', [
        'taxonomy_term' => $term->id(),
        ])->toString(),
        $term->label(),
      );
      $this->moduleHandler->invokeAll('terms_autocomplete_label_alter', [$vid, $term, &$label, $properties]);
      $results[] = [
        'value' => $this->entityRepository->getTranslationFromContext($term)->label(),
        'label' => $label,
      ];
    }

    return new JsonResponse($results);
  }

}
