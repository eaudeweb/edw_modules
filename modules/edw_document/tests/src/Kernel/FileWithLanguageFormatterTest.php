<?php

namespace Drupal\Tests\edw_document\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_document\Traits\DocumentTestTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\Role;

/**
 * Tests the link text chosen for each file of a 'file_language' field.
 *
 * The formatter picks one of three things to label a file link with — the
 * file's language, the item's description, or nothing (leaving the theme to
 * fall back to the filename) — based on two settings and on whether the item
 * actually has a language. The precedence between them is the whole point of
 * the plugin, and it is decided by three sequential reassignments of one
 * variable, so an ordering mistake is silent.
 *
 * @coversDefaultClass \Drupal\edw_document\Plugin\Field\FieldFormatter\FileWithLanguageFormatter
 *
 * @group edw_document
 */
class FileWithLanguageFormatterTest extends KernelTestBase {

  use DocumentTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'node',
    'edw_document',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['user']);

    $anonymous = Role::load(Role::ANONYMOUS_ID);
    $anonymous->grantPermission('access content');
    $anonymous->save();

    NodeType::create(['type' => 'document', 'name' => 'Document'])->save();
    $this->createFileLanguageField('node', 'document');
  }

  /**
   * Tests that a file with a language is labelled with the language name.
   *
   * @covers ::viewElements
   */
  public function testLanguageIsShownAsItsName(): void {
    $node = $this->createDocumentNode([
      $this->fileLanguageValue($this->createDocumentFile('report-fr.pdf'), 'fr'),
    ]);

    $elements = $this->buildField($node);

    $this->assertSame('French', (string) $elements[0]['#description']);
    $this->assertSame('file_link', $elements[0]['#theme']);
  }

  /**
   * Tests that the description is used when no language is set.
   *
   * @covers ::viewElements
   */
  public function testDescriptionIsUsedWhenThereIsNoLanguage(): void {
    $node = $this->createDocumentNode([
      $this->fileLanguageValue($this->createDocumentFile('report.pdf'), '', 'Annual report'),
    ]);

    $elements = $this->buildField($node, ['use_description_as_link_text' => TRUE]);

    $this->assertSame('Annual report', (string) $elements[0]['#description']);
  }

  /**
   * Tests that the description is withheld unless the setting asks for it.
   *
   * A NULL description is what makes file_link fall back to the filename.
   *
   * @covers ::viewElements
   */
  public function testDescriptionIsIgnoredWhenTheSettingIsOff(): void {
    $node = $this->createDocumentNode([
      $this->fileLanguageValue($this->createDocumentFile('report.pdf'), '', 'Annual report'),
    ]);

    $elements = $this->buildField($node, ['use_description_as_link_text' => FALSE]);

    $this->assertNull($elements[0]['#description']);
  }

  /**
   * Tests that a language beats the description when both are present.
   *
   * 'use_description_as_link_text' is explicitly scoped to items without a
   * language: a French PDF labelled with its description would lose the only
   * clue about which language it is in.
   *
   * @covers ::viewElements
   */
  public function testLanguageWinsOverDescription(): void {
    $node = $this->createDocumentNode([
      $this->fileLanguageValue($this->createDocumentFile('report-fr.pdf'), 'fr', 'Annual report'),
    ]);

    $elements = $this->buildField($node, ['use_description_as_link_text' => TRUE]);

    $this->assertSame('French', (string) $elements[0]['#description']);
  }

  /**
   * Tests that 'suppress_language' lets the description override the language.
   *
   * This is the escape hatch for sites that label their files editorially
   * ("Report — annexes") and do not want the language name imposed.
   *
   * @covers ::viewElements
   */
  public function testSuppressLanguageLetsTheDescriptionOverrideTheLanguage(): void {
    $node = $this->createDocumentNode([
      $this->fileLanguageValue($this->createDocumentFile('report-fr.pdf'), 'fr', 'Annual report'),
    ]);

    $elements = $this->buildField($node, ['suppress_language' => TRUE]);

    $this->assertSame('Annual report', (string) $elements[0]['#description']);
  }

  /**
   * Tests that 'suppress_language' does nothing without a description.
   *
   * Suppressing the language with nothing to replace it would leave the link
   * showing a bare filename, losing information rather than tidying it.
   *
   * @covers ::viewElements
   */
  public function testSuppressLanguageKeepsTheLanguageWhenThereIsNoDescription(): void {
    $node = $this->createDocumentNode([
      $this->fileLanguageValue($this->createDocumentFile('report-fr.pdf'), 'fr', ''),
    ]);

    $elements = $this->buildField($node, ['suppress_language' => TRUE]);

    $this->assertSame('French', (string) $elements[0]['#description']);
  }

  /**
   * Tests that an item with neither language nor description is left bare.
   *
   * @covers ::viewElements
   */
  public function testNoLanguageAndNoDescriptionLeavesTheLabelEmpty(): void {
    $node = $this->createDocumentNode([
      $this->fileLanguageValue($this->createDocumentFile('report.pdf'), '', ''),
    ]);

    $elements = $this->buildField($node, ['use_description_as_link_text' => TRUE]);

    $this->assertEmpty($elements[0]['#description']);
  }

  /**
   * Tests that each item is labelled from its own language.
   *
   * The item is read back off the file entity as `_referringItem`, which core
   * has to clone when one file is referenced twice; getting that wrong would
   * label every item with the first one's language.
   *
   * @covers ::viewElements
   */
  public function testEachItemIsLabelledFromItsOwnLanguage(): void {
    $node = $this->createDocumentNode([
      $this->fileLanguageValue($this->createDocumentFile('report-en.pdf'), 'en'),
      $this->fileLanguageValue($this->createDocumentFile('report-fr.pdf'), 'fr'),
      $this->fileLanguageValue($this->createDocumentFile('report-es.pdf'), 'es'),
    ]);

    $elements = $this->buildField($node);

    $this->assertSame('English', (string) $elements[0]['#description']);
    $this->assertSame('French', (string) $elements[1]['#description']);
    $this->assertSame('Spanish', (string) $elements[2]['#description']);
  }

  /**
   * Tests that the same file used twice is labelled per item.
   *
   * Uploading one PDF and tagging it as two languages is unusual but legal,
   * and it is the case that forces core to clone the referenced entity.
   *
   * @covers ::viewElements
   */
  public function testTheSameFileReferencedTwiceIsLabelledPerItem(): void {
    $file = $this->createDocumentFile('report.pdf');
    $node = $this->createDocumentNode([
      $this->fileLanguageValue($file, 'en'),
      $this->fileLanguageValue($file, 'fr'),
    ]);

    $elements = $this->buildField($node);

    $this->assertSame('English', (string) $elements[0]['#description']);
    $this->assertSame('French', (string) $elements[1]['#description']);
  }

  /**
   * Tests that files hidden with the display checkbox are not rendered.
   *
   * @covers ::viewElements
   */
  public function testHiddenFilesAreNotRendered(): void {
    $node = $this->createDocumentNode([
      $this->fileLanguageValue($this->createDocumentFile('visible.pdf'), 'en', '', TRUE),
      $this->fileLanguageValue($this->createDocumentFile('hidden.pdf'), 'fr', '', FALSE),
    ]);

    $elements = $this->buildField($node);

    $this->assertArrayHasKey(0, $elements);
    $this->assertArrayNotHasKey(1, $elements);
  }

  /**
   * Tests that the file's cache tags are attached to each link.
   *
   * Without them a renamed or replaced file keeps showing its old link.
   *
   * @covers ::viewElements
   */
  public function testTheFileCacheTagsAreAttached(): void {
    $file = $this->createDocumentFile('report.pdf');
    $node = $this->createDocumentNode([$this->fileLanguageValue($file, 'fr')]);

    $elements = $this->buildField($node);

    $this->assertSame($file->getCacheTags(), $elements[0]['#cache']['tags']);
  }

  /**
   * Tests the summary shown on the display settings form.
   *
   * @covers ::settingsSummary
   */
  public function testSettingsSummaryReportsTheActiveOverrides(): void {
    $this->assertSame([], $this->settingsSummary([]));

    $this->assertCount(1, $this->settingsSummary(['use_description_as_link_text' => TRUE]));
    $this->assertCount(1, $this->settingsSummary(['suppress_language' => TRUE]));
    $this->assertCount(2, $this->settingsSummary([
      'use_description_as_link_text' => TRUE,
      'suppress_language' => TRUE,
    ]));
  }

  /**
   * Builds the file field of a node with the formatter under test.
   *
   * This goes through the field's own view() rather than calling
   * viewElements() directly, because core only populates the referenced file
   * entities in prepareView(), which the display calls first.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to render.
   * @param array $settings
   *   Formatter settings; unset keys fall back to the plugin's defaults.
   * @param string $formatter
   *   The formatter plugin id.
   *
   * @return array
   *   The field's render array.
   */
  protected function buildField(NodeInterface $node, array $settings = [], string $formatter = 'file_with_language_formatter'): array {
    return $node->get('field_files')->view([
      'type' => $formatter,
      'label' => 'hidden',
      'settings' => $settings,
    ]);
  }

  /**
   * Returns the summary the formatter shows for the given settings.
   *
   * @param array $settings
   *   Formatter settings.
   *
   * @return array
   *   The summary lines.
   */
  protected function settingsSummary(array $settings): array {
    return $this->viewDisplay($settings)->getRenderer('field_files')->settingsSummary();
  }

  /**
   * Builds a view display showing the file field with the given formatter.
   *
   * @param array $settings
   *   Formatter settings.
   * @param string $formatter
   *   The formatter plugin id.
   *
   * @return \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   *   The view display.
   */
  protected function viewDisplay(array $settings = [], string $formatter = 'file_with_language_formatter') {
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'document',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $display->setComponent('field_files', [
      'type' => $formatter,
      'label' => 'hidden',
      'settings' => $settings,
    ]);

    return $display;
  }

  /**
   * Creates a document node carrying the given field items.
   *
   * @param array $values
   *   Field item values, as built by fileLanguageValue().
   *
   * @return \Drupal\node\NodeInterface
   *   The saved node.
   */
  protected function createDocumentNode(array $values): NodeInterface {
    $node = Node::create([
      'type' => 'document',
      'title' => 'Annual report',
      'langcode' => 'en',
      'field_files' => $values,
    ]);
    $node->save();

    return $node;
  }

}
