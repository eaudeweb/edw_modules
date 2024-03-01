<?php

namespace Drupal\edw_document\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\Exception\FileNotExistsException;
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
   * Constructs a new FileController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\edw_document\Services\DocumentManager $document_manager
   *   The document manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, DocumentManager $document_manager) {
    $this->fileStorage = $entity_type_manager->getStorage('file');
    $this->documentManager = $document_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('edw_document.document.manager')
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
   */
  public function serveFile(Request $request, $uuid) {
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

}
