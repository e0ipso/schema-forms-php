<?php

namespace SchemaForms\Tests;

use PHPUnit\Framework\TestCase;
use SchemaForms\ArrayToStdClass;
use Shaper\Transformation\TransformationInterface;

/**
 * Unit tests for the \SchemaForms\ArrayToStdClass class.
 *
 * @package SchemaForms
 *
 * @coversDefaultClass \SchemaForms\ArrayToStdClass
 */
class ArrayToStdClassTest extends TestCase {

  /**
   * The validator.
   *
   * @var \Shaper\Transformation\TransformationInterface
   */
  private TransformationInterface $sut;

  /**
   * Data provider for testTransform.
   *
   * @return array
   *   The data.
   */
  public function dataProviderTransform(): array {
    return [
      [2, 2],
      [null, null],
      [$this, (object) []],
      [
        ['foo' => [1, 3, ['bar' => 'baz']]],
        (object) ['foo' => [1, 3, (object) ['bar' => 'baz']]],
      ],
      [
        ['foo' => [1, 3, ['bar' => 'baz']]],
        (object) ['foo' => [1, 3, (object) ['bar' => 'baz']]],
      ],
      [
        [['foo' => 'bar'], 'lorem', ['cowsays' => 'moo']],
        [(object) ['foo' => 'bar'], 'lorem', (object) ['cowsays' => 'moo']],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->sut = new ArrayToStdClass();
  }

  /**
   * Tests the transformation.
   *
   * @dataProvider dataProviderTransform
   */
  public function testTransform(mixed $data, mixed $expected): void {
    $actual = $this->sut->transform($data);
    $this->assertEquals($expected, $actual);
  }

}
