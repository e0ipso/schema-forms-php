<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SchemaForms\Drupal\ButtonNames;
use SchemaForms\Drupal\ElementScaffold;
use SchemaForms\Drupal\EnumElements;
use SchemaForms\Drupal\FieldBuilder;
use SchemaForms\Drupal\FieldContext;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\FormTreeBuilder;
use SchemaForms\Drupal\JsonShape;
use SchemaForms\Drupal\Labels;
use SchemaForms\Drupal\MultivalueCallbacks;
use SchemaForms\Drupal\MultivalueWidgetBuilder;
use SchemaForms\Drupal\PreservedValues;
use SchemaForms\Drupal\RenderChildren;
use SchemaForms\Drupal\RequiredMarker;
use SchemaForms\Drupal\SavedInput;
use SchemaForms\Drupal\SchemaPreparer;
use SchemaForms\Drupal\SchemaTypes;
use SchemaForms\Drupal\SchemaValidationBridge;
use SchemaForms\Drupal\SubmissionPreparer;
use SchemaForms\Drupal\SubmissionValidator;
use SchemaForms\Drupal\SubmittedValue;
use SchemaForms\Drupal\UnsupportedElement;
use SchemaForms\Drupal\UserInputCleaner;
use SchemaForms\Drupal\WidgetButton;
use SchemaForms\Drupal\WidgetButtons;
use SchemaForms\Drupal\WidgetState;
use SchemaForms\Drupal\WidgetStateStorage;
use SchemaForms\Tests\Kernel\Form\GeneratedSchemaForm;
use SchemaForms\Tests\Kernel\Form\SubformSchemaForm;

/**
 * Host forms that embed the generated container or bypass its bridge.
 */
#[CoversClass(FormGeneratorDrupal::class)]
#[CoversClass(SubmissionPreparer::class)]
#[CoversClass(SchemaValidationBridge::class)]
#[CoversClass(SubmissionValidator::class)]
#[CoversClass(SubmittedValue::class)]
#[CoversClass(UserInputCleaner::class)]
#[CoversClass(PreservedValues::class)]
#[CoversClass(RenderChildren::class)]
#[CoversClass(JsonShape::class)]
#[CoversClass(MultivalueCallbacks::class)]
#[CoversClass(WidgetState::class)]
#[CoversClass(WidgetStateStorage::class)]
#[CoversClass(ButtonNames::class)]
#[CoversClass(WidgetButtons::class)]
#[CoversClass(ElementScaffold::class)]
#[CoversClass(EnumElements::class)]
#[CoversClass(FieldBuilder::class)]
#[CoversClass(FieldContext::class)]
#[CoversClass(FormTreeBuilder::class)]
#[CoversClass(Labels::class)]
#[CoversClass(MultivalueWidgetBuilder::class)]
#[CoversClass(RequiredMarker::class)]
#[CoversClass(SavedInput::class)]
#[CoversClass(SchemaPreparer::class)]
#[CoversClass(SchemaTypes::class)]
#[CoversClass(UnsupportedElement::class)]
#[CoversClass(WidgetButton::class)]
#[RunTestsInSeparateProcesses]
final class HostFormSubmissionTest extends SchemaFormsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  private const SCHEMA = '{"type":"object","properties":{"name":{"type":"string","title":"Name","minLength":3},"count":{"type":"integer","title":"Count"}}}';

  /**
   * A plugin-style subform validates the values it will store.
   *
   * Regression: the subform state resolves #parents relative to the subform,
   * so the bridge found no value, wrote that back over the stored values and
   * validated the raw user input instead.
   */
  #[DataProvider('subformProvider')]
  public function testSubformStateValidatesStoredValues(string $name, array $errors): void {
    $form = new SubformSchemaForm(json_decode(self::SCHEMA, flags: JSON_THROW_ON_ERROR));
    $result = $this->browserSubmit($form, ['settings.schema.name' => $name, 'settings.schema.count' => '5']);
    $this->assertSame($errors, array_keys($result->errors));
    $this->assertSame(['name' => $name, 'count' => 5], $result->values['settings']['schema']);
    $seen = $result->formState->get(SubformSchemaForm::VALIDATE_FORM_VALUES);
    $this->assertSame(['name' => $name, 'count' => 5], $seen['settings']['schema']);
  }

  /**
   * Provides valid and invalid subform submissions.
   *
   * @return array
   *   The test cases.
   */
  public static function subformProvider(): array {
    return [
      'valid' => ['Chuck', []],
      'invalid' => ['xy', ['settings][schema][name']],
    ];
  }

  /**
   * A button with its own #validate skips the bridge but gets plain values.
   *
   * Regression: saved NULL placeholder objects written while the form was
   * built reached the submit handlers when no bridge removed them.
   */
  public function testButtonValidateLeavesNoPlaceholders(): void {
    $schema = json_decode('{"type":"object","properties":{
      "items":{"type":"array","items":{"type":["string","null"]}},
      "people":{"type":"array","items":{"type":["object","null"],"properties":{"name":{"type":"string"}}}},
      "grid":{"type":"array","items":{"type":["array","null"],"items":{"type":"string"}}}
    }}', flags: JSON_THROW_ON_ERROR);
    $current = ['items' => ['first', NULL], 'people' => [NULL], 'grid' => [NULL]];
    $form = new GeneratedSchemaForm($schema, currentInput: $current, withSave: TRUE, saveValidate: ['::validateForm']);
    $result = $this->browserSubmit($form);
    $this->assertSame([], $result->errors);
    // The button's own handler ran instead of the form-level bridge.
    $this->assertIsArray($result->formState->get(GeneratedSchemaForm::VALIDATE_FORM_VALUES));
    $objects = [];
    $values = $result->values;
    array_walk_recursive($values, static function ($value) use (&$objects): void {
      if (is_object($value) && !$value instanceof \Stringable) {
        $objects[] = get_debug_type($value);
      }
    });
    $this->assertSame([], $objects);
    $this->assertSame(['first', ''], $result->values['items']);
  }

}
