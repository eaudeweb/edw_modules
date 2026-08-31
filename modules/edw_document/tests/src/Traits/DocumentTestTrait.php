<?php

namespace Drupal\Tests\edw_document\Traits;

use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;

/**
 * Helpers for building the fixtures the download tests need.
 *
 * The field is built programmatically rather than by installing the
 * edw_document_media / edw_document_node config, so the tests exercise
 * edw_document itself and not the submodules' opinions about it.
 */
trait DocumentTestTrait {

  /**
   * Creates a managed file with real bytes on disk.
   *
   * Writing actual content matters: getFilteredFiles() decides whether to keep
   * a file by probing the file system, generateArchive() reads the bytes back,
   * and FileController checks file_exists() before serving.
   *
   * @param string $filename
   *   The file name, including the extension.
   * @param string $content
   *   The bytes to write.
   *
   * @return \Drupal\file\FileInterface
   *   The saved file entity.
   */
  protected function createDocumentFile(string $filename, string $content = 'Test document payload.'): FileInterface {
    $directory = 'public://edw-document-test';
    \Drupal::service('file_system')
      ->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    $uri = $directory . '/' . $filename;
    file_put_contents($uri, $content);

    $file = File::create([
      'uri' => $uri,
      'filename' => $filename,
      'status' => FileInterface::STATUS_PERMANENT,
    ]);
    $file->save();

    return $file;
  }

  /**
   * Deletes a file's bytes while leaving the entity in place.
   *
   * Reproduces the state getFilteredFiles() and FileController guard against:
   * a managed file whose payload has gone missing.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file whose bytes should be removed.
   */
  protected function deleteFileBytes(FileInterface $file): void {
    \Drupal::service('file_system')->delete($file->getFileUri());
  }

  /**
   * Creates an unlimited, translatable file field with a display column.
   *
   * `display_field` is what getOptions() filters on, and translatability is
   * what puts a meaningful langcode in the field table that getFilteredFiles()
   * filters on — both are required for the download options to work.
   *
   * @param string $entity_type
   *   The entity type to attach the field to.
   * @param string $bundle
   *   The bundle to attach the field to.
   * @param string $field_name
   *   The field name.
   */
  protected function createFilesField(string $entity_type, string $bundle, string $field_name = 'field_files'): void {
    if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
      FieldStorageConfig::create([
        'entity_type' => $entity_type,
        'field_name' => $field_name,
        'type' => 'file',
        'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
        'translatable' => TRUE,
        'settings' => [
          'uri_scheme' => 'public',
          'display_field' => TRUE,
          'display_default' => TRUE,
        ],
      ])->save();
    }

    if (!FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
      FieldConfig::create([
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'field_name' => $field_name,
        'label' => 'Files',
        'settings' => [
          'file_extensions' => 'doc docx pdf rtf txt xls xlsx png htm',
          'description_field' => TRUE,
        ],
      ])->save();
    }
  }

  /**
   * Returns a single response header value.
   *
   * Mink hands back a header map whose casing depends on the driver, and whose
   * values may be strings or arrays.
   *
   * @param array $headers
   *   The response headers.
   * @param string $name
   *   The header name.
   *
   * @return string
   *   The header value, or an empty string when it is absent.
   */
  protected function headerValue(array $headers, string $name): string {
    foreach ($headers as $header => $values) {
      if (strcasecmp($header, $name) === 0) {
        return is_array($values) ? (string) reset($values) : (string) $values;
      }
    }

    return '';
  }

  /**
   * Builds field values for a set of files.
   *
   * @param \Drupal\file\FileInterface[] $files
   *   The files to reference.
   * @param bool $display
   *   The value of the display column, which getOptions() filters on.
   *
   * @return array
   *   Field item values.
   */
  protected function fileFieldValues(array $files, bool $display = TRUE): array {
    return array_map(function (FileInterface $file) use ($display) {
      return [
        'target_id' => $file->id(),
        'display' => $display ? 1 : 0,
        'description' => '',
      ];
    }, array_values($files));
  }

  /**
   * Creates an unlimited field of the module's own 'file_language' type.
   *
   * Unlike a core file field, this one carries a per-item `language` column,
   * which is what the language-aware formatters and the search index alter
   * read. The field itself is deliberately *not* translatable: the language
   * lives on the item, not on the entity translation.
   *
   * @param string $entity_type
   *   The entity type to attach the field to.
   * @param string $bundle
   *   The bundle to attach the field to.
   * @param string $field_name
   *   The field name.
   */
  protected function createFileLanguageField(string $entity_type, string $bundle, string $field_name = 'field_files'): void {
    if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
      FieldStorageConfig::create([
        'entity_type' => $entity_type,
        'field_name' => $field_name,
        'type' => 'file_language',
        'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
        'settings' => [
          'uri_scheme' => 'public',
          'display_field' => TRUE,
          'display_default' => TRUE,
        ],
      ])->save();
    }

    if (!FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
      FieldConfig::create([
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'field_name' => $field_name,
        'label' => 'Files',
        'settings' => [
          'file_extensions' => 'doc docx pdf rtf txt xls xlsx png htm',
          'description_field' => TRUE,
        ],
      ])->save();
    }
  }

  /**
   * Builds a single 'file_language' field item value.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file to reference.
   * @param string $language
   *   The item's language code, or an empty string for "no language".
   * @param string $description
   *   The item's description.
   * @param bool $display
   *   The value of the display column.
   *
   * @return array
   *   A field item value.
   */
  protected function fileLanguageValue(FileInterface $file, string $language = '', string $description = '', bool $display = TRUE): array {
    return [
      'target_id' => $file->id(),
      'display' => $display ? 1 : 0,
      'description' => $description,
      'language' => $language,
    ];
  }

}
