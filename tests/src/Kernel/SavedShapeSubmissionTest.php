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
use SchemaForms\Drupal\RenderChildren;
use SchemaForms\Drupal\RequiredMarker;
use SchemaForms\Drupal\SavedInput;
use SchemaForms\Drupal\SchemaPreparer;
use SchemaForms\Drupal\SchemaTypes;
use SchemaForms\Drupal\SubmissionValidator;
use SchemaForms\Drupal\UnsupportedElement;
use SchemaForms\Drupal\WidgetButton;
use SchemaForms\Drupal\WidgetState;
use SchemaForms\Drupal\WidgetStateStorage;
use SchemaForms\Tests\Kernel\Form\GeneratedSchemaForm;

/**
 * Saved values whose shape is unusual survive a Save.
 */
#[CoversClass(FormGeneratorDrupal::class)]
#[CoversClass(SubmissionValidator::class)]
#[CoversClass(JsonShape::class)]
#[CoversClass(FieldBuilder::class)]
#[CoversClass(SchemaTypes::class)]
#[CoversClass(EnumElements::class)]
#[CoversClass(ButtonNames::class)]
#[CoversClass(ElementScaffold::class)]
#[CoversClass(FieldContext::class)]
#[CoversClass(FormTreeBuilder::class)]
#[CoversClass(Labels::class)]
#[CoversClass(MultivalueCallbacks::class)]
#[CoversClass(MultivalueWidgetBuilder::class)]
#[CoversClass(RenderChildren::class)]
#[CoversClass(RequiredMarker::class)]
#[CoversClass(SavedInput::class)]
#[CoversClass(SchemaPreparer::class)]
#[CoversClass(UnsupportedElement::class)]
#[CoversClass(WidgetButton::class)]
#[CoversClass(WidgetState::class)]
#[CoversClass(WidgetStateStorage::class)]
#[RunTestsInSeparateProcesses]
final class SavedShapeSubmissionTest extends SchemaFormsKernelTestBase {

  protected static $modules = ['system'];

  /**
   * Objects whose property names are "0" to "n-1" validate as objects.
   *
   * Regression: PHP holds such an object as a list, which JSON encodes as an
   * array, so validation reported that an object was required.
   */
  #[DataProvider('integerNameProvider')]
  public function testIntegerPropertyNames(?string $parent): void {
    $schema = json_decode('{"type":"object","properties":{
      "0":{"type":"string"},
      "1":{"type":"object","properties":{"0":{"type":"string"},"1":{"type":"integer"}}}
    }}', flags: JSON_THROW_ON_ERROR);
    $current = ['0' => 'a', '1' => ['0' => 'b', '1' => 2]];
    $form = new GeneratedSchemaForm($schema, parentKey: $parent, tree: TRUE, currentInput: $current, withSave: TRUE);

    $result = $this->browserSubmit($form, [], $parent);

    $this->assertSame([], $result->errors);
    $values = $parent === NULL ? $result->values : $result->values[$parent];
    $this->assertSame('a', $values[0]);
    $this->assertSame(['b', 2], $values[1]);
  }

  public static function integerNameProvider(): array {
    return ['form root' => [NULL], 'under a key' => ['schema']];
  }

  /**
   * A saved list where an object is expected is held as an object.
   *
   * PHP cannot tell the list ["a", "b"] from an object whose properties are
   * "0" and "1", so the schema decides.
   */
  public function testListForAnObjectIsAnObject(): void {
    $schema = json_decode('{"type":"object","properties":{"fixed":{"type":"object","additionalProperties":{"type":"string"}},"list":{"type":["array","object"]}}}', flags: JSON_THROW_ON_ERROR);
    $form = new GeneratedSchemaForm($schema, currentInput: ['fixed' => ['a', 'b'], 'list' => ['c']], withSave: TRUE);

    $result = $this->browserSubmit($form);

    $this->assertSame([], $result->errors);
    $this->assertSame(['a', 'b'], $result->values['fixed']);
    $this->assertSame(['c'], $result->values['list']);
  }

  /**
   * Saved values a control cannot show are kept or reported on Save.
   */
  #[DataProvider('savedScalarProvider')]
  public function testSavedValueOutsideTheControl(string $property, mixed $saved, array $errors): void {
    $schema = json_decode('{"type":"object","properties":{"p":' . $property . '}}', flags: JSON_THROW_ON_ERROR);
    $form = new GeneratedSchemaForm($schema, currentInput: ['p' => $saved], withSave: TRUE);

    $result = $this->browserSubmit($form);

    $this->assertSame($errors, array_keys($result->errors));
    $this->assertSame($saved, $result->values['p']);
  }

  public static function savedScalarProvider(): array {
    return [
      'string in a union' => ['{"type":["array","string"],"items":{"type":"string"}}', 'abc', []],
      'string for an array' => ['{"type":"array","items":{"type":"string"}}', 'abc', ['p']],
      'integer for an object' => ['{"type":"object","properties":{"a":{"type":"string"}}}', 5, ['p']],
      'array enum member' => ['{"type":"array","enum":[["a"],["b"]],"items":{"type":"string"}}', ['a'], []],
    ];
  }

}
