<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SchemaForms\Drupal\EnumElements;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\MultivalueCallbacks;
use SchemaForms\Drupal\SchemaValidationBridge;
use SchemaForms\Tests\Kernel\Form\GeneratedSchemaForm;
use SchemaForms\Tests\Kernel\Form\LegacyCallbackSchemaForm;

/**
 * A form cached with the callbacks release 3.1.1 named still submits.
 */
#[CoversClass(FormGeneratorDrupal::class)]
#[CoversClass(EnumElements::class)]
#[CoversClass(MultivalueCallbacks::class)]
#[CoversClass(SchemaValidationBridge::class)]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
final class LegacyCallbackSubmissionTest extends SchemaFormsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * The schema: a list widget, checkboxes and a number.
   */
  private const SCHEMA = '{"type":"object","properties":{
    "tags":{"type":"array","items":{"type":"string","minLength":2}},
    "picks":{"type":"array","items":{"enum":["a","b"]}},
    "count":{"type":"integer"}
  }}';

  /**
   * Appending, casting and validating run through the deprecated callbacks.
   */
  public function testContainerUnderKey(): void {
    $this->expectNotices([
      'validateWithSchema',
      'multiValueProcess',
      'multiValueAfterBuild',
      'addMoreSubmit',
      'validateCheckboxes',
    ]);
    $form = new LegacyCallbackSchemaForm(new GeneratedSchemaForm(json_decode(self::SCHEMA), parentKey: 'schema', withSave: TRUE));

    $appended = $this->browserSubmit($form, [], 'schema', 'tags_add_more', 'Append an item');
    $this->assertTrue($appended->formState->isRebuilding());
    $storage = $appended->formState->getStorage();
    $edits = ['tags.0.removable_element' => 'x', 'count' => '3', 'picks' => ['b' => 'b']];

    $invalid = $this->browserSubmit($form, $edits, 'schema', storage: $storage);
    $valid = $this->browserSubmit($form, ['tags.0.removable_element' => 'xy'] + $edits, 'schema', storage: $storage);

    $this->assertSame(['schema][tags][0][removable_element'], array_keys($invalid->errors));
    $this->assertSame([], $valid->errors);
    $this->assertSame(['tags' => ['xy'], 'picks' => ['b'], 'count' => 3], $valid->values['schema']);
  }

  /**
   * The deprecated form-level handler validates a container that is the form.
   */
  public function testContainerAsForm(): void {
    $this->expectNotices(['validateFormWithSchema']);
    $form = new LegacyCallbackSchemaForm(new GeneratedSchemaForm(json_decode(self::SCHEMA), withSave: TRUE));

    $result = $this->browserSubmit($form, ['count' => 'many']);

    $this->assertSame(['count'], array_keys($result->errors));
  }

  /**
   * Expects the notice of each deprecated generator method.
   *
   * @param list<string> $methods
   *   The method names.
   */
  private function expectNotices(array $methods): void {
    $replacements = [
      'validateWithSchema' => 'SchemaValidationBridge::elementValidate()',
      'validateFormWithSchema' => 'SchemaValidationBridge::formValidate()',
      'multiValueProcess' => 'MultivalueCallbacks::process()',
      'multiValueAfterBuild' => 'MultivalueCallbacks::afterBuild()',
      'addMoreSubmit' => 'MultivalueCallbacks::appendSubmit()',
      'validateCheckboxes' => 'EnumElements::validateCheckboxes()',
    ];
    foreach ($methods as $method) {
      $this->expectUserDeprecationMessage(sprintf(
        'FormGeneratorDrupal::%s() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. Generated forms name %s instead. See https://github.com/e0ipso/schema-forms-php#upgrading',
        $method,
        $replacements[$method]
      ));
    }
  }

}
