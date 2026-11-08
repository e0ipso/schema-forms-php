<?php

namespace SchemaForms\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\ArrayToStdClass;
use SchemaForms\Exception\ExceptionInterface;
use SchemaForms\Exception\UnencodableDataException;
use Shaper\Transformation\TransformationInterface;

/**
 * Unit tests for the \SchemaForms\ArrayToStdClass class.
 *
 * @package SchemaForms
 */
#[CoversClass(ArrayToStdClass::class)]
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
  public static function dataProviderTransform(): array {
    return [
      [2, 2],
      [null, null],
      // An object with only non-public state, as the test case itself was.
      [
        new class() {
          private string $hidden = 'hidden';
        },
        (object) [],
      ],
      [
        ['foo' => [1, 3, ['bar' => 'baz']]],
        (object) ['foo' => [1, 3, (object) ['bar' => 'baz']]],
      ],
      // Unencodable input keeps the empty-object fallback.
      [
        ['foo' => INF],
        new \stdClass(),
      ],
      [
        [['foo' => 'bar'], 'lorem', ['cowsays' => 'moo']],
        [(object) ['foo' => 'bar'], 'lorem', (object) ['cowsays' => 'moo']],
      ],
    ];
  }

  /**
   * convert() reports data with no JSON encoding.
   */
  #[DataProvider('dataProviderUnencodable')]
  public function testConvertThrows(mixed $data, string $cause): void {
    try {
      ArrayToStdClass::convert($data);
      $this->fail('Unencodable data must be rejected.');
    }
    catch (UnencodableDataException $e) {
      $this->assertInstanceOf(ExceptionInterface::class, $e);
      $this->assertInstanceOf(\InvalidArgumentException::class, $e);
      $this->assertInstanceOf(\JsonException::class, $e->getPrevious());
      $this->assertSame(0, $e->getCode());
      $this->assertSame('The data cannot be encoded as JSON: ' . $cause, $e->getMessage());
    }
    // The transformation keeps its empty-object fallback for the same data.
    $this->assertEquals(new \stdClass(), $this->sut->transform($data));
  }

  /**
   * Data provider for testConvertThrows.
   */
  public static function dataProviderUnencodable(): array {
    $recursive = new \stdClass();
    $recursive->self = $recursive;
    return [
      'infinity' => [['foo' => INF], 'Inf and NaN cannot be JSON encoded'],
      'invalid UTF-8' => [["\xB1\x31"], 'Malformed UTF-8 characters, possibly incorrectly encoded'],
      'recursion' => [$recursive, 'Recursion detected'],
    ];
  }

  /**
   * convert() gives encodable data the same shape as transform().
   */
  public function testConvert(): void {
    $data = ['foo' => [1, 3, ['bar' => 'baz']], 'list' => [['a' => 1]]];
    $expected = (object) ['foo' => [1, 3, (object) ['bar' => 'baz']], 'list' => [(object) ['a' => 1]]];
    $this->assertEquals($expected, ArrayToStdClass::convert($data));
    $this->assertEquals($expected, $this->sut->transform($data));
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
   */
  #[DataProvider('dataProviderTransform')]
  public function testTransform(mixed $data, mixed $expected): void {
    $actual = $this->sut->transform($data);
    $this->assertEquals($expected, $actual);
  }

}
