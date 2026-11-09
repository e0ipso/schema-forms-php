<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal\SchemaForms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\FormOptions;
use SchemaForms\Tests\Fixture\TranslationContainer;
use Shaper\Util\Context;

/**
 * The generator does no repeated work, and cached forms hold no copies.
 */
#[CoversClass(FormGeneratorDrupal::class)]
final class FormGeneratorDrupalCostTest extends TestCase {

  use TranslationContainer;

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

}
