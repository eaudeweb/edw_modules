<?php

namespace Drupal\edw_document\Response;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Cache\CacheableResponseTrait;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A file response that can be cached.
 *
 * See https://www.drupal.org/project/drupal/issues/3227041.
 */
class CacheableBinaryFileResponse extends BinaryFileResponse implements CacheableResponseInterface {

  use CacheableResponseTrait;

  /**
   * Constructor.
   *
   * phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod
   *
   * @param string $uri
   *   File uri.
   * @param int $status
   *   The http response code, e.g. 200 for "OK".
   * @param array $headers
   *   An array of response headers.
   * @param bool $public
   *   TRUE to set 'Cache-Control' header to 'public'.
   *   FALSE to set 'Cache-Control' header to 'private'.
   * @param string|null $contentDisposition
   *   A content-disposition header.
   * @param bool $autoEtag
   *   TRUE to set an 'ETag' header based on the file checksum.
   * @param bool $autoLastModified
   *   TRUE to set a 'Last-Modified' header based on the file mtime.
   */
  public function __construct(protected string $uri, int $status = 200, array $headers = [], bool $public = TRUE, ?string $contentDisposition = NULL, bool $autoEtag = FALSE, bool $autoLastModified = TRUE) {
    parent::__construct($uri, $status, $headers, $public, $contentDisposition, $autoEtag, $autoLastModified);
  }

  /**
   * Gets a serializable representation.
   *
   * @return array
   *   Data for serialization.
   */
  public function __serialize(): array {
    $values = (array) $this;
    // The file object cannot be serialized.
    unset($values["\0*\0file"]);
    return $values;
  }

  /**
   * Initializes uncacheable properties on unserialize.
   */
  public function __wakeup(): void {
    $this->setFile($this->uri);
  }

}
