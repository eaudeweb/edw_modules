<?php

namespace Drupal\Tests\edw_utilities\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\edw_utilities\Plugin\facets\processor\TransformLanguageIdProcessor;
use Drupal\facets\FacetInterface;
use Drupal\facets\Result\Result;

/**
 * Tests the language labels shown on a language facet.
 *
 * A facet built over a language field holds bare langcodes, so without this
 * the block offers visitors a list reading "ar, en, fr". Sites also define
 * languages of their own through languagefield — a document may be in a
 * language the site itself is not translated into — so the lookup falls back
 * to those before giving up.
 *
 * @coversDefaultClass \Drupal\edw_utilities\Plugin\facets\processor\TransformLanguageIdProcessor
 *
 * @group edw_utilities
 */
class TransformLanguageIdProcessorTest extends UnitTestCase {

  /**
   * The facet the results belong to.
   *
   * @var \Drupal\facets\FacetInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $facet;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->facet = $this->createMock(FacetInterface::class);
  }

  /**
   * Tests that an installed language is shown by name.
   *
   * @covers ::build
   */
  public function testAnInstalledLanguageIsShownByName(): void {
    $results = [
      new Result($this->facet, 'fr', 'fr', 4),
      new Result($this->facet, 'en', 'en', 9),
    ];

    $built = $this->processor()->build($this->facet, $results);

    $this->assertSame('French', $built[0]->getDisplayValue());
    $this->assertSame('English', $built[1]->getDisplayValue());
  }

  /**
   * Tests that the raw value is left alone.
   *
   * The raw value is what the facet matches against the index and puts in the
   * URL, so relabelling has to be cosmetic.
   *
   * @covers ::build
   */
  public function testTheRawValueIsLeftAlone(): void {
    $results = [new Result($this->facet, 'fr', 'fr', 4)];

    $built = $this->processor()->build($this->facet, $results);

    $this->assertSame('fr', $built[0]->getRawValue());
    $this->assertSame(4, $built[0]->getCount());
  }

  /**
   * Tests that an unknown langcode is left as it is without languagefield.
   *
   * @covers ::build
   */
  public function testAnUnknownLangcodeIsLeftAloneWithoutLanguagefield(): void {
    $results = [
      new Result($this->facet, 'zz', 'zz', 1),
      new Result($this->facet, 'fr', 'fr', 4),
    ];

    $built = $this->processor()->build($this->facet, $results);

    $this->assertSame('zz', $built[0]->getDisplayValue());
    $this->assertSame('French', $built[1]->getDisplayValue(), 'A miss does not stop the rest being relabelled.');
  }

  /**
   * Tests that a site-defined language is looked up as a fallback.
   *
   * @covers ::build
   */
  public function testSiteDefinedLanguageIsLookedUpAsFallback(): void {
    $custom = $this->createMock('Drupal\Core\Entity\EntityInterface');
    $custom->method('label')->willReturn('Kiswahili');

    $storage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
    $storage->method('load')->with('sw')->willReturn($custom);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('custom_language')->willReturn($storage);

    $results = [new Result($this->facet, 'sw', 'sw', 2)];

    $built = $this->processor(TRUE, $entityTypeManager)->build($this->facet, $results);

    $this->assertSame('Kiswahili', $built[0]->getDisplayValue());
  }

  /**
   * Tests that a langcode nobody knows survives both lookups.
   *
   * @covers ::build
   */
  public function testLangcodeNobodyKnowsSurvivesBothLookups(): void {
    $storage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
    $storage->method('load')->willReturn(NULL);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturn($storage);

    $results = [new Result($this->facet, 'zz', 'zz', 1)];

    $built = $this->processor(TRUE, $entityTypeManager)->build($this->facet, $results);

    $this->assertSame('zz', $built[0]->getDisplayValue());
  }

  /**
   * Tests that the processor is only offered for string facets.
   *
   * A langcode is a string; offering this on a numeric or entity facet would
   * put it in front of site builders where it can only produce nonsense.
   *
   * @covers ::supportsFacet
   */
  public function testTheProcessorIsOnlyOfferedForStringFacets(): void {
    $this->assertTrue($this->processor()->supportsFacet($this->facetOfType('string')));
    $this->assertFalse($this->processor()->supportsFacet($this->facetOfType('integer')));
    $this->assertFalse($this->processor()->supportsFacet($this->facetOfType('entity_reference')));
  }

  /**
   * Tests that the labels vary by interface language.
   *
   * The names are translated, so a cached facet block must not be served to a
   * visitor browsing in another language.
   *
   * @covers ::getCacheContexts
   */
  public function testTheLabelsVaryByInterfaceLanguage(): void {
    $this->assertContains('languages:language_interface', $this->processor()->getCacheContexts());
  }

  /**
   * Builds the processor under test.
   *
   * @param bool $hasLanguagefield
   *   Whether the languagefield module is installed.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface|null $entityTypeManager
   *   The entity type manager, for the languagefield fallback.
   *
   * @return \Drupal\edw_utilities\Plugin\facets\processor\TransformLanguageIdProcessor
   *   The processor.
   */
  protected function processor(bool $hasLanguagefield = FALSE, ?EntityTypeManagerInterface $entityTypeManager = NULL): TransformLanguageIdProcessor {
    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $languageManager->method('getLanguage')->willReturnCallback(function ($langcode) {
      $known = ['en' => 'English', 'fr' => 'French'];

      return isset($known[$langcode])
        ? new Language(['id' => $langcode, 'name' => $known[$langcode]])
        : NULL;
    });

    $moduleHandler = $this->createMock(ModuleHandler::class);
    $moduleHandler->method('moduleExists')->with('languagefield')->willReturn($hasLanguagefield);

    return new TransformLanguageIdProcessor(
      [],
      'transform_language_id',
      [],
      $languageManager,
      $entityTypeManager ?: $this->createMock(EntityTypeManagerInterface::class),
      $moduleHandler
    );
  }

  /**
   * Builds a facet whose indexed data is of the given type.
   *
   * @param string $dataType
   *   The data type of the facet's field.
   *
   * @return \Drupal\facets\FacetInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The facet.
   */
  protected function facetOfType(string $dataType) {
    $definition = $this->createMock(DataDefinitionInterface::class);
    $definition->method('getDataType')->willReturn($dataType);

    $facet = $this->createMock(FacetInterface::class);
    $facet->method('getDataDefinition')->willReturn($definition);

    return $facet;
  }

}
