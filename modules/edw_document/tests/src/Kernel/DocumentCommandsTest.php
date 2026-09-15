<?php

namespace Drupal\Tests\edw_document\Kernel;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_document\Traits\DocumentTestTrait;
use Drupal\edw_document\Commands\DocumentCommands;
use Drupal\file\Entity\File;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the maintenance commands for document files.
 *
 * These exist to repair data rather than to serve it. Sites that imported
 * their documents from an older system ended up with files whose langcode did
 * not match the media item carrying them — which breaks the language filter
 * on the downloads, since that reads the file's own langcode — and with the
 * same PDF uploaded many times over.
 *
 * @coversDefaultClass \Drupal\edw_document\Commands\DocumentCommands
 *
 * @group edw_document
 */
class DocumentCommandsTest extends KernelTestBase {

  use DocumentTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'language',
    'edw_document',
  ];

  /**
   * The commands under test.
   *
   * @var \Drupal\edw_document\Commands\DocumentCommands
   */
  protected $commands;

  /**
   * The messenger the commands report through.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    ConfigurableLanguage::createFromLangcode('fr')->save();

    $this->messenger = \Drupal::messenger();
    $this->commands = new DocumentCommands(
      \Drupal::database(),
      \Drupal::entityTypeManager(),
      \Drupal::languageManager(),
      $this->messenger
    );
  }

  /**
   * Tests that a file's langcode can be corrected.
   *
   * @covers ::fixLangcodeFile
   */
  public function testFileLangcodeCanBeCorrected(): void {
    $file = $this->createDocumentFile('rapport.pdf');
    $this->assertSame('en', $file->language()->getId());

    $this->commands->fixLangcodeFile((int) $file->id(), 'fr');

    $this->assertSame('fr', File::load($file->id())->language()->getId());
  }

  /**
   * Tests that an unconfigured langcode is refused.
   *
   * Writing an arbitrary langcode would leave the file invisible to every
   * language filter rather than merely mis-filed, so the command checks the
   * language exists on this site before touching anything.
   *
   * @covers ::fixLangcodeFile
   */
  public function testAnUnconfiguredLangcodeIsRefused(): void {
    $file = $this->createDocumentFile('rapport.pdf');

    $this->commands->fixLangcodeFile((int) $file->id(), 'de');

    $this->assertSame('en', File::load($file->id())->language()->getId());
    $this->assertNotEmpty($this->messenger->messagesByType(MessengerInterface::TYPE_ERROR));
  }

  /**
   * Tests that correcting a langcode reports nothing on success.
   *
   * @covers ::fixLangcodeFile
   */
  public function testCorrectingLangcodeIsSilent(): void {
    $file = $this->createDocumentFile('rapport.pdf');

    $this->commands->fixLangcodeFile((int) $file->id(), 'fr');

    $this->assertSame([], $this->messenger->messagesByType(MessengerInterface::TYPE_ERROR));
  }

  /**
   * Tests that files with identical bytes are grouped together.
   *
   * The duplicates report compares content, not names: the same document
   * uploaded twice under different filenames is the case worth finding.
   *
   * @covers ::setReportItem
   * @covers ::getReportItemsBySha
   * @covers ::getShaByFid
   */
  public function testFilesWithIdenticalBytesAreGrouped(): void {
    $first = $this->createDocumentFile('report.pdf', 'Same bytes.');
    $second = $this->createDocumentFile('report-copy.pdf', 'Same bytes.');
    $other = $this->createDocumentFile('annex.pdf', 'Different bytes.');

    $this->commands->setReportItem((int) $first->id(), $first->getFileUri(), 1);
    $this->commands->setReportItem((int) $second->id(), $second->getFileUri(), 2);
    $this->commands->setReportItem((int) $other->id(), $other->getFileUri(), 3);

    $sha = $this->commands->getShaByFid((int) $first->id());

    $this->assertSame($sha, $this->commands->getShaByFid((int) $second->id()));
    $this->assertNotSame($sha, $this->commands->getShaByFid((int) $other->id()));
    $this->assertCount(2, $this->commands->getReportItemsBySha($sha));
    $this->assertCount(1, $this->commands->getReportItemsBySha(
      $this->commands->getShaByFid((int) $other->id())
    ));
  }

  /**
   * Tests that each recorded duplicate keeps where it is used.
   *
   * The report is only actionable if it says which entity and language each
   * copy belongs to, so an editor can tell which one to keep.
   *
   * @covers ::setReportItem
   * @covers ::getReportItemsBySha
   */
  public function testEachRecordedCopyKeepsWhereItIsUsed(): void {
    $first = $this->createDocumentFile('report.pdf', 'Same bytes.');
    $second = $this->createDocumentFile('report-copy.pdf', 'Same bytes.');

    $this->commands->setReportItem((int) $first->id(), $first->getFileUri(), 11, 'en');
    $this->commands->setReportItem((int) $second->id(), $second->getFileUri(), 22, 'fr');

    $items = $this->commands->getReportItemsBySha($this->commands->getShaByFid((int) $first->id()));

    $this->assertSame([11, 22], array_column($items, 'entity_id'));
    $this->assertSame(['en', 'fr'], array_column($items, 'langcode'));
  }

  /**
   * Tests that a file whose bytes are gone is left out of the report.
   *
   * Hashing a missing file would abort the whole report, and a file that is
   * not on disk cannot be a duplicate of anything.
   *
   * @covers ::setReportItem
   */
  public function testFileMissingFromDiskIsNotRecorded(): void {
    $present = $this->createDocumentFile('report.pdf', 'Same bytes.');
    $missing = $this->createDocumentFile('gone.pdf', 'Same bytes.');
    $this->deleteFileBytes($missing);

    $this->commands->setReportItem((int) $present->id(), $present->getFileUri(), 1);
    $this->commands->setReportItem((int) $missing->id(), $missing->getFileUri(), 2);

    $items = $this->commands->getReportItemsBySha($this->commands->getShaByFid((int) $present->id()));

    $this->assertCount(1, $items);
    $this->assertSame((int) $present->id(), $items[0]['fid']);
  }

  /**
   * Tests that the langcode defaults to English when none is given.
   *
   * @covers ::setReportItem
   */
  public function testTheRecordedLangcodeDefaultsToEnglish(): void {
    $file = $this->createDocumentFile('report.pdf');

    $this->commands->setReportItem((int) $file->id(), $file->getFileUri(), 1);

    $items = $this->commands->getReportItemsBySha($this->commands->getShaByFid((int) $file->id()));

    $this->assertSame('en', $items[0]['langcode']);
  }

}
