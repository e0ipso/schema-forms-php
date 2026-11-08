<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\JsonShape;

/**
 * Arrays take the JSON shape their schema describes.
 */
#[CoversClass(JsonShape::class)]
final class JsonShapeTest extends TestCase {

  /**
   * Saved values keep the JSON shape their schema gives them.
   */
  #[DataProvider('shapeProvider')]
  public function testShape(mixed $data, string $schema, string $json): void {
    $this->assertSame($json, json_encode(JsonShape::fromSchema($data, json_decode($schema))));
  }

  /**
   * Provides schemas and saved values with the JSON shape they keep.
   *
   * @return array
   *   The test cases.
   */
  public static function shapeProvider(): array {
    $nested = '{"type":"object","properties":{"o":{"type":"object","properties":{"0":{"type":"string"}}},"l":{"type":"array","items":{"type":"object"}}}}';
    return [
      'integer names' => [['a', 'b'], '{"type":"object"}', '{"0":"a","1":"b"}'],
      'empty object' => [[], '{"type":"object"}', '{}'],
      'array or object' => [['a'], '{"type":["array","object"]}', '["a"]'],
      'nullable object' => [['a'], '{"type":["object","null"]}', '{"0":"a"}'],
      'untyped list' => [['a'], '{}', '["a"]'],
      'untyped map' => [['k' => 'a'], '{}', '{"k":"a"}'],
      'nested' => [['o' => ['x'], 'l' => [['y'], []]], $nested, '{"o":{"0":"x"},"l":[{"0":"y"},{}]}'],
      'undeclared property' => [['p' => ['x']], '{"type":"object","properties":{}}', '{"p":["x"]}'],
      'scalar' => ['a', '{"type":"object"}', '"a"'],
      'no schema' => [['a'], 'null', '["a"]'],
    ];
  }

}
