<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal\SchemaForms;

use Drupal\Core\Form\FormState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\FormOptions;
use SchemaForms\Tests\Fixture\TranslationContainer;
use Shaper\Util\Context;

/**
 * The generator's work stays bounded on repeated and large schemas.
 */
#[CoversClass(FormGeneratorDrupal::class)]
final class FormGeneratorDrupalCostTest extends TestCase {

  use TranslationContainer;

  /**
   * The number of root properties in the large schema.
   */
  private const WIDTH = 200;

  /**
   * The nesting depth of the deep branches in the large schema.
   */
  private const DEPTH = 8;

  /**
   * The largest serialized form state storage the large schema may need.
   *
   * About 3% above the 20,400 bytes it took when the bound was set.
   */
  private const STORAGE_BYTES = 21000;

  /**
   * The largest serialized form the large schema may produce.
   *
   * About 2% above the 530,765 bytes it took when the bound was set.
   */
  private const FORM_BYTES = 541000;

  /**
   * A schema the generator accepted is not validated again.
   */
  public function testRepeatedSchemaIsValidatedOnce(): void {
    $generator = new FormGeneratorDrupal();
    $json = '{"type":"object","properties":{"a":{"type":"string"}}}';
    $first = $generator->transform(json_decode($json));
    $validator = $generator->getInputValidator();
    $this->assertFalse($validator->isValid('not a schema'));
    $errors = $validator->getErrors();
    $this->assertNotSame([], $errors);

    // Validation starts by clearing the errors, so they survive only when
    // the second transform skips it.
    $second = $generator->transform(json_decode($json));

    $this->assertSame($errors, $validator->getErrors());
    $this->assertSame($first['#json_schema'], $second['#json_schema']);

    $generator->transform(json_decode('{"type":"object","properties":{"b":{"type":"string"}}}'));

    $this->assertSame([], $validator->getErrors());
  }

  /**
   * Only transform() marks a schema as accepted.
   */
  public function testDirectBuildDoesNotSkipValidation(): void {
    $generator = new FormGeneratorDrupal();
    $schema = json_decode('{"type":"object","properties":{"a":{"type":"string"}}}');
    $generator->doTransform($schema);
    $validator = $generator->getInputValidator();
    $validator->isValid('not a schema');

    $generator->transform($schema);

    $this->assertSame([], $validator->getErrors());
  }

  /**
   * A schema that cannot be fingerprinted is validated every time.
   */
  public function testUnserializableSchemaIsValidatedEachTime(): void {
    $generator = new FormGeneratorDrupal();
    $schema = (object) ['type' => 'object', 'x-handler' => static fn (): bool => TRUE];
    $this->assertTrue($generator->conformsToExpectedInputShape($schema));
    $validator = $generator->getInputValidator();
    $validator->isValid('not a schema');

    $this->assertTrue($generator->conformsToExpectedInputShape($schema));

    $this->assertSame([], $validator->getErrors());
  }

  /**
   * A rejected schema is rejected every time.
   */
  public function testRejectedSchemaIsNotRemembered(): void {
    $generator = new FormGeneratorDrupal();
    $schema = json_decode('{"type":"array"}');
    foreach ([1, 2] as $attempt) {
      try {
        $generator->transform($schema);
        $this->fail('Attempt ' . $attempt . ' accepted an array root.');
      }
      catch (\TypeError $e) {
        $this->assertStringContainsString('received invalid input data', $e->getMessage());
      }
    }
  }

  /**
   * The remembered schemas stay out of the serialized generator.
   */
  public function testMemoryIsNotSerialized(): void {
    $generator = new FormGeneratorDrupal();
    $generator->transform(json_decode('{"type":"object","properties":{"a":{"type":"string"}}}'));

    $this->assertStringNotContainsString('checkedSchemas', serialize($generator));
  }

