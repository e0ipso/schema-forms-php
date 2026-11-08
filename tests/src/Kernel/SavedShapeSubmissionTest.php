<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\SubmissionValidator;
use SchemaForms\Tests\Kernel\Form\GeneratedSchemaForm;

/**
 * Saved values whose shape is unusual survive a Save.
 */
#[CoversClass(FormGeneratorDrupal::class)]
#[CoversClass(SubmissionValidator::class)]
#[RunTestsInSeparateProcesses]
final class SavedShapeSubmissionTest extends SchemaFormsKernelTestBase {

  protected static $modules = ['system'];

  /**
   * A list value where an object is expected is still reported.
   */
  public function testListForAnObjectIsReported(): void {
    $schema = json_decode('{"type":"object","properties":{"fixed":{"type":"object"}}}', flags: JSON_THROW_ON_ERROR);
    $form = new GeneratedSchemaForm($schema, currentInput: ['fixed' => ['a', 'b']], withSave: TRUE);

    $result = $this->browserSubmit($form);

    $this->assertSame(['fixed'], array_keys($result->errors));
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
