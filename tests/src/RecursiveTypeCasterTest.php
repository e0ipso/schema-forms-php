<?php

namespace SchemaForms\Tests;

use PHPUnit\Framework\TestCase;
use SchemaForms\RecursiveTypeCaster;

/**
 * Unit tests for the \SchemaForms\RecursiveTypeCaster class.
 *
 * @package SchemaForms
 *
 * @coversDefaultClass \SchemaForms\RecursiveTypeCaster
 */
class RecursiveTypeCasterTest extends TestCase {

  /**
   * Data provider for testRecursiveTypeRefinements.
   *
   * @return array
   *   The data.
   */
  public static function dataProviderRecursiveTypeRefinements(): array {
    return [
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
    ];
  }

  /**
   * Tests the type refinements.
   *
   * @dataProvider dataProviderRecursiveTypeRefinements
   *
   * @covers ::recursiveTypeRefinements
   */
  public function testRecursiveTypeRefinements($data, string $schema, $expected): void {
    $actual = RecursiveTypeCaster::recursiveTypeRefinements($data, json_decode($schema));
    $this->assertSame($expected, $actual);
  }

}
