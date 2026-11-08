<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Tests\Kernel\Form\GeneratedSchemaForm;

/**
 * Multivalue buttons act on their own widget through the real FormBuilder.
 */
#[CoversClass(FormGeneratorDrupal::class)]
#[RunTestsInSeparateProcesses]
final class WidgetButtonSubmissionTest extends SchemaFormsKernelTestBase {

  protected static $modules = ['system'];

  /**
   * An item appended after the last one was removed is saved empty.
   *
   * Regression: the new item reused the removed item's delta, showed its
   * saved value and saved it again.
   */
  public function testRemovedItemDoesNotComeBack(): void {
    $schema = json_decode('{"type":"object","properties":{"items":{"type":"array","items":{"type":"string"}}}}', flags: JSON_THROW_ON_ERROR);
    $form = new GeneratedSchemaForm($schema, currentInput: ['items' => ['a', 'b']], withSave: TRUE);
    $removed = $this->browserSubmit($form, [], NULL, 'items-1-remove', '⨯');
    $appended = $this->browserSubmit($form, [], NULL, 'items_add_more', 'Append an item', $removed->formState->getStorage());

    $result = $this->browserSubmit($form, storage: $appended->formState->getStorage());

    $this->assertSame([], $result->errors);
    $this->assertSame(['a', ''], $result->values['items']);
  }

}
