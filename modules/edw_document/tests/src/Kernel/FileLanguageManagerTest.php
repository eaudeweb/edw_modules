<?php

namespace Drupal\Tests\edw_document\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the lookup that turns a stored langcode into a language name.
 *
 * Files carry their own langcode, independent of the site's configured
 * languages: a document may be attached in Arabic on a site that is only
 * installed in English. That is why this reads the full ISO list rather than
 * the language manager's configured languages. The file formatters and the
 * language facet both label their output through here, so a miss shows up as
 * a raw langcode in the UI.
 *
 * @coversDefaultClass \Drupal\edw_document\Services\FileLanguageManager
 *
 * @group edw_document
 */
class FileLanguageManagerTest extends KernelTestBase {

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
   * The file language manager under test.
   *
   * @var \Drupal\edw_document\Services\FileLanguageManager
   */
  protected $fileLanguageManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fileLanguageManager = \Drupal::service('edw_document.file.language_manager');
  }

  /**
   * Tests that a langcode is resolved to its English name.
   *
   * @covers ::getLanguageName
   */
  public function testResolvesLangcodeToItsEnglishName(): void {
    $this->assertSame('English', $this->fileLanguageManager->getLanguageName('en'));
    $this->assertSame('French', $this->fileLanguageManager->getLanguageName('fr'));
  }

  /**
   * Tests that the English name is used, not the native one.
   *
   * The standard list holds both names; the second entry is the native one.
   * Picking the wrong index would silently switch the whole UI to native
   * names, which is a display decision this service owns.
   *
   * @covers ::getLanguageName
   */
  public function testUsesTheEnglishNameRatherThanTheNativeOne(): void {
    // 'fr' is ['French', 'Français'] — the native name must not win.
    $this->assertSame('French', $this->fileLanguageManager->getLanguageName('fr'));
    $this->assertSame('Spanish', $this->fileLanguageManager->getLanguageName('es'));
  }

  /**
   * Tests that languages the site does not have installed still resolve.
   *
   * Only 'en' is installed here. A file uploaded as Arabic must still be
   * labelled "Arabic" rather than falling back to its langcode.
   *
   * @covers ::getLanguageName
   */
  public function testResolvesLanguagesThatAreNotInstalledOnTheSite(): void {
    $installed = array_keys(\Drupal::languageManager()->getLanguages());
    $this->assertNotContains('ar', $installed);

    $this->assertSame('Arabic', $this->fileLanguageManager->getLanguageName('ar'));
  }

  /**
   * Tests that an unrecognised langcode returns NULL rather than erroring.
   *
   * Callers treat NULL as "no label": ListLanguageProcessor leaves the raw
   * value in place and FileWithLanguageFormatter falls back to the filename.
   * Legacy content and hand-edited data do contain junk langcodes, so this is
   * a reachable path and not merely defensive.
   *
   * @covers ::getLanguageName
   */
  public function testUnknownLangcodeReturnsNull(): void {
    $this->assertNull($this->fileLanguageManager->getLanguageName('zz'));
  }

  /**
   * Tests that an empty langcode returns NULL rather than erroring.
   *
   * An empty language is the normal state of a file item whose language was
   * never picked in the widget, and the formatter asks for its name before
   * checking whether one was set.
   *
   * @covers ::getLanguageName
   */
  public function testEmptyLangcodeReturnsNull(): void {
    $this->assertNull($this->fileLanguageManager->getLanguageName(''));
  }

  /**
   * Tests the shape of the passed-through standard language list.
   *
   * The file widget builds its language dropdown straight from this, taking
   * element 0 of each entry as the option label.
   *
   * @covers ::getStandardLanguageList
   */
  public function testStandardLanguageListIsKeyedByLangcode(): void {
    $languages = $this->fileLanguageManager->getStandardLanguageList();

    $this->assertArrayHasKey('en', $languages);
    $this->assertArrayHasKey('fr', $languages);
    $this->assertSame('French', $languages['fr'][0]);
  }

}
