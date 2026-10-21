<?php

namespace SchemaForms\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\RecursiveTypeCaster;

/**
 * Unit tests for the \SchemaForms\RecursiveTypeCaster class.
 *
 * @package SchemaForms
 */
#[CoversClass(RecursiveTypeCaster::class)]
class RecursiveTypeCasterTest extends TestCase {

  /**
   * Data provider for testRecursiveTypeRefinements.
   *
   * @return array
   *   The data.
   */
  public static function dataProviderRecursiveTypeRefinements(): array {
    return [
      'fractional number enum' => ['0.5', '{"type":"number","enum":[0.5,1.5]}', 0.5],
      // A `null` member means the property also accepts absence. It does not
      // mean every falsy value the other members can hold is absence.
      'nullable string keeps the empty string' => ['', '{"type":["string","null"]}', ''],
      'nullable string keeps "0"' => ['0', '{"type":["string","null"]}', '0'],
      'nullable string keeps a normal value' => ['lorem', '{"type":["string","null"]}', 'lorem'],
      'nullable string spells 0 as a string' => [0, '{"type":["string","null"]}', '0'],
      'nullable boolean keeps FALSE' => [FALSE, '{"type":["boolean","null"]}', FALSE],
      'nullable boolean keeps a submitted "0"' => ['0', '{"type":["boolean","null"]}', FALSE],
      'nullable boolean keeps a submitted "1"' => ['1', '{"type":["boolean","null"]}', TRUE],
      'nullable integer keeps 0' => [0, '{"type":["integer","null"]}', 0],
      'nullable integer keeps a submitted "0"' => ['0', '{"type":["integer","null"]}', 0],
      'nullable number keeps 0.0' => [0.0, '{"type":["number","null"]}', 0.0],
      // NULL always means absence, and so does an untouched control on a
      // property no declared type of which can hold the empty string.
      'NULL stays NULL' => [NULL, '{"type":["string","null"]}', NULL],
      'nullable integer nulls the empty string' => ['', '{"type":["integer","null"]}', NULL],
      'nullable number nulls the empty string' => ['', '{"type":["number","null"]}', NULL],
      'nullable boolean nulls the empty string' => ['', '{"type":["boolean","null"]}', NULL],
      // A constrained string cannot hold the empty string either.
      'nullable string with minLength nulls it' => ['', '{"type":["string","null"],"minLength":1}', NULL],
      'nullable string with a format nulls it' => ['', '{"type":["string","null"],"format":"uri"}', NULL],
      'nullable string with a pattern nulls it' => ['', '{"type":["string","null"],"pattern":"^a+$"}', NULL],
      'nullable enum without "" nulls it' => ['', '{"type":["string","null"],"enum":[null,"a","b"]}', NULL],
      'nullable enum listing "" keeps it' => ['', '{"type":["string","null"],"enum":["","a","b"]}', ''],
      // A property that does not accept `null` is untouched by any of this.
      'plain string keeps the empty string' => ['', '{"type":"string"}', ''],
      'plain string keeps "0"' => ['0', '{"type":"string"}', '0'],
      // Nested properties are refined with their own sub-schema.
      'object properties are refined individually' => [
        ['a' => '', 'b' => '', 'c' => '0'],
        '{"type":"object","properties":{"a":{"type":["string","null"]},"b":{"type":["integer","null"]},"c":{"type":["string","null"]}}}',
        ['a' => '', 'b' => NULL, 'c' => '0'],
      ],
      // SCHE-0018: unsafe numeric conversions must retain the input string.
      'non-finite positive exponent' => ['1e999', '{"type":"number"}', '1e999'],
      'non-finite negative exponent' => ['-1e999', '{"type":"number"}', '-1e999'],
      'positive integer overflow' => ['99999999999999999999', '{"type":"integer"}', '99999999999999999999'],
      'negative integer overflow' => ['-99999999999999999999', '{"type":"integer"}', '-99999999999999999999'],
      'ordinary negative decimal' => ['-1.5', '{"type":"number"}', -1.5],
      // Numeric strings become the number they spell.
      'number casts a decimal string to float' => ['1.5', '{"type":"number"}', 1.5],
      'plain integer casts a numeric string' => ['3', '{"type":"integer"}', 3],
      // A list is refined item by item with the `items` sub-schema.
      'list items are refined individually' => [
        ['1', '2', '3'],
        '{"type":"array","items":{"type":"integer"}}',
        [1, 2, 3],
      ],
      'a list nested in an object is refined' => [
        ['ids' => ['1', '2'], 'label' => 'x'],
        '{"type":"object","properties":{"ids":{"type":"array","items":{"type":"integer"}},"label":{"type":"string"}}}',
        ['ids' => [1, 2], 'label' => 'x'],
      ],
    ];
  }

  /**
   * Tests the type refinements.
   */
  #[DataProvider('dataProviderRecursiveTypeRefinements')]
  public function testRecursiveTypeRefinements($data, string $schema, $expected): void {
    $actual = RecursiveTypeCaster::recursiveTypeRefinements($data, json_decode($schema));
    $this->assertSame($expected, $actual);
  }

  /**
   * SCHE-0020: object writes must preserve stdClass at every nesting level.
   */
  public function testStdClassProperties(): void {
    $data = json_decode('{"count":"3","nested":{"ratio":"1.5","optional":""}}');
    $schema = json_decode('{"type":"object","properties":{"count":{"type":"integer"},"nested":{"type":"object","properties":{"ratio":{"type":"number"},"optional":{"type":"string","minLength":1}}}}}');
    $actual = RecursiveTypeCaster::recursiveTypeRefinements($data, $schema);
    $this->assertSame($data, $actual);
    $this->assertInstanceOf(\stdClass::class, $actual);
    $this->assertInstanceOf(\stdClass::class, $actual->nested);
    $this->assertSame(3, $actual->count);
    $this->assertSame(1.5, $actual->nested->ratio);
    $this->assertObjectNotHasProperty('optional', $actual->nested);
  }

}
