<?php

namespace Drupal\edw_document\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drush\Commands\DrushCommands;

/**
 * Class DocumentCommands for documents.
 */
class DocumentCommands extends DrushCommands {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The entity type manager.
   *
   * @var \Drupal\file\FileStorageInterface
   */
  protected $fileStorage;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new DocumentCommands object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(Connection $connection, EntityTypeManagerInterface $entityTypeManager, LanguageManagerInterface $languageManager, MessengerInterface $messenger) {
    parent::__construct();
    $this->connection = $connection;
    $this->fileStorage = $entityTypeManager->getStorage('file');
    $this->languageManager = $languageManager;
    $this->messenger = $messenger;
  }

  /**
   * Fix a langcode in file_managed for a file with a given langcode.
   *
   * @param int $fid
   *   The file id you want to fix the langcode.
   * @param string $langcode
   *   The correct langcode.
   *
   * @command edw_document:files-fix-langcode
   * @aliases files-fl
   */
  public function fixLangcodeFile(int $fid, string $langcode) {
    $file = $this->fileStorage->load($fid);
    if (!$this->languageManager->getLanguage($langcode) instanceof Language) {
      $this->messenger->addError("No language available with this langcode.");
      return;
    }
    $file->set('langcode', $langcode);
    $file->save();
  }

  /**
   * Fix langcode for a list of files, based on their media language.
   *
   * @param string $fids
   *   (optional) The files you want to fix.
   *
   * @command edw_document:files-fix-langcodes
   * @aliases files-fls
   */
  public function fixLangcodeFiles(string $fids = '') {
    $fids = (!empty($fids)) ? explode(',', $fids) : [];
    $values = $this->getQuery($fids);
    $this->messenger->addMessage(sprintf("%s files found.", count($values)));
    foreach ($values as $value) {
      /** @var \Drupal\file\Entity\File $file */
      $file = $this->fileStorage->load($value->fid);
      $file->set('langcode', $value->media_langcode);
      $file->save();
      $this->messenger->addMessage(sprintf("%s file with ID %s was updated.", $value->file_filename, $value->fid));
    }
  }

  /**
   * Get a list of all files with wrong langcode.
   *
   * @command edw_document:files-analyze-langcodes
   * @aliases files-als
   */
  public function listWrongLangcodeFiles() {
    $table = [];
    $table[] = [
      'm_langcode' => 'Media',
      'm_id' => 'Media ID',
      'f_langcode' => 'File',
      'fid' => 'fid',
      'filename' => 'Filename',
    ];
    $values = $this->getQuery();
    foreach ($values as $value) {
      $table[] = [
        'm_langcode' => $value->media_langcode,
        'm_id' => $value->media_mid,
        'f_langcode' => $value->file_langcode,
        'fid' => $value->fid,
        'filename' => $value->file_filename,
      ];
    }
    return new RowsOfFields($table);
  }

  /**
   * {@inheritdoc}
   */
  protected function getQuery(array $fids = []) {
    $query = $this->connection->select('media__field_files', 'a');
    $query->addField('a', 'langcode', 'media_langcode');
    $query->addField('a', 'entity_id', 'media_mid');
    $query->innerJoin('file_usage', 'b', 'a.entity_id = b.id and a.field_files_target_id = b.fid');
    $query->addField('b', 'fid', 'fid');
    $query->condition('b.type', 'media');
    if (!empty($fids)) {
      $query->condition('b.fid', $fids, 'IN');
    }
    $query->innerJoin('file_managed', 'c', 'b.fid = c.fid and a.langcode != c.langcode');
    $query->addField('c', 'langcode', 'file_langcode');
    $query->addField('c', 'filename', 'file_filename');
    return $query->execute()->fetchAll();
  }

}