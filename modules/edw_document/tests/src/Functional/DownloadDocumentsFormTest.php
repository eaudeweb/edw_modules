<?php

namespace Drupal\Tests\edw_document\Functional;

use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\media\Entity\Media;

/**
 * Tests the choices offered by the download modal.
 *
 * The modal must offer exactly the formats and languages the selected
 * documents actually have: an option that matches no file sends the user to a
 * 204, and a missing option hides a document they are entitled to.
 *
 * @group edw_document
 */
class DownloadDocumentsFormTest extends DocumentDownloadTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ConfigurableLanguage::createFromLangcode('fr')->save();

    $this->createMediaType('file', ['id' => 'document', 'label' => 'Document']);
    $this->createFilesField('media', 'document');

    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'view media',
    ]));
  }

  /**
   * Tests that the modal offers the formats the document actually has.
   */
  public function testFormatOptionsMatchTheDocument(): void {
    $media = $this->createDocumentMedia([
      $this->createDocumentFile('report.pdf'),
      $this->createDocumentFile('data.xlsx'),
    ]);

    $markup = $this->getModalMarkup($media);

    $this->assertStringContainsString('PDF', $markup);
    $this->assertStringContainsString('XLS', $markup);
    // Nothing the document does not have.
    $this->assertStringNotContainsString('VIDEO', $markup);
    $this->assertStringNotContainsString('PPT', $markup);
  }

  /**
   * Tests that the modal offers only languages the document has files in.
   */
  public function testLanguageOptionsMatchTheDocument(): void {
    $media = $this->createDocumentMedia([
      $this->createDocumentFile('report.pdf'),
    ], 'fr');

    $markup = $this->getModalMarkup($media);

    $this->assertStringContainsString('language[fr]', $markup);
    $this->assertStringNotContainsString('language[en]', $markup);
  }

  /**
   * Tests that a document with nothing to download says so.
   *
   * Reached when every referenced file has lost its bytes. An empty modal with
   * a disabled button gives the user nothing to act on.
   */
  public function testDocumentWithNoFilesShowsWarning(): void {
    $media = $this->createDocumentMedia([]);

    $markup = $this->getModalMarkup($media);

    $this->assertStringContainsString("Couldn't find any file to download!", $markup);
  }

  /**
   * Tests that the modal requires a format and a language.
   */
  public function testFormatAndLanguageAreRequired(): void {
    $media = $this->createDocumentMedia([
      $this->createDocumentFile('report.pdf'),
      $this->createDocumentFile('data.xlsx'),
    ]);

    $markup = $this->getModalMarkup($media);

    $this->assertStringContainsString('Select at least one format', $markup);
    $this->assertStringContainsString('Select at least one language', $markup);
  }

  /**
   * Returns the form markup the modal route renders for a document.
   *
   * The route answers with an AJAX command that carries the rendered form.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The document to open the modal for.
   *
   * @return string
   *   The rendered form markup.
   */
  protected function getModalMarkup($media): string {
    $this->drupalGet(sprintf('/document-download/modal/media/%d/field_files', $media->id()));
    $this->assertSession()->statusCodeEquals(200);

    $commands = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertIsArray($commands, 'The modal route did not return AJAX commands.');

    // The response also carries add_css and add_js commands, whose data is an
    // array of asset descriptors rather than markup.
    $markup = '';
    foreach ($commands as $command) {
      $data = $command['data'] ?? '';
      if (is_string($data)) {
        $markup .= $data;
      }
    }
    $this->assertNotSame('', $markup, 'The modal route returned no rendered form.');

    return $markup;
  }

  /**
   * Creates a document media entity carrying the given files.
   *
   * @param \Drupal\file\FileInterface[] $files
   *   The files to attach.
   * @param string $langcode
   *   The media language.
   *
   * @return \Drupal\media\MediaInterface
   *   The saved media entity.
   */
  protected function createDocumentMedia(array $files, string $langcode = 'en') {
    $media = Media::create([
      'bundle' => 'document',
      'name' => 'Test document',
      'status' => 1,
      'langcode' => $langcode,
      'field_files' => $this->fileFieldValues($files),
    ]);
    $media->save();

    return $media;
  }

}
