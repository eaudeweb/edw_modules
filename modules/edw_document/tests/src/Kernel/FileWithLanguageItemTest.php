<?php

namespace Drupal\Tests\edw_document\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_document\Traits\DocumentTestTrait;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;

/**
 * Tests the 'file_language' field type.
 *
 * This is a core file field with one extra column: the language of the file
 * itself, which is not the language of the entity that carries it. A single
 * English document node holds its English, French and Spanish PDFs as three
 * items of one field, each tagged with its own language. Everything else in
 * the module — the formatters, the language facet, the search index alter —
 * reads that column, so it has to survive a save/load round trip.
 *
 * @coversDefaultClass \Drupal\edw_document\Plugin\Field\FieldType\FileWithLanguageItem
 *
 * @group edw_document
 */
class FileWithLanguageItemTest extends KernelTestBase {

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

    // Public files are only viewable with 'access content'; see
    // \Drupal\file\FileAccessControlHandler::checkAccess().
    $anonymous = Role::load(Role::ANONYMOUS_ID);
    $anonymous->grantPermission('access content');
    $anonymous->save();

    NodeType::create(['type' => 'document', 'name' => 'Document'])->save();
    $this->createFileLanguageField('node', 'document');
  }

  /**
   * Tests that each item keeps its own language through a save/load cycle.
   *
   * @covers ::schema
   * @covers ::propertyDefinitions
   */
  public function testEachItemKeepsItsOwnLanguage(): void {
    $english = $this->createDocumentFile('report-en.pdf');
    $french = $this->createDocumentFile('report-fr.pdf');

    $node = Node::create([
      'type' => 'document',
      'title' => 'Annual report',
      'langcode' => 'en',
      'field_files' => [
        $this->fileLanguageValue($english, 'en'),
        $this->fileLanguageValue($french, 'fr'),
      ],
    ]);
    $node->save();

    $reloaded = Node::load($node->id());

    $this->assertSame('en', $reloaded->get('field_files')[0]->language);
    $this->assertSame('fr', $reloaded->get('field_files')[1]->language);
    // The language is the file's, not the host entity's.
    $this->assertSame('en', $reloaded->language()->getId());
  }

  /**
   * Tests that the inherited file columns still work.
   *
   * The field type reimplements schema() from scratch rather than adding a
   * column to the parent's, so the file columns are easy to lose.
   *
   * @covers ::schema
   */
  public function testInheritedFileColumnsStillWork(): void {
    $file = $this->createDocumentFile('report.pdf');

    $node = Node::create([
      'type' => 'document',
      'title' => 'Annual report',
      'field_files' => [$this->fileLanguageValue($file, 'fr', 'The annual report', TRUE)],
    ]);
    $node->save();

    $item = Node::load($node->id())->get('field_files')[0];

    $this->assertSame((int) $file->id(), (int) $item->target_id);
    $this->assertSame('The annual report', $item->description);
    $this->assertEquals(1, $item->display);
    $this->assertSame($file->getFileUri(), $item->entity->getFileUri());
  }

  /**
   * Tests that an item with no language is stored as empty, not as a failure.
   *
   * Leaving the widget's language select on "- None -" is a supported choice:
   * DropdownFilesFormatter hides such items and FileWithLanguageFormatter
   * falls back to the description or filename.
   *
   * @covers ::schema
   */
  public function testAnItemMayHaveNoLanguage(): void {
    $file = $this->createDocumentFile('report.pdf');

    $node = Node::create([
      'type' => 'document',
      'title' => 'Annual report',
      'field_files' => [$this->fileLanguageValue($file, '')],
    ]);
    $node->save();

    $item = Node::load($node->id())->get('field_files')[0];

    $this->assertEmpty($item->language);
  }

  /**
   * Tests that the language column is actually created in the field table.
   *
   * @covers ::schema
   */
  public function testTheLanguageColumnIsCreatedInStorage(): void {
    $schema = \Drupal::database()->schema();

    $this->assertTrue($schema->fieldExists('node__field_files', 'field_files_language'));
    $this->assertTrue($schema->fieldExists('node__field_files', 'field_files_target_id'));
    $this->assertTrue($schema->fieldExists('node__field_files', 'field_files_display'));
    $this->assertTrue($schema->fieldExists('node__field_files', 'field_files_description'));
  }

  /**
   * Tests that 'language' is exposed as a typed-data property.
   *
   * Without a property definition the column is writable but invisible to
   * anything walking the field's properties — which is how Search API maps
   * `field_files:language` onto an index field.
   *
   * @covers ::propertyDefinitions
   */
  public function testLanguageIsExposedAsProperty(): void {
    $storage = FieldStorageConfig::loadByName('node', 'field_files');
    $properties = $storage->getPropertyDefinitions();

    $this->assertArrayHasKey('language', $properties);
    $this->assertSame('string', $properties['language']->getDataType());
    // The file columns are still exposed alongside it.
    $this->assertArrayHasKey('target_id', $properties);
    $this->assertArrayHasKey('description', $properties);
  }

  /**
   * Tests that the field type ships with the language-aware widget/formatter.
   *
   * A plain file widget cannot set the language column, so a field of this
   * type that defaulted to the core widget would be unfillable.
   */
  public function testDefaultsToTheLanguageAwareWidgetAndFormatter(): void {
    $definition = \Drupal::service('plugin.manager.field.field_type')
      ->getDefinition('file_language');

    $this->assertSame('file_generic_with_language', $definition['default_widget']);
    $this->assertSame('file_with_language_formatter', $definition['default_formatter']);
  }

}
