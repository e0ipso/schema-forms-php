<?php

namespace SchemaForms\Tests;

use PHPUnit\Framework\TestCase;
use SchemaForms\RenderArrayValidator;

/**
 * Unit tests for the \SchemaForms\RenderArrayValidator class.
 *
 * @package SchemaForms
 *
 * @coversDefaultClass \SchemaForms\RenderArrayValidator
 */
class RenderArrayValidatorTest extends TestCase {

  /**
   * The validator.
   *
   * @var \SchemaForms\RenderArrayValidator
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
   *
   * @dataProvider dataProviderIsValid
   */
  public function testIsValid($render_array, bool $expected) {
    $actual = $this->sut->isValid($render_array);
    $this->assertSame($expected, $actual);
  }

  /**
   * Data provider for the testIsValid.
   *
   * @return array
   *   The data.
   */
  public function dataProviderIsValid() {
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
        TRUE
      ]
    ];
  }

}
