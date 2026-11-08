<?php

namespace SchemaForms\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Exception\UnsupportedReferenceException;
use SchemaForms\RecursiveTypeCaster;
use SchemaForms\SchemaReferenceResolver;

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
      'untyped numeric string unchanged' => ['12', '{}', '12'],
      'untyped boolean text unchanged' => ['false', '{}', 'false'],
      'untyped array unchanged' => [['12', FALSE], '{}', ['12', FALSE]],
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
      // A decimal property name becomes an integer key in a PHP array.
      'required decimal property name keeps its blank' => [
        ['123' => '', 'abc' => '', '456' => ''],
        '{"type":"object","required":["123","abc"],"properties":{"123":{"type":"string","minLength":1},"abc":{"type":"string","minLength":1},"456":{"type":"string","minLength":1}}}',
        ['123' => '', 'abc' => ''],
      ],
      'required decimal property name on stdClass keeps its blank' => [
        (object) ['123' => '', 'abc' => ''],
        '{"type":"object","required":["123"],"properties":{"123":{"type":"string","minLength":1},"abc":{"type":"string","minLength":1}}}',
        (object) ['123' => ''],
      ],
      'a non-list required keyword is ignored' => [
        ['a' => ''],
        '{"type":"object","required":"a","properties":{"a":{"type":"string","minLength":1}}}',
        [],
      ],
      // A value with no string spelling is left for validation to report.
      'string keeps a list' => [['x'], '{"type":"string"}', ['x']],
      'string keeps a map' => [['a' => 'x'], '{"type":"string"}', ['a' => 'x']],
      'string spells a float' => [1.5, '{"type":"string"}', '1.5'],
      'string spells TRUE' => [TRUE, '{"type":"string"}', '1'],
      'string spells NULL' => [NULL, '{"type":"string"}', ''],
      'each optional blank is checked on its own' => [
        ['a' => '', 'b' => '', 'c' => ''],
        '{"type":"object","properties":{"a":{"type":"string","minLength":1},"b":{"type":"string"},"c":{"type":"string","minLength":1}}}',
        ['b' => ''],
      ],
      // Unsafe numeric conversions must retain the input string.
      'non-finite positive exponent' => ['1e999', '{"type":"number"}', '1e999'],
      'non-finite negative exponent' => ['-1e999', '{"type":"number"}', '-1e999'],
      'positive integer overflow' => ['99999999999999999999', '{"type":"integer"}', '99999999999999999999'],
      'negative integer overflow' => ['-99999999999999999999', '{"type":"integer"}', '-99999999999999999999'],
      'ordinary negative decimal' => ['-1.5', '{"type":"number"}', -1.5],
      // Numeric strings become the number they spell.
      'number casts a decimal string to float' => ['1.5', '{"type":"number"}', 1.5],
      'plain integer casts a numeric string' => ['3', '{"type":"integer"}', 3],
      // An exponent without a dot keeps a fraction and casts a whole number.
      'number keeps a negative exponent fraction' => ['1e-3', '{"type":"number"}', 0.001],
      'number casts a whole exponent to integer' => ['1E3', '{"type":"number"}', 1000],
      'number keeps a negative fraction exponent' => ['-2e-1', '{"type":"number"}', -0.2],
      'integer keeps a negative exponent fraction' => ['1e-3', '{"type":"integer"}', 0.001],
      'integer casts a whole exponent' => ['1E3', '{"type":"integer"}', 1000],
      'integer keeps a negative fraction exponent' => ['-2e-1', '{"type":"integer"}', -0.2],
      'number keeps a dotted whole number a float' => ['1.0', '{"type":"number"}', 1.0],
      'number casts a plain integer string' => ['12', '{"type":"number"}', 12],
      'exponent integer overflow' => ['1e20', '{"type":"integer"}', '1e20'],
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
    if (is_object($expected)) {
      $this->assertEquals($expected, $actual);
      return;
    }
    $this->assertSame($expected, $actual);
  }

  /**
   * A supplied factory resolves references the schema makes.
   */
  public function testSuppliedFactory(): void {
    $factory = SchemaReferenceResolver::createFactory(json_decode('{"type":"object"}'));
    $factory->getSchemaStorage()->addSchema('internal://labels/', json_decode('{"definitions":{"label":{"type":"string","minLength":2}}}'));
    $schema = json_decode('{"type":"object","properties":{"field":{"type":"string","allOf":[{"$ref":"internal://labels/#/definitions/label"}]},"count":{"type":"integer"}}}');
    $data = ['field' => '', 'count' => '3'];
    $this->assertSame(['count' => 3], RecursiveTypeCaster::recursiveTypeRefinements($data, $schema, $factory));
    $this->expectException(UnsupportedReferenceException::class);
    RecursiveTypeCaster::recursiveTypeRefinements($data, $schema);
  }

  /**
   * An object where a string is expected stays an object.
   */
  public function testStringKeepsAnObject(): void {
    $value = (object) ['a' => 'x'];
    $actual = RecursiveTypeCaster::recursiveTypeRefinements(['field' => $value], json_decode('{"type":"object","properties":{"field":{"type":"string"}}}'));
    $this->assertSame(['field' => $value], $actual);
  }

  /**
   * Every draft-04 type keeps its boolean refinement result.
   *
   * The type list is matched strictly. Only a non-string type member could
   * match differently, and the validator rejects such a schema.
   */
  #[DataProvider('dataProviderBooleanTypes')]
  public function testBooleanTypeMatching(array $types, mixed $input, mixed $expected): void {
    $schema = (object) ['type' => $types];
    $this->assertSame($expected, RecursiveTypeCaster::recursiveTypeRefinements($input, $schema));
  }

  /**
   * Data provider for testBooleanTypeMatching.
   */
  public static function dataProviderBooleanTypes(): array {
    $cases = [];
    foreach (['array', 'boolean', 'integer', 'null', 'number', 'object', 'string'] as $type) {
      foreach (['0', '1', 0, 1, 'true', ''] as $input) {
        $expected = match (TRUE) {
          $type === 'boolean' && in_array($input, ['0', '1', 0, 1], TRUE) => (bool) $input,
          in_array($type, ['integer', 'number'], TRUE) && is_numeric($input) => (int) $input,
          $type === 'null' && $input === '' => NULL,
          $type === 'string' => (string) $input,
          default => $input,
        };
        $cases[sprintf('%s %s', $type, var_export($input, TRUE))] = [[$type], $input, $expected];
      }
    }
    return $cases;
  }

  /**
   * Object writes must preserve stdClass at every nesting level.
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
