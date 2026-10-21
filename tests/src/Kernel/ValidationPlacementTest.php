<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Tests\Kernel\Form\BridgeCountingFormState;
use SchemaForms\Tests\Kernel\Form\GeneratedSchemaForm;

/**
 * Schema validation runs wherever the generated container sits in the form.
 */
#[CoversClass(FormGeneratorDrupal::class)]
#[RunTestsInSeparateProcesses]
final class ValidationPlacementTest extends SchemaFormsKernelTestBase {

  protected static $modules = ['system'];

  private const SCHEMA = '{"type":"object","properties":{"name":{"type":"string","title":"Name","minLength":3},"count":{"type":"integer","title":"Count"}}}';

  /**
   * The bridge runs once, before the form's own validateForm().
   *
   * Regression: with the container as the form, Drupal skipped its
   * #element_validate, so nothing was cast or validated (SCHE-0060).
   */
  #[DataProvider('placementProvider')]
  public function testBridgeRunsOnceInEveryPlacement(?string $parent_key, bool $tree, array $value_parents): void {
    $form_state = new BridgeCountingFormState();
    $values = ['name' => 'xy', 'count' => '5'];
    if ($parent_key !== NULL) {
      $values = [$parent_key => $values];
    }
    $submission = $this->submitSchemaForm($this->schema(), $values, [], $parent_key, $tree, $form_state);

    $this->assertSame(1, $form_state->bridgeRuns);
    $error_name = implode('][', [...$value_parents, 'name']);
    $this->assertSame([$error_name], array_keys($submission->errors));
    $this->assertStringContainsString('minLength', $submission->errors[$error_name]);
    $count_path = [...$value_parents, 'count'];
    $this->assertSame(5, $form_state->getValue($count_path));
    // The form's own validateForm() ran, after the cast.
    $seen = $form_state->get(GeneratedSchemaForm::VALIDATE_FORM_VALUES);
    $this->assertIsArray($seen);
    $this->assertSame(5, $this->valueAt($seen, $count_path));
  }

  /**
   * Drupal's own form values do not count as additional properties.
   *
   * Regression: validating every form value at the form root would reject
   * form_build_id and form_id under "additionalProperties": false.
   */
  public function testFormKeysAreNotAdditionalProperties(): void {
    $schema = json_decode('{"type":"object","additionalProperties":false,"properties":{"name":{"type":"string","title":"Name"},"count":{"type":"integer","title":"Count"}}}', flags: JSON_THROW_ON_ERROR);
    $submission = $this->submitSchemaForm($schema, ['name' => 'Chuck', 'count' => '5']);

    $this->assertSame([], $submission->errors);
    $this->assertSame(5, $submission->values['count']);
    $this->assertSame('schema_forms_generated_schema_form', $submission->values['form_id']);
    $this->assertArrayHasKey('form_build_id', $submission->values);
  }

  /**
   * Provides the container placements.
   */
  public static function placementProvider(): array {
    return [
      'container is the form' => [NULL, FALSE, []],
      'container under a key in a #tree form' => ['schema', TRUE, ['schema']],
    ];
  }

  /**
   * Decodes the test schema.
   */
  private function schema(): object {
    return json_decode(self::SCHEMA, flags: JSON_THROW_ON_ERROR);
  }

  /**
   * Reads a nested value.
   */
  private function valueAt(array $values, array $parents): mixed {
    foreach ($parents as $key) {
      $values = $values[$key] ?? NULL;
    }
    return $values;
  }

  /**
   * SCHE-0013: checkbox maps become typed lists before schema validation.
   */
  #[DataProvider('checkboxProvider')]
  public function testCheckboxLists(string $type, array $enum, array $submitted, array $expected): void {
    $schema = (object) ['type' => 'object', 'properties' => (object) ['choices' => (object) ['type' => 'array', 'items' => (object) ['type' => $type, 'enum' => $enum]]]];
    $result = $this->submitSchemaForm($schema, ['schema' => ['choices' => $submitted]], [], 'schema');
    $this->assertSame([], $result->errors);
    $this->assertSame($expected, $result->values['schema']['choices']);
    $this->assertTrue(array_is_list($result->values['schema']['choices']));
    $seen = $result->formState->get(GeneratedSchemaForm::VALIDATE_FORM_VALUES);
    $this->assertSame($expected, $seen['schema']['choices']);
  }

