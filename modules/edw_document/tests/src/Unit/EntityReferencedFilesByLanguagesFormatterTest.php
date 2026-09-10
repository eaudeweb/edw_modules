<?php

namespace Drupal\Tests\edw_document\Unit;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\edw_document\Plugin\Field\FieldFormatter\EntityReferencedFilesByLanguagesFormatter;

/**
 * Tests which fields offer the referenced-files-by-language formatter.
 *
 * The formatter reaches through the referenced entity to its media source
 * field to find a file. That only makes sense for media references, so it has
 * to stay off the display options of every other entity reference field —
 * otherwise a site builder can pick it for a taxonomy or node reference and
 * get an empty table with no explanation.
 *
 * @coversDefaultClass \Drupal\edw_document\Plugin\Field\FieldFormatter\EntityReferencedFilesByLanguagesFormatter
 *
 * @group edw_document
 */
class EntityReferencedFilesByLanguagesFormatterTest extends UnitTestCase {

  /**
   * Tests that the formatter is offered for media reference fields.
   *
   * @covers ::isApplicable
   */
  public function testIsApplicableToMediaReferenceFields(): void {
    $this->assertTrue(EntityReferencedFilesByLanguagesFormatter::isApplicable(
      $this->referenceFieldTo('media')
    ));
  }

  /**
   * Tests that the formatter is withheld from other reference fields.
   *
   * @covers ::isApplicable
   */
  public function testIsNotApplicableToOtherReferenceFields(): void {
    $this->assertFalse(EntityReferencedFilesByLanguagesFormatter::isApplicable(
      $this->referenceFieldTo('node')
    ));
    $this->assertFalse(EntityReferencedFilesByLanguagesFormatter::isApplicable(
      $this->referenceFieldTo('taxonomy_term')
    ));
    $this->assertFalse(EntityReferencedFilesByLanguagesFormatter::isApplicable(
      $this->referenceFieldTo('file')
    ));
  }

  /**
   * Builds a field definition for an entity reference to the given type.
   *
   * @param string $targetType
   *   The referenced entity type id.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The field definition.
   */
  protected function referenceFieldTo(string $targetType) {
    $storage = $this->createMock(FieldStorageDefinitionInterface::class);
    $storage->method('getSetting')->with('target_type')->willReturn($targetType);

    $definition = $this->createMock(FieldDefinitionInterface::class);
    $definition->method('getFieldStorageDefinition')->willReturn($storage);

    return $definition;
  }

}
