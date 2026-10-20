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

}
