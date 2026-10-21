<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use Drupal\Core\Form\FormState;
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
      'fractional selections in schema order' => ['number', [0.5, 1.5, 1.75], ['1.75' => '1.75', '0.5' => '0.5'], [0.5, 1.75]],
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
    $this->assertSame(get_debug_type($value), get_debug_type($result->values['schema']['fixed']));
    $this->assertEquals($value, $result->values['schema']['fixed']);
    $this->assertEmpty($result->form['schema']['fixed']['#required']);
  }

  public static function fixedValueProvider(): array {
    return [
      'const null' => ['null', NULL, TRUE],
      'const false' => ['boolean', FALSE, TRUE],
      'const zero' => ['integer', 0, TRUE],
      'const empty text' => ['string', '', TRUE],
      'const string zero' => ['string', '0', TRUE],
      'const array' => ['array', ['a'], TRUE],
      'const object' => ['object', (object) ['k' => 1], TRUE],
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


  /**
   * SCHE-0026: removed positions do not shift error paths onto another item.
   */
  #[DataProvider('removedDeltaProvider')]
  public function testErrorsAfterRemoval(int $removed, int $invalid): void {
    $schema = json_decode('{"type":"object","properties":{"items":{"type":"array","items":{"type":"string","minLength":3}}}}');
    $values = ['schema' => ['items' => [
      ['removable_element' => 'first'],
      ['removable_element' => 'middle'],
      ['removable_element' => 'last'],
    ]]];
    $saved = ['items' => ['first', 'middle', 'last']];
    $initial = $this->submitSchemaForm($schema, $values, [], 'schema', current_input: $saved);
    $this->assertSame([], $initial->errors);
    $state = (new FormState())->setStorage($initial->formState->getStorage());
    $state->setTriggeringElement($initial->form['schema']['items'][$removed]['remove_one']);
    $removed_result = $this->submitSchemaForm($schema, $values, [], 'schema', form_state: $state, current_input: $saved);
    $this->assertSame([], $removed_result->errors);
    $indices = FormGeneratorDrupal::getPropFormState(['items'], 'items', $state)['items_indices'];
    $this->assertSame(array_values(array_diff([0, 1, 2], [$removed])), array_values($indices));
    unset($values['schema']['items'][$removed]);
    $values['schema']['items'][$invalid]['removable_element'] = 'x';
    $state = (new FormState())->setStorage($state->getStorage());
    $result = $this->submitSchemaForm($schema, $values, [], 'schema', form_state: $state, current_input: $saved);
    $this->assertSame(["schema][items][$invalid][removable_element"], array_keys($result->errors));
    $this->assertSame(['schema', 'items', $invalid, 'removable_element'], $result->form['schema']['items'][$invalid]['removable_element']['#parents']);
    $this->assertArrayNotHasKey($removed, $result->form['schema']['items']);
    $this->assertTrue(array_is_list($result->values['schema']['items']));
  }

  public static function removedDeltaProvider(): array {
    return ['first' => [0, 2], 'middle' => [1, 2], 'last' => [2, 1]];
  }

  /**
   * SCHE-0032: Append retains validation at the final nested value path.
   */
  public function testAppendValidatesExistingItems(): void {
    $schema = json_decode('{"type":"object","properties":{"nested":{"type":"object","properties":{"items":{"type":"array","items":{"type":"string","minLength":3}}}}}}');
    $saved = ['nested' => ['items' => ['valid']]];
    $values = ['schema' => ['nested' => ['items' => [['removable_element' => 'valid']]]]];
    $initial = $this->submitSchemaForm($schema, $values, [], 'schema', current_input: $saved);
    $button = $initial->form['schema']['nested']['items']['add_more'];
    $this->assertSame([['schema', 'nested', 'items']], $button['#limit_validation_errors']);
    $this->assertFalse($initial->form['#tree']);
    $state = (new FormState())->setStorage($initial->formState->getStorage());
    $state->setTriggeringElement($button);
    $values['schema']['nested']['items'][0]['removable_element'] = 'x';
    $result = $this->submitSchemaForm($schema, $values, [], 'schema', form_state: $state, current_input: $saved);
    $this->assertSame(['schema][nested][items][0][removable_element'], array_keys($result->errors));
  }

  /**
   * SCHE-0027: presence requirements accept false and empty enum lists.
   */
  #[DataProvider('requiredEmptyProvider')]
  public function testRequiredEmptyValues(string $type, mixed $expected, int $minimum): void {
    $property = (object) ['type' => $type, 'title' => 'Required choice'];
    if ($type === 'array') {
      $property->items = (object) ['type' => 'string', 'enum' => ['a', 'b']];
      $property->minItems = $minimum;
    }
    $schema = (object) ['type' => 'object', 'required' => ['choice'], 'properties' => (object) ['choice' => $property, 'text' => (object) ['type' => 'string']]];
    $result = $this->submitSchemaForm($schema, ['schema' => ['choice' => $expected]], [], 'schema');
    $this->assertSame($expected, $result->values['schema']['choice']);
    if ($minimum) {
      $this->assertSame(['schema][choice'], array_keys($result->errors));
      $this->assertMatchesRegularExpression('/(minimum|at least|fewer than|minItems)/i', implode(' ', $result->errors));
      return;
    }
    $this->assertSame([], $result->errors);
    $form = $result->form;
    $html = (string) $this->container->get('renderer')->renderInIsolation($form);
    $this->assertStringContainsString('form-required', $html);
    $this->assertStringContainsString('Required choice', $html);
  }

  public static function requiredEmptyProvider(): array {
    return ['false boolean' => ['boolean', FALSE, 0], 'empty array' => ['array', [], 0], 'minimum still enforced' => ['array', [], 1]];
  }

  public function testRequiredTextfieldStillRejectsEmpty(): void {
    $schema = json_decode('{"type":"object","required":["text"],"properties":{"text":{"type":"string"}}}');
    $result = $this->submitSchemaForm($schema, ['schema' => ['text' => '']], [], 'schema');
    $this->assertTrue($result->form['schema']['text']['#required']);
    $this->assertSame(['schema][text'], array_keys($result->errors));
  }

  public function testFractionalRadioSubmission(): void {
    $schema = json_decode('{"type":"object","properties":{"choice":{"type":"number","enum":[0.5,1.5,1.75]}}}');
    $result = $this->submitSchemaForm($schema, ['schema' => ['choice' => '1.75']], [], 'schema');
    $this->assertSame([], $result->errors);
    $this->assertSame(1.75, $result->values['schema']['choice']);
  }


  /**
   * SCHE-0048: unsupported references become root or container form errors.
   */
  #[DataProvider('rejectedReferenceProvider')]
  public function testRejectedReferenceFormError(string $scheme, ?string $parent): void {
    $file = tempnam(sys_get_temp_dir(), 'schema-kernel-ref-');
    file_put_contents($file, '{"type":"string"}');
    try {
      $uri = $scheme === 'file' ? 'file://' . $file : $scheme . '://127.0.0.1:9/sentinel.json';
      $schema = (object) ['type' => 'object', 'properties' => (object) ['field' => (object) ['type' => 'string', '$ref' => $uri]]];
      $result = $this->submitSchemaForm($schema, $parent === NULL ? [] : [$parent => []], [], $parent);
      $this->assertSame([$parent ?? ''], array_keys($result->errors));
      $this->assertStringContainsString('unsupported reference', implode(' ', $result->errors));
    }
    finally {
      unlink($file);
    }
  }

  public static function rejectedReferenceProvider(): array {
    return [
      'file root' => ['file', NULL], 'file container' => ['file', 'schema'],
      'http root' => ['http', NULL], 'http container' => ['http', 'schema'],
      'https root' => ['https', NULL], 'https container' => ['https', 'schema'],
    ];
  }

  /**
   * SCHE-0037: special segments retain their actual Form API error paths.
   */
  #[DataProvider('specialPropertyProvider')]
  public function testSpecialPropertyErrors(string $name, string $placement): void {
    $leaf = (object) ['type' => 'string', 'minLength' => 3];
    $property = $leaf;
    $input = [$name => 'x'];
    $saved = [];
    $state = new FormState();
    $path = ['schema', $name];
    if ($placement === 'nested') {
      $property = (object) ['type' => 'object', 'properties' => (object) [$name => $leaf]];
      $input = [$name => [$name => 'x']];
      $path[] = $name;
    }
    if ($placement === 'gapped') {
      $property = (object) ['type' => 'array', 'items' => (object) ['type' => 'object', 'properties' => (object) [$name => $leaf]]];
      $input = [$name => [2 => ['removable_element' => [$name => 'x']]]];
      $saved = [$name => [2 => [$name => 'valid']]];
      FormGeneratorDrupal::setPropFormState([$name], $name, $state, ['items_indices' => [2]]);
      $path = [...$path, 2, 'removable_element', $name];
    }
    $schema = (object) ['type' => 'object', 'properties' => (object) [$name => $property]];
    $result = $this->submitSchemaForm($schema, ['schema' => $input], [], 'schema', form_state: $state, current_input: $saved);
    $this->assertSame([implode('][', $path)], array_keys($result->errors));
    $this->assertStringContainsString('minLength', implode(' ', $result->errors));
    $element = $result->form;
    foreach ($path as $segment) {
      $element = $element[$segment];
    }
    $this->assertSame($path, $element['#parents']);
  }

  public static function specialPropertyProvider(): array {
    $cases = [];
    foreach (['a/b', 'a~b', 'a%b', 'a~/b%'] as $name) {
      foreach (['flat', 'nested', 'gapped'] as $placement) {
        $cases[$name . ' ' . $placement] = [$name, $placement];
      }
    }
    return $cases;
  }

  /**
   * SCHE-0038/0066: real option keys round trip to the declared JSON values.
   */
  #[DataProvider('untypedEnumProvider')]
  public function testUntypedEnumSubmission(array $enum, string $key, mixed $expected): void {
    $schema = (object) ['type' => 'object', 'properties' => (object) ['choice' => (object) ['enum' => $enum]]];
    $result = $this->submitSchemaForm($schema, ['schema' => ['choice' => $key]], [], 'schema');
    $this->assertArrayHasKey($key, $result->form['schema']['choice']['#options']);
    $this->assertSame([], $result->errors);
    $this->assertSame($expected, $result->values['schema']['choice']);
    $seen = $result->formState->get(GeneratedSchemaForm::VALIDATE_FORM_VALUES);
    $this->assertSame($expected, $seen['schema']['choice']);
  }

  public static function untypedEnumProvider(): array {
    return [
      'boolean true' => [[FALSE, TRUE], '1', TRUE],
      'boolean false' => [[FALSE, TRUE], '0', FALSE],
      'integer zero' => [[0, 12], '0', 0],
      'integer' => [[0, 12], '12', 12],
      'fractional number' => [[0.5, 1.75], '1.75', 1.75],
      'numeric string' => [['0', '12'], '12', '12'],
      'string' => [['a', 'b'], 'b', 'b'],
      'mixed boolean' => [['a', TRUE, 2.5], '1', TRUE],
      'mixed number' => [['a', TRUE, 2.5], '2.5', 2.5],
      'integer then string collision' => [[1, '1'], '1', '1'],
      'string then integer collision' => [['1', 1], '1', 1],
      'boolean then integer collision' => [[TRUE, 1], '1', 1],
      'integer then boolean collision' => [[1, TRUE], '1', TRUE],
      'false then string collision' => [[FALSE, '0'], '0', '0'],
    ];
  }

  public function testUntypedEnumCheckboxZero(): void {
    $schema = json_decode('{"type":"object","properties":{"choices":{"type":"array","items":{"enum":[false,12,"a"]}}}}');
    $result = $this->submitSchemaForm($schema, ['schema' => ['choices' => [12 => '12', 0 => '0']]], [], 'schema');
    $this->assertSame([], $result->errors);
    $this->assertSame([FALSE, 12], $result->values['schema']['choices']);
  }

}
