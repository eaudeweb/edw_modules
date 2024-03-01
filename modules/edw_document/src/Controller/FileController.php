<?php

namespace Drupal\edw_document\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\Exception\FileNotExistsException;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Url;
use Drupal\edw_document\Services\DocumentManager;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Controller for files.
 */
class FileController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The document manager service.
   *
   * @var \Drupal\edw_document\Services\DocumentManager
   */
  protected $documentManager;

  /**
   * The File storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileStorage;

  /**
   * The path processor service.
   *
   * @var \Drupal\Core\PathProcessor\InboundPathProcessorInterface
   */
  protected $pathProcessor;

  /**
   * Constructs a new FileController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\edw_document\Services\DocumentManager $document_manager
   *   The document manager service.
   * @param \Drupal\Core\PathProcessor\InboundPathProcessorInterface $path_processor
   *   The inbound path processor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, DocumentManager $document_manager, InboundPathProcessorInterface $path_processor) {
    $this->fileStorage = $entity_type_manager->getStorage('file');
    $this->documentManager = $document_manager;
    $this->pathProcessor = $path_processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('edw_document.document.manager'),
      $container->get('path_processor_manager')
    );
  }

  /**
   * Serves a file for download.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $uuid
   *   The UUID of the file to be served.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The response object for serving the file.
   *
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   */
  public function serveFile(Request $request, $uuid) {
    $url = $this->processPath($request);
    $uuid = $url->getRouteParameters()['uuid'];
    $files = $this->fileStorage->loadByProperties([
      'uuid' => $uuid,
    ]);
    /** @var \Drupal\file\Entity\File $file */
    $file = reset($files);
    if (!$file instanceof FileInterface) {
      throw new FileNotExistsException();
    }
    $headers = [
      'Content-Type' => $file->getMimeType(),
      'Content-Length' => $file->getSize(),
    ];
    $uri = $file->getFileUri();
    $filename = $request->query->get('filename') ?? $file->getFilename();
    $response = new BinaryFileResponse($uri, 200, $headers);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);
    return $response;
  }

  /**
   * Processes the path for the given request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\Core\Url
   *   The processed URL object.
   */
  protected function processPath($request) {
    $path = $this->pathProcessor->processInbound($request->getPathInfo(), $request);
    return Url::fromUserInput($path, ['query' => $request->query->all()]);
  }

}
