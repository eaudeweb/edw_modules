<?php

namespace Drupal\Tests\edw_document\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Tests\UnitTestCase;
use Drupal\edw_document\Services\DocumentManager;

/**
 * Tests the format and language option labels shown in the download modal.
 *
 * @coversDefaultClass \Drupal\edw_document\Services\DocumentManager
 *
 * @group edw_document
 */
class DocumentManagerIconsTest extends UnitTestCase {

  /**
   * The document manager under test.
   *
   * @var \Drupal\edw_document\Services\DocumentManager
   */
  protected $documentManager;

  /**
   * The mocked language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $extensionList = $this->createMock(ModuleExtensionList::class);
    $extensionList->method('getPath')
      ->with('edw_document')
      ->willReturn('modules/contrib/edw_modules/modules/edw_document');

    $this->languageManager = $this->createMock(LanguageManagerInterface::class);

    $this->documentManager = new DocumentManager(
      $this->createMock(CurrentRouteMatch::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $extensionList,
      $this->createMock(FileUrlGeneratorInterface::class),
      $this->createMock(FileSystemInterface::class),
      $this->languageManager,
      $this->createMock(Connection::class)
    );
  }

  /**
   * Tests that available formats are labelled for the modal checkboxes.
   *
   * @covers ::getIcons
   */
  public function testGetIconsLabelsAvailableFormats(): void {
    $icons = $this->documentManager->getIcons(['pdf', 'document']);

    $this->assertSame(['pdf' => 'PDF', 'document' => 'DOC'], $icons);
  }

  /**
   * Tests that formats which are not available are dropped.
   *
   * @covers ::getIcons
   */
  public function testGetIconsFiltersUnavailableFormats(): void {
    $this->assertSame([], $this->documentManager->getIcons([]));
    $this->assertSame(['text' => 'TEXT'], $this->documentManager->getIcons(['text']));
    // A format that has no icon at all is simply absent.
    $this->assertSame([], $this->documentManager->getIcons(['audio']));
  }

  /**
   * Tests that every format with an icon also has a label.
   *
   * The extension map sends .htm and .shtml to "link", and there is an icon
   * for it, but ICONS_LABEL_INFO has no entry — so a .htm document reaches the
   * modal with an unlabelled checkbox. The same holds for "html".
   *
   * @covers ::getIcons
   */
  public function testEveryIconFormatHasLabel(): void {
    $formats = array_keys($this->documentManager->documentIconsPathInfo());

    $missing = array_diff($formats, array_keys(DocumentManager::ICONS_LABEL_INFO));
    $this->assertSame([], array_values($missing), sprintf(
      'These formats have an icon but no label, so their download checkbox renders unlabelled: %s',
      implode(', ', $missing)
    ));

    // And the labels really do come back for each of them.
    $icons = $this->documentManager->getIcons($formats);
    $this->assertNotContains(NULL, $icons);
    $this->assertNotContains('', $icons);
  }

  /**
   * Tests that only site languages that actually have files are offered.
   *
   * @covers ::getFilteredLanguages
   */
  public function testGetFilteredLanguages(): void {
    $this->languageManager->method('getLanguages')->willReturn([
      'en' => new Language(['id' => 'en', 'name' => 'English']),
      'fr' => new Language(['id' => 'fr', 'name' => 'French']),
      'ru' => new Language(['id' => 'ru', 'name' => 'Russian']),
    ]);

    $this->assertSame(
      ['en' => 'en', 'fr' => 'fr'],
      $this->documentManager->getFilteredLanguages(['en', 'fr'])
    );

    // A langcode the site does not have configured is ignored rather than
    // producing an option nobody can satisfy.
    $this->assertSame(
      ['en' => 'en'],
      $this->documentManager->getFilteredLanguages(['en', 'zh-hans'])
    );

    $this->assertSame([], $this->documentManager->getFilteredLanguages([]));
  }

}
