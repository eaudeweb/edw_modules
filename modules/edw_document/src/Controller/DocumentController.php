<?php

namespace Drupal\edw_document\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\edw_document\Form\DownloadDocumentsForm;
use Drupal\edw_document\Services\DocumentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller routines for documents.
 */
class DocumentController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The document manager service.
   *
   * @var \Drupal\edw_document\Services\DocumentManager
   */
  protected $documentManager;

  /**
   * Constructs a new \Drupal\Core\Controller\FormController object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\edw_document\Services\DocumentManager $document_manager
   *   The document manager service.
   */
  public function __construct(FormBuilderInterface $form_builder, EntityTypeManagerInterface $entity_type_manager, DocumentManager $document_manager) {
    $this->formBuilder = $form_builder;
    $this->entityTypeManager = $entity_type_manager;
    $this->documentManager = $document_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('entity_type.manager'),
      $container->get('edw_document.document.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function modal(ContentEntityInterface $entity, $entity_type = 'node', string $field_name = NULL) {
    $form = $this->formBuilder->getForm(DownloadDocumentsForm::class, $entity_type, [$entity->id()], $field_name, $entity_type);

    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand(NULL, $form, [
      'height' => 600,
      'width' => 900,
      'dialogClass' => 'no-titlebar',
    ]));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function archiveFiles(Request $request) {
    $args = array_map(function ($value) {
      return explode(',', $value);
    }, $request->query->all());
    $fieldName = $request->query->get('field_name') ?? 'field_files';
    $this->documentManager->setEntityTypeId('media');
    $files = $this->documentManager->getFilteredFiles($args['ids'], $fieldName, $args['format'], $args['language']);
    $text = count($args['ids']) >= 2 ? 'Files' : 'File';
    if (empty($files)) {
      $script = <<<EOD
    <script type="text/javascript">
      alert("$text not found in the selected language!");
      window.close();
    </script>
    EOD;
      return new Response($script);
    }
    if (count($files) == 1) {
      $path = $this->documentManager->downloadFile($files);
      return new RedirectResponse($path);
    }
    return $this->documentManager->generateArchive($files);
  }

}
