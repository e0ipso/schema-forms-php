<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\RenderArrayValidator;

/**
 * Unit tests for the \SchemaForms\Drupal\RenderArrayValidator class.
 *
 * @package SchemaForms
 */
#[CoversClass(RenderArrayValidator::class)]
class RenderArrayValidatorTest extends TestCase {

  /**
   * The validator.
   *
   * @var \SchemaForms\Drupal\RenderArrayValidator
   */
  private $sut;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->sut = new RenderArrayValidator();
  }

  /**
   * Tests if the render array is valid.
   */
  #[DataProvider('dataProviderIsValid')]
  public function testIsValid($render_array, bool $expected) {
    $actual = $this->sut->isValid($render_array);
    $this->assertSame($expected, $actual);
  }

  /**
   * A rejection records an error, and the next check clears it.
   */
  public function testErrors(): void {
    $this->assertFalse($this->sut->isValid(['fa' => 'mily']));
    $this->assertSame(['Expected a render array: an array with a "#" key, or with a child that is a render array.'], $this->sut->getErrors());
    $this->assertTrue($this->sut->isValid(['#fa' => 'mily']));
    $this->assertSame([], $this->sut->getErrors());
  }

  /**
   * Data provider for the testIsValid.
   *
   * @return array
   *   The data.
   */
  public static function dataProviderIsValid() {
    return [
      [
        ['#fa' => 'mily'],
        TRUE,
      ],
      [
        ['fa' => 'mily'],
        FALSE,
      ],
      [
        [0 => 'ze', '#ro' => ''],
        TRUE,
      ],
      [
        [0 => 'ze', 'ro' => []],
        FALSE,
      ],
      [
        ['fa' => ['#fa' => 'mily']],
        TRUE,
      ],
      [
        ['fa' => [['#fa' => 'mily']]],
        TRUE,
      ],
      [
        '#fa',
        FALSE,
      ],
      [
        NULL,
        FALSE,
      ],
      [
        INF,
        FALSE,
      ],
      [
        [
          '#va' => [['Foo\Bar', 'validateWithSchema']],
          'fa' => ['#fa' => 'mily'],
        ],
        TRUE,
      ],
      // A render property holds any value, not only a string.
      [
        ['#markup' => new \stdClass()],
        TRUE,
      ],
      // Any child render array is enough; an empty sibling does not reject it.
      [
        ['fa' => [], 'mily' => ['#type' => 'item']],
        TRUE,
      ],
      [
        [],
        FALSE,
      ],
      // Drupal reads only a leading "#" as a render property.
      [
        ['a#b' => 1],
        FALSE,
      ],
      [
        ['fa' => ['mi#ly' => 'x']],
        FALSE,
      ],
      [
        ['fa' => [[]]],
        FALSE,
      ],
    ];
  }

}
