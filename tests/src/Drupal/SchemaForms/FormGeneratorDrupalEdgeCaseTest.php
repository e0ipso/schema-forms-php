<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal\SchemaForms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\FormGeneratorDrupal;
use Shaper\Util\Context;

/**
 * Schemas and saved values that used to stop the form from building.
 */
#[CoversClass(FormGeneratorDrupal::class)]
final class FormGeneratorDrupalEdgeCaseTest extends TestCase {

  /**
   * Enums with array or object members render the "cannot be edited" item.
   */
  #[DataProvider('nonScalarEnumProvider')]
  public function testNonScalarEnumIsUnsupported(string $property, bool $saved): void {
    $schema = json_decode('{"type":"object","properties":{"p":' . $property . '}}');
    $current = $saved ? ['current_input' => ['p' => [['a']]]] : [];
    $field = (new FormGeneratorDrupal())->transform($schema, new Context($current))['p'];
    $this->assertSame('item', $field['#type']);
    $this->assertFalse($field['#input']);
    $this->assertTrue($field['#disabled']);
    $this->assertArrayNotHasKey('#options', $field);
    $this->assertStringContainsString('cannot be edited', $field['#markup']->getUntranslatedString());
    if ($saved) {
      $this->assertSame([['a']], $field['#schema_fixed_value']);
      return;
    }
    $this->assertArrayNotHasKey('#schema_fixed_value', $field);
  }

  public static function nonScalarEnumProvider(): array {
    $cases = [];
    foreach ([
      'array members' => '{"type":"array","enum":[["a"],["b"]],"items":{"type":"string"}}',
      'untyped object members' => '{"enum":[{"k":1}]}',
      'mixed members' => '{"type":"string","enum":["a",["b"]]}',
      'array item members' => '{"type":"array","items":{"enum":[["a"],"b"]}}',
    ] as $name => $property) {
      $cases[$name] = [$property, FALSE];
      $cases[$name . ' with a saved value'] = [$property, TRUE];
    }
    return $cases;
  }

  /**
   * A saved scalar on a compound type keeps its value instead of crashing.
   *
   * When the type list allows the scalar, its control renders it. Otherwise
   * the saved value is kept as it is, so validation can report it.
   */
  #[DataProvider('savedScalarProvider')]
  public function testSavedScalarOnCompoundType(mixed $type, mixed $saved, string $element): void {
    $property = (object) [
      'type' => $type,
      'items' => (object) ['type' => 'string'],
      'properties' => (object) ['name' => (object) ['type' => 'string']],
    ];
    $schema = (object) ['type' => 'object', 'properties' => (object) ['p' => $property]];
    $field = (new FormGeneratorDrupal())->transform($schema, new Context(['current_input' => ['p' => $saved]]))['p'];
    $this->assertSame($element, $field['#type']);
    if ($element === 'item') {
      $this->assertSame($saved, $field['#schema_fixed_value']);
      $this->assertFalse($field['#input']);
      $this->assertStringContainsString('cannot be edited', $field['#markup']->getUntranslatedString());
      return;
    }
    $this->assertSame($saved, $field['#default_value']);
    $this->assertArrayNotHasKey('#schema_fixed_value', $field);
  }

  public static function savedScalarProvider(): array {
    return [
      'string in an array or string union' => [['array', 'string'], 'abc', 'textfield'],
      'integer in an object or integer union' => [['object', 'integer'], 5, 'number'],
      'number for an integer in a union' => [['array', 'null', 'number'], 5, 'number'],
      'boolean in a union' => [['object', 'boolean'], TRUE, 'checkbox'],
      'string for an array' => ['array', 'abc', 'item'],
      'integer for an object' => ['object', 5, 'item'],
      'float for an integer union' => [['array', 'integer'], 1.5, 'item'],
    ];
  }

  /**
   * A saved scalar where a list item expects a list keeps the scalar.
   */
  public function testSavedScalarListItem(): void {
    $schema = json_decode('{"type":"object","properties":{"grid":{"type":"array","items":{"type":"array","items":{"type":"string"}}}}}');
    $form = (new FormGeneratorDrupal())->transform($schema, new Context(['current_input' => ['grid' => ['x', ['y']]]]));
    $this->assertSame('item', $form['grid'][0]['removable_element']['#type']);
    $this->assertSame('x', $form['grid'][0]['removable_element']['#schema_fixed_value']);
    $this->assertSame('details', $form['grid'][1]['removable_element']['#type']);
  }

  /**
   * A sub-property named like a UI hint does not read its parent's hint.
   */
  public function testUiPrefixedPropertyNames(): void {
    $schema = json_decode('{"type":"object","properties":{"p":{"type":"object","properties":{"ui:title":{"type":"string"},"ui:help":{"type":"string"},"name":{"type":"string"}}}}}');
    $hints = ['p' => ['ui:title' => 'Parent', 'ui:help' => 'Parent help', 'name' => 'not a hint array']];
    $form = (new FormGeneratorDrupal())->transform($schema, new Context(['ui_hints' => $hints]));
    $this->assertSame('Parent', (string) $form['p']['#title']);
    $this->assertSame('Ui:title', (string) $form['p']['ui:title']['#title']);
    $this->assertArrayNotHasKey('#description', $form['p']['ui:title']);
    $this->assertSame('textfield', $form['p']['ui:help']['#type']);
    $this->assertSame('Name', (string) $form['p']['name']['#title']);
  }

}