  public static function checkboxProvider(): array {
    return [
      'reversed string selections' => ['string', ['a', 'b', 'c'], ['c' => 'c', 'a' => 'a'], ['a', 'c']],
      'none checked' => ['string', ['a', 'b'], [], []],
      'checked integer zero' => ['integer', [0, 12, 5], [5 => '5', 0 => '0'], [0, 5]],
      'unchecked integer zero' => ['integer', [0, 12, 5], [12 => '12'], [12]],
      'numeric strings keep schema type' => ['string', ['0', '12', '5'], [5 => '5', 0 => '0'], ['0', '5']],
    ];
  }

  /**
   * SCHE-0014: nested required lists and nullable conversion are independent.
   */
  public function testNestedBlankRules(): void {
    $schema = json_decode('{"type":"object","required":["profile"],"properties":{"profile":{"type":"object","required":["needed"],"properties":{"needed":{"type":"string","minLength":1},"optional":{"type":"string","minLength":1},"plain":{"type":"string"},"nullable":{"type":["integer","null"]}}}}}');
    $result = $this->submitSchemaForm($schema, ['schema' => ['profile' => ['needed' => '', 'optional' => '', 'plain' => '', 'nullable' => '']]], [], 'schema');
    $this->assertSame(['schema][profile][needed'], array_keys($result->errors));
    $this->assertSame(['needed' => '', 'plain' => '', 'nullable' => NULL], $result->values['schema']['profile']);
  }

  /**
   * SCHE-0015: a non-tree host preserves nested objects and multivalue paths.
   */
  public function testNestedMultivalueInNonTreeHost(): void {
    $schema = json_decode('{"type":"object","properties":{"profile":{"type":"object","properties":{"age":{"type":"integer"}}},"scores":{"type":"array","items":{"type":"integer"}}}}');
    $values = ['profile' => ['age' => '42'], 'scores' => [0 => ['removable_element' => '7'], 1 => ['removable_element' => '9']]];
    $result = $this->submitSchemaForm($schema, ['schema' => $values], [], 'schema', current_input: ['scores' => [1, 2]]);
    $this->assertFalse($result->form['#tree']);
    $this->assertTrue($result->form['schema']['#tree']);
    $this->assertSame([], $result->errors);
    $this->assertSame(['profile' => ['age' => 42], 'scores' => [7, 9]], $result->values['schema']);
    foreach ([0, 1] as $delta) {
      $this->assertSame(['schema', 'scores', $delta, 'removable_element'], $result->form['schema']['scores'][$delta]['removable_element']['#parents']);
    }
  }

  /**
   * SCHE-0017: non-input constants and saved values retain exact JSON types.
   */
  #[DataProvider('fixedValueProvider')]
  public function testFixedValues(string $type, mixed $value, bool $constant): void {
    $property = (object) ['type' => $type];
    if ($constant) {
      $property->const = $value;
    }
    $schema = (object) ['type' => 'object', 'required' => ['fixed'], 'properties' => (object) ['fixed' => $property]];
    $result = $this->submitSchemaForm($schema, ['schema' => ['fixed' => 'tampered']], [], 'schema', current_input: $constant ? [] : ['fixed' => $value]);
    $this->assertSame([], $result->errors);
    $this->assertArrayHasKey('fixed', $result->values['schema']);
    $this->assertSame($value, $result->values['schema']['fixed']);
    $this->assertEmpty($result->form['schema']['fixed']['#required']);
  }

  public static function fixedValueProvider(): array {
    return [
      'const null' => ['null', NULL, TRUE],
      'const false' => ['boolean', FALSE, TRUE],
      'const zero' => ['integer', 0, TRUE],
      'const text' => ['string', 'fixed', TRUE],
      'unsupported saved null' => ['null', NULL, FALSE],
    ];
  }

  public function testUnsupportedMissingValueStillErrors(): void {
    $schema = json_decode('{"type":"object","required":["fixed"],"properties":{"fixed":{"type":"null"}}}');
    $result = $this->submitSchemaForm($schema, ['schema' => []], [], 'schema');
    $this->assertNotEmpty($result->errors);
    $this->assertStringContainsString('required', implode(' ', $result->errors));
    $this->assertArrayNotHasKey('fixed', ($result->values['schema'] ?? []));
    $this->assertEmpty($result->form['schema']['fixed']['#required']);
  }

}
