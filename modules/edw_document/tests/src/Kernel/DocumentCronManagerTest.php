<?php

namespace Drupal\Tests\edw_document\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the cron cleanup of leftover archive directories.
 *
 * Archives themselves are temporary and are removed after being sent, but the
 * dated directories they were built in stay behind, so cron sweeps the empty
 * ones. A cleanup that is too eager would delete archives still being served.
 *
 * @coversDefaultClass \Drupal\edw_document\Services\DocumentCronManager
 *
 * @group edw_document
 */
class DocumentCronManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'edw_document',
  ];

  /**
   * The cron manager under test.
   *
   * @var \Drupal\edw_document\Services\DocumentCronManager
   */
  protected $cronManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    $this->fileSystem = \Drupal::service('file_system');
    $this->prepareDirectory('public://downloads');

    $this->cronManager = \Drupal::service('edw_document.document.cron_manager');
  }

  /**
   * Tests that empty directories go and non-empty ones stay.
   *
   * @covers ::cleaupOldDirectories
   */
  public function testRemovesOnlyEmptyDirectories(): void {
    $empty = 'public://downloads/01-01-2026-empty';
    $occupied = 'public://downloads/01-01-2026-occupied';

    $this->prepareDirectory($empty);
    $this->prepareDirectory($occupied);
    file_put_contents($occupied . '/documents.zip', 'Archive still in use.');

    $this->cronManager->cleaupOldDirectories();

    $this->assertDirectoryDoesNotExist($this->fileSystem->realpath($empty));
    $this->assertFileExists($this->fileSystem->realpath($occupied . '/documents.zip'));
  }

  /**
   * Tests that a clean root is handled without error.
   *
   * @covers ::cleaupOldDirectories
   */
  public function testNothingToCleanUp(): void {
    $this->cronManager->cleaupOldDirectories();

    $this->assertDirectoryExists($this->fileSystem->realpath('public://downloads'));
  }

  /**
   * Tests that the directory path is always slash terminated.
   *
   * @covers ::getDirectoryPath
   */
  public function testDirectoryPathIsSlashTerminated(): void {
    $this->assertStringEndsWith('/', $this->cronManager->getDirectoryPath());
  }

  /**
   * Creates a writable directory.
   *
   * @param string $uri
   *   The directory URI.
   */
  protected function prepareDirectory(string $uri): void {
    $this->fileSystem->prepareDirectory(
      $uri,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
    );
  }

}
