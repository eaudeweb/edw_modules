<?php

namespace Drupal\Tests\edw_document\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\edw_document\Plugin\facets\processor\ListLanguageProcessor;
use Drupal\edw_document\Services\FileLanguageManager;
use Drupal\facets\FacetInterface;
use Drupal\facets\Result\Result;

/**
 * Tests the language facet's display labels.
 *
 * The documents index stores each file's language as a raw langcode, so the
 * facet block would otherwise offer visitors a list reading "ar, en, fr, ru".
 * This processor relabels the results at build time, leaving the raw values
 * alone so the facet's URLs and its query keep working.
 *
 * @coversDefaultClass \Drupal\edw_document\Plugin\facets\processor\ListLanguageProcessor
 *
 * @group edw_document
 */
class ListLanguageProcessorTest extends UnitTestCase {

  /**
   * The processor under test.
   *
   * @var \Drupal\edw_document\Plugin\facets\processor\ListLanguageProcessor
   */
  protected $processor;

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

    $fileLanguageManager = $this->createMock(FileLanguageManager::class);
    $fileLanguageManager->method('getLanguageName')->willReturnMap([
      ['en', 'English'],
      ['fr', 'French'],
      ['ar', 'Arabic'],
      ['zz', NULL],
      ['', NULL],
    ]);

    $this->facet = $this->createMock(FacetInterface::class);
    $this->processor = new ListLanguageProcessor([], 'file_language_processor', [], $fileLanguageManager);
  }

  /**
   * Tests that each langcode is relabelled with its language name.
   *
   * @covers ::build
   */
  public function testLangcodesAreRelabelledWithLanguageNames(): void {
    $results = [
      new Result($this->facet, 'en', 'en', 12),
      new Result($this->facet, 'fr', 'fr', 5),
      new Result($this->facet, 'ar', 'ar', 2),
    ];

    $built = $this->processor->build($this->facet, $results);

    $this->assertSame(
      ['English', 'French', 'Arabic'],
      array_map(fn (Result $result) => $result->getDisplayValue(), $built)
    );
  }

  /**
   * Tests that the raw values and counts are left untouched.
   *
   * The raw value is what the facet puts in the URL and matches against the
   * index, so relabelling must be cosmetic only.
   *
   * @covers ::build
   */
  public function testRawValuesAndCountsAreNotChanged(): void {
    $results = [new Result($this->facet, 'fr', 'fr', 5)];

    $built = $this->processor->build($this->facet, $results);

    $this->assertSame('fr', $built[0]->getRawValue());
    $this->assertSame(5, $built[0]->getCount());
  }

  /**
   * Tests that an unrecognised langcode keeps whatever label it had.
   *
   * Overwriting it with an empty string would give the visitor a blank,
   * unclickable-looking facet item; leaving the langcode at least says
   * something.
   *
   * @covers ::build
   */
  public function testUnknownLangcodesKeepTheirExistingLabel(): void {
    $results = [
      new Result($this->facet, 'zz', 'zz', 1),
      new Result($this->facet, 'fr', 'fr', 5),
    ];

    $built = $this->processor->build($this->facet, $results);

    $this->assertSame('zz', $built[0]->getDisplayValue());
    // A miss must not stop the rest of the list from being relabelled.
    $this->assertSame('French', $built[1]->getDisplayValue());
  }

  /**
   * Tests that the result list is returned intact.
   *
   * @covers ::build
   */
  public function testTheResultListIsReturnedWithItsKeys(): void {
    $results = [
      'a' => new Result($this->facet, 'en', 'en', 12),
      'b' => new Result($this->facet, 'fr', 'fr', 5),
    ];

    $built = $this->processor->build($this->facet, $results);

    $this->assertSame(['a', 'b'], array_keys($built));
  }

  /**
   * Tests that an empty result set is handled.
   *
   * @covers ::build
   */
  public function testAnEmptyResultSetIsReturnedUnchanged(): void {
    $this->assertSame([], $this->processor->build($this->facet, []));
  }

}