  /**
   * A wide and deep schema builds with bounded form state and form size.
   *
   * The test builds through doTransform(): the meta-schema check that
   * transform() adds runs once per schema and generator, and it would take
   * most of the test's time.
   */
  public function testLargeSchemaStaysBounded(): void {
    $form_state = new FormState();
    $saved = ['list_0' => array_fill(0, 3, ['name' => 'x', 'deep' => []])];

    $options = new FormOptions(
      formState: $form_state,
      currentInput: $saved,
    );
    $form = (new FormGeneratorDrupal())->doTransform(self::largeSchema(), new Context(['options' => $options]));

    $this->assertCount(self::WIDTH, array_filter(array_keys($form), static fn (int|string $key): bool => !str_starts_with((string) $key, '#')));
    $deepest = $form['deep_0'];
    foreach (range(1, self::DEPTH) as $level) {
      $deepest = $deepest['level_' . $level];
    }
    $this->assertSame('textfield', $deepest['leaf']['#type']);
    $this->assertSame('details', $deepest['rows']['#type']);
    $this->assertCount(3, array_filter(array_keys($form['list_0']), 'is_int'));
    $this->assertLessThan(self::STORAGE_BYTES, strlen(serialize($form_state->getStorage())));
    $this->assertLessThan(self::FORM_BYTES, strlen(serialize($form)));
  }

  /**
   * Elements that collect no input hold no copy of the saved values.
   *
   * Core reads #default_value only on inputs, and a cached form stores every
   * copy, so a copy on each container would grow the form with the depth.
   */
  public function testOnlyInputsHoldSavedValues(): void {
    $schema = json_decode('{"type":"object","properties":{"outer":{"type":"object","properties":{
      "kind":{"const":"fixed","default":"fixed"},
      "rows":{"type":"array","items":{"type":"object","properties":{"cell":{"type":"string"}}}},
      "empty":{"type":"array","items":{"type":"string"}}
    }}}}', flags: JSON_THROW_ON_ERROR);
    $saved = ['outer' => ['kind' => 'fixed', 'rows' => [['cell' => 'x']], 'empty' => []]];

    $form = (new FormGeneratorDrupal())->transform($schema, new Context(['options' => new FormOptions(currentInput: $saved)]));

    $holders = self::defaultValueHolders($form);
    $this->assertSame(['textfield'], array_values(array_unique($holders)));
    $this->assertSame('x', $form['outer']['rows'][0]['removable_element']['cell']['#default_value']);
  }

  /**
   * Lists the types of the elements that hold a default value.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   *
   * @return list<string>
   *   The types, in form order.
   */
  private static function defaultValueHolders(array $element): array {
    $own = array_key_exists('#default_value', $element) ? [(string) ($element['#type'] ?? '')] : [];
    $children = array_filter(
      $element,
      static fn (mixed $child, int|string $key): bool => is_array($child) && !str_starts_with((string) $key, '#'),
      ARRAY_FILTER_USE_BOTH
    );
    return [...$own, ...array_merge(...array_values(array_map([self::class, 'defaultValueHolders'], $children)))];
  }

  /**
   * Builds a schema with wide, deep and list branches.
   *
   * @return \stdClass
   *   The schema: root properties cycle through a string, an enum, a list of
   *   objects and an object nested self::DEPTH levels deep.
   */
  private static function largeSchema(): \stdClass {
    $row = (object) [
      'type' => 'object',
      'properties' => (object) [
        'name' => (object) ['type' => 'string'],
        'deep' => (object) ['type' => 'array', 'items' => (object) ['type' => 'integer']],
      ],
    ];
    $deep = array_reduce(
      range(self::DEPTH, 1),
      static fn (\stdClass $inner, int $level): \stdClass => (object) [
        'type' => 'object',
        'properties' => (object) [
          'level_' . $level => $inner,
          'note_' . $level => (object) ['type' => 'string'],
        ],
      ],
      (object) [
        'type' => 'object',
        'properties' => (object) [
          'leaf' => (object) ['type' => 'string'],
          'rows' => (object) ['type' => 'array', 'items' => $row],
        ],
      ]
    );
    $kinds = [
      'text' => (object) ['type' => 'string', 'maxLength' => 20],
      'choice' => (object) ['type' => 'string', 'enum' => ['a', 'b', 'c']],
      'list' => (object) ['type' => 'array', 'items' => $row],
      'deep' => $deep,
    ];
    $names = array_map(
      static fn (int $index): string => array_keys($kinds)[$index % count($kinds)] . '_' . intdiv($index, count($kinds)),
      range(0, self::WIDTH - 1)
    );
    $properties = array_combine($names, array_map(
      static fn (string $name): \stdClass => $kinds[strstr($name, '_', TRUE)],
      $names
    ));
    return (object) ['type' => 'object', 'properties' => (object) $properties];
  }

}
