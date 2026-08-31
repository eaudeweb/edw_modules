<?php

namespace Drupal\Tests\edw_utilities\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\edw_utilities\Plugin\search_api\processor\FieldValueIndex;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests keeping content out of a search index by field value.
 *
 * One index often serves several audiences, and not everything with the right
 * content type belongs in it — internal-only pages, or documents flagged as
 * superseded. This processor drops items whose field does not carry the
 * configured value, before they are ever handed to the backend.
 *
 * @coversDefaultClass \Drupal\edw_utilities\Plugin\search_api\processor\FieldValueIndex
 *
 * @group edw_utilities
 */
class FieldValueIndexTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'options',
    'filter',
    'node',
    'taxonomy',
    'search_api',
    'edw_utilities',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    Vocabulary::create(['vid' => 'audiences', 'name' => 'Audiences'])->save();

    $this->createField('field_public', 'boolean');
    $this->createField('field_audience', 'entity_reference', ['target_type' => 'taxonomy_term']);
  }

  /**
   * Tests that only content with the configured value is indexed.
   *
   * @covers ::alterIndexedItems
   */
  public function testOnlyContentWithTheConfiguredValueIsIndexed(): void {
    $public = $this->createPage('Public page', ['field_public' => 1]);
    $internal = $this->createPage('Internal page', ['field_public' => 0]);

    $items = [
      'public' => $this->indexItem($public),
      'internal' => $this->indexItem($internal),
    ];
    $this->processor('field_public=1')->alterIndexedItems($items);

    $this->assertSame(['public'], array_keys($items));
  }

  /**
   * Tests that the condition can be turned around.
   *
   * The same field often needs the opposite index — everything the public
   * index leaves out.
   *
   * @covers ::alterIndexedItems
   */
  public function testTheConditionCanBeTurnedAround(): void {
    $public = $this->createPage('Public page', ['field_public' => 1]);
    $internal = $this->createPage('Internal page', ['field_public' => 0]);

    $items = [
      'public' => $this->indexItem($public),
      'internal' => $this->indexItem($internal),
    ];
    $this->processor('field_public=1', TRUE)->alterIndexedItems($items);

    $this->assertSame(['internal'], array_keys($items));
  }

  /**
   * Tests that a reference field is matched on what it points at.
   *
   * Reading the 'value' column of a reference field would find nothing and
   * silently empty the index.
   *
   * @covers ::alterIndexedItems
   */
  public function testReferenceFieldIsMatchedOnWhatItPointsAt(): void {
    $wanted = Term::create(['vid' => 'audiences', 'name' => 'Delegates']);
    $wanted->save();
    $other = Term::create(['vid' => 'audiences', 'name' => 'Press']);
    $other->save();

    $forDelegates = $this->createPage('For delegates', ['field_audience' => $wanted->id()]);
    $forPress = $this->createPage('For press', ['field_audience' => $other->id()]);

    $items = [
      'delegates' => $this->indexItem($forDelegates),
      'press' => $this->indexItem($forPress),
    ];
    $this->processor('field_audience=' . $wanted->id())->alterIndexedItems($items);

    $this->assertSame(['delegates'], array_keys($items));
  }

  /**
   * Tests that content without the field at all is left in the index.
   *
   * An index spanning several content types will contain items the filter
   * cannot speak about; dropping those would make one type's field decide
   * whether another type is searchable.
   *
   * @covers ::alterIndexedItems
   */
  public function testContentWithoutTheFieldIsLeftInTheIndex(): void {
    $article = Node::create(['type' => 'article', 'title' => 'An article']);
    $article->save();
    $internal = $this->createPage('Internal page', ['field_public' => 0]);

    $items = [
      'article' => $this->indexItem($article),
      'internal' => $this->indexItem($internal),
    ];
    $this->processor('field_public=1')->alterIndexedItems($items);

    $this->assertSame(['article'], array_keys($items));
  }

  /**
   * Tests that an empty field does not match.
   *
   * @covers ::alterIndexedItems
   */
  public function testAnEmptyFieldDoesNotMatch(): void {
    $unset = $this->createPage('Never flagged');

    $items = ['unset' => $this->indexItem($unset)];
    $this->processor('field_public=1')->alterIndexedItems($items);

    $this->assertSame([], array_keys($items));
  }

  /**
   * Tests that a filter without a value is rejected on the settings form.
   *
   * The filter is parsed by splitting on '=', so anything else would reach
   * alterIndexedItems() as a missing array offset at index time — long after
   * the site builder has moved on.
   *
   * @covers ::validateConfigurationForm
   */
  public function testFilterWithoutValueIsRejected(): void {
    foreach (['field_public', 'field_public=1=2', ''] as $filter) {
      $formState = new FormState();
      $formState->setValue('filters', $filter);
      $form = [];

      $this->processor()->validateConfigurationForm($form, $formState);

      $this->assertNotEmpty($formState->getErrors(), "'$filter' is rejected.");
    }
  }

  /**
   * Tests that a well-formed filter is accepted.
   *
   * @covers ::validateConfigurationForm
   */
  public function testWellFormedFilterIsAccepted(): void {
    $formState = new FormState();
    $formState->setValue('filters', ' field_public=1 ');
    $form = [];

    $this->processor()->validateConfigurationForm($form, $formState);

    $this->assertSame([], $formState->getErrors());
  }

  /**
   * Builds the processor with a filter.
   *
   * @param string $filters
   *   The field=value filter.
   * @param bool $negate
   *   Whether to invert the condition.
   *
   * @return \Drupal\edw_utilities\Plugin\search_api\processor\FieldValueIndex
   *   The processor.
   */
  protected function processor(string $filters = '', bool $negate = FALSE): FieldValueIndex {
    return new FieldValueIndex(
      ['filters' => $filters, 'negate' => $negate],
      'field_value',
      []
    );
  }

  /**
   * Builds an index item wrapping an entity.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The entity being indexed.
   *
   * @return \Drupal\search_api\Item\ItemInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The index item.
   */
  protected function indexItem(NodeInterface $node) {
    $original = $this->createMock('Drupal\Core\TypedData\ComplexDataInterface');
    $original->method('getValue')->willReturn($node);

    $item = $this->createMock(ItemInterface::class);
    $item->method('getOriginalObject')->willReturn($original);

    return $item;
  }

  /**
   * Creates a page.
   *
   * @param string $title
   *   The page title.
   * @param array $values
   *   Extra field values.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved page.
   */
  protected function createPage(string $title, array $values = []): NodeInterface {
    $node = Node::create(['type' => 'page', 'title' => $title] + $values);
    $node->save();

    return $node;
  }

  /**
   * Creates a field on the page content type.
   *
   * @param string $fieldName
   *   The field name.
   * @param string $type
   *   The field type.
   * @param array $storageSettings
   *   Storage-level settings.
   */
  protected function createField(string $fieldName, string $type, array $storageSettings = []): void {
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => $fieldName,
      'type' => $type,
      'settings' => $storageSettings,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'page',
      'field_name' => $fieldName,
      'label' => $fieldName,
    ])->save();
  }

}
