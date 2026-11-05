<?php

namespace Drupal\edw_document\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Url;
use Drupal\edw_document\Response\CacheableBinaryFileResponse;
use Drupal\edw_document\Services\DocumentManager;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * Constructs a new FileController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\edw_document\Services\DocumentManager $document_manager
   *   The document manager service.
   * @param \Drupal\Core\PathProcessor\InboundPathProcessorInterface $path_processor
   *   The inbound path processor.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, DocumentManager $document_manager, InboundPathProcessorInterface $path_processor, StreamWrapperManagerInterface $stream_wrapper_manager) {
    $this->fileStorage = $entity_type_manager->getStorage('file');
    $this->documentManager = $document_manager;
    $this->pathProcessor = $path_processor;
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('edw_document.document.manager'),
      $container->get('path_processor_manager'),
      $container->get('stream_wrapper_manager')
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
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when no file with the provided UUID exists.
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the current user may not download a non-public file.
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
      return throw new NotFoundHttpException();
    }
    $uri = $file->getFileUri();
    if (!file_exists($uri)) {
      return throw new NotFoundHttpException();
    }
    $headers = [
      'Content-Type' => $file->getMimeType(),
      'Content-Length' => $file->getSize(),
    ];
    $filename = $request->query->get('filename') ?? $file->getFilename();

    // Mirror core's FileDownloadController so private files keep the access
    // rules enforced on /system/files.
    if ($this->streamWrapperManager->getScheme($uri) !== 'public') {
      $hookHeaders = $this->moduleHandler()->invokeAll('file_download', [$uri]);
      if (empty($hookHeaders) || in_array(-1, $hookHeaders, TRUE)) {
        throw new AccessDeniedHttpException();
      }
      $response = new BinaryFileResponse($uri, 200, $headers + $hookHeaders, FALSE);
      $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);
      return $response;
    }

    $response = new CacheableBinaryFileResponse($uri, 200, $headers);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);
    $response->addCacheableDependency($file);
    $response->getCacheableMetadata()->addCacheContexts(['url']);

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
