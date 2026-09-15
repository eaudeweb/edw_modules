<?php

namespace Drupal\Tests\edw_document\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\edw_document\Traits\DocumentTestTrait;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\ItemInterface;

/**
 * Tests which file languages a document is indexed under.
 *
 * The documents listing has a language facet backed by the indexed
 * `file_language` field. Search API collects that field by walking the file
 * items' language property, which ignores the per-item display checkbox — so
 * a document whose French PDF has been hidden would still be offered under
 * "French", and clicking the facet would return a document with nothing to
 * download. This hook removes the languages of hidden files before the item
 * is handed to the index.
 *
 * @group edw_document
 */
class SearchApiIndexLanguagesTest extends KernelTestBase {

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
    'search_api',
    'edw_document',
  ];

  /**
   * The index the items belong to.
   *
   * @var \Drupal\search_api\IndexInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $index;

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

    NodeType::create(['type' => 'document', 'name' => 'Document'])->save();
    $this->createFileLanguageField('node', 'document');

    $this->index = $this->createMock(IndexInterface::class);
  }

  /**
   * Tests that a hidden file's language is dropped from the index.
   */
  public function testTheLanguageOfHiddenFileIsNotIndexed(): void {
    $node = $this->createDocumentNode([
      $this->fileLanguageValue($this->createDocumentFile('report-en.pdf'), 'en', '', TRUE),
      $this->fileLanguageValue($this->createDocumentFile('report-fr.pdf'), 'fr', '', FALSE),
    ]);

    $field = $this->languageField(['en', 'fr']);
    $items = [$this->indexItem($node, $field)];

    edw_document_search_api_index_items_alter($this->index, $items);

    $this->assertSame(['en'], $field->getValues());
  }

  /**
   * Tests that visible files keep their languages.
   */
  public function testVisibleFilesKeepTheirLanguages(): void {
    $node = $this->createDocumentNode([
      $this->fileLanguageValue($this->createDocumentFile('report-en.pdf'), 'en', '', TRUE),
      $this->fileLanguageValue($this->createDocumentFile('report-fr.pdf'), 'fr', '', TRUE),
    ]);

    $field = $this->languageField(['en', 'fr']);
    $items = [$this->indexItem($node, $field)];

    edw_document_search_api_index_items_alter($this->index, $items);

    $this->assertSame(['en', 'fr'], $field->getValues());
  }

  /**
   * Tests that hiding every file leaves the document with no languages.
   *
   * The document stays in the index — it is still findable by title — but it
   * stops matching any language facet value.
   */
  public function testHidingEveryFileEmptiesTheIndexedLanguages(): void {
    $node = $this->createDocumentNode([
      $this->fileLanguageValue($this->createDocumentFile('report-en.pdf'), 'en', '', FALSE),
      $this->fileLanguageValue($this->createDocumentFile('report-fr.pdf'), 'fr', '', FALSE),
    ]);

    $field = $this->languageField(['en', 'fr']);
    $items = [$this->indexItem($node, $field)];

    edw_document_search_api_index_items_alter($this->index, $items);

    $this->assertSame([], $field->getValues());
  }

  /**
   * Tests that a language is kept while any visible file still has it.
   *
   * Two files can share a language — a hidden draft and a published final —
   * and hiding one of them must not remove the language altogether.
   */
  public function testLanguageSurvivesWhileVisibleFileStillHasIt(): void {
    $node = $this->createDocumentNode([
      $this->fileLanguageValue($this->createDocumentFile('report-fr-draft.pdf'), 'fr', '', FALSE),
      $this->fileLanguageValue($this->createDocumentFile('report-fr-final.pdf'), 'fr', '', TRUE),
    ]);

    $field = $this->languageField(['fr']);
    $items = [$this->indexItem($node, $field)];

    edw_document_search_api_index_items_alter($this->index, $items);

    $this->assertSame(['fr'], $field->getValues());
  }

  /**
   * Tests that an item indexing an entity without the field is left alone.
   */
  public function testAnEntityWithoutTheFileFieldIsLeftAlone(): void {
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $page = Node::create(['type' => 'page', 'title' => 'About us']);
    $page->save();

    $field = $this->languageField(['en']);
    $items = [$this->indexItem($page, $field)];

    edw_document_search_api_index_items_alter($this->index, $items);

    $this->assertSame(['en'], $field->getValues());
  }

  /**
   * Tests that every item in the batch is cleaned, not just the first.
   *
   * Search API hands over a whole batch at once. An index fed by more than
   * one datasource can contain items that have no `file_language` field at
   * all, and skipping one of those must not abandon the rest of the batch —
   * otherwise which documents get cleaned depends on the order Search API
   * happened to queue them in.
   */
  public function testEveryItemInTheBatchIsCleaned(): void {
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $page = Node::create(['type' => 'page', 'title' => 'About us']);
    $page->save();

    $document = $this->createDocumentNode([
      $this->fileLanguageValue($this->createDocumentFile('report-en.pdf'), 'en', '', TRUE),
      $this->fileLanguageValue($this->createDocumentFile('report-fr.pdf'), 'fr', '', FALSE),
    ]);

    $documentField = $this->languageField(['en', 'fr']);
    $items = [
      // An item the hook has no opinion about, queued first.
      $this->indexItem($page, NULL),
      $this->indexItem($document, $documentField),
    ];

    edw_document_search_api_index_items_alter($this->index, $items);

    $this->assertSame(['en'], $documentField->getValues());
  }

  /**
   * Builds the indexed language field.
   *
   * @param array $langcodes
   *   The languages Search API collected from the file items.
   *
   * @return \Drupal\search_api\Item\Field
   *   The index field.
   */
  protected function languageField(array $langcodes): Field {
    $field = new Field($this->index, 'file_language');
    $field->setPropertyPath('field_files:language');
    $field->setValues($langcodes);

    return $field;
  }

  /**
   * Builds an index item for an entity.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The entity being indexed.
   * @param \Drupal\search_api\Item\Field|null $field
   *   The `file_language` field on the item, or NULL when the item's
   *   datasource does not provide one.
   *
   * @return \Drupal\search_api\Item\ItemInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The index item.
   */
  protected function indexItem(NodeInterface $node, ?Field $field) {
    $original = $this->createMock(ComplexDataInterface::class);
    $original->method('getValue')->willReturn($node);

    $item = $this->createMock(ItemInterface::class);
    $item->method('getField')->with('file_language')->willReturn($field);
    $item->method('getOriginalObject')->willReturn($original);

    return $item;
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
