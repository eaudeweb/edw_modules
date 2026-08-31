<?php

namespace Drupal\Tests\edw_paragraphs\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\edw_paragraphs_carousel\Plugin\Validation\Constraint\CarouselItemsCardinality;
use Drupal\edw_paragraphs_carousel\Plugin\Validation\Constraint\CarouselItemsCardinalityValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * Tests that a carousel is not saved with too few slides.
 *
 * A carousel with one slide is not a carousel — it renders as a slider that
 * cannot slide, usually with visible but inert controls. The editor should be
 * told at save time rather than discovering it on the page.
 *
 * @coversDefaultClass \Drupal\edw_paragraphs_carousel\Plugin\Validation\Constraint\CarouselItemsCardinalityValidator
 *
 * @group edw_paragraphs
 */
class CarouselItemsCardinalityValidatorTest extends UnitTestCase {

  /**
   * The constraint being validated.
   *
   * @var \Drupal\edw_paragraphs_carousel\Plugin\Validation\Constraint\CarouselItemsCardinality
   */
  protected $constraint;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->constraint = new CarouselItemsCardinality();
  }

  /**
   * Tests that two or more slides are accepted.
   *
   * @covers ::validate
   */
  public function testTwoOrMoreSlidesAreAccepted(): void {
    $this->assertViolationCount(0, $this->itemsList(2));
    $this->assertViolationCount(0, $this->itemsList(5));
  }

  /**
   * Tests that a single slide is refused.
   *
   * @covers ::validate
   */
  public function testSingleSlideIsRefused(): void {
    $this->assertViolationCount(1, $this->itemsList(1));
  }

  /**
   * Tests that an empty carousel is refused.
   *
   * @covers ::validate
   */
  public function testAnEmptyCarouselIsRefused(): void {
    $this->assertViolationCount(1, $this->itemsList(0));
  }

  /**
   * Tests that the editor is told what is wrong.
   *
   * @covers ::validate
   */
  public function testTheEditorIsToldWhatIsWrong(): void {
    $this->assertSame(
      'The Carousel paragraph should contain at least 2 items.',
      $this->constraint->message
    );
  }

  /**
   * Asserts how many violations validating a value produces.
   *
   * @param int $expected
   *   The number of violations expected.
   * @param object $value
   *   The field item list being validated.
   */
  protected function assertViolationCount(int $expected, $value): void {
    $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
    $builder->expects($this->exactly($expected))->method('addViolation');

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->exactly($expected))
      ->method('buildViolation')
      ->with($this->constraint->message)
      ->willReturn($builder);

    $validator = new CarouselItemsCardinalityValidator();
    $validator->initialize($context);
    $validator->validate($value, $this->constraint);
  }

  /**
   * Builds a field item list holding the given number of slides.
   *
   * @param int $count
   *   How many slides the carousel references.
   *
   * @return object
   *   Something answering getValue() the way a field item list does.
   */
  protected function itemsList(int $count) {
    $items = $this->createMock('Drupal\Core\Field\FieldItemListInterface');
    $items->method('getValue')->willReturn(array_fill(0, $count, ['target_id' => 1]));

    return $items;
  }

}
