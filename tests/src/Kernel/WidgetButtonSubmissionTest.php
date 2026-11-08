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
   * Append buttons of widgets whose legacy names collide stay apart.
   *
   * Regression: "tag-list" and "tag_list" both named their button
   * "tag_list_add_more", so FormBuilder matched the first one for both.
   */
  public function testCollidingAppendNames(): void {
    $schema = json_decode('{"type":"object","properties":{
      "tag-list":{"type":"array","items":{"type":"string"}},
      "tag_list":{"type":"array","items":{"type":"string"}},
      "plain":{"type":"array","items":{"type":"string"}}
    }}', flags: JSON_THROW_ON_ERROR);
    $form = new GeneratedSchemaForm($schema, withSave: TRUE);
    $built = $this->browserSubmit($form)->form;
    $names = array_map(static fn (string $key): string => $built[$key]['add_more']['#name'], ['tag-list', 'tag_list', 'plain']);
    $this->assertCount(3, array_unique($names));
    $this->assertSame('plain_add_more', $names[2]);

    $result = $this->browserSubmit($form, [], NULL, $names[1], 'Append an item');

    $this->assertSame([0], FormGeneratorDrupal::getPropFormState(['tag_list'], 'tag_list', $result->formState)['items_indices']);
    $this->assertSame([], FormGeneratorDrupal::getPropFormState(['tag-list'], 'tag-list', $result->formState)['items_indices']);
  }

  /**
   * Remove buttons of widgets whose legacy names collide stay apart.
   *
   * Regression: the widgets at ["a-b"] and ["a", "b"] both named the remove
   * button of their first item "a-b-0-remove".
   */
  public function testCollidingRemoveNames(): void {
    $schema = json_decode('{"type":"object","properties":{
      "a-b":{"type":"array","items":{"type":"string"}},
      "a":{"type":"object","properties":{"b":{"type":"array","items":{"type":"string"}}}}
    }}', flags: JSON_THROW_ON_ERROR);
    $current = ['a-b' => ['x', 'y'], 'a' => ['b' => ['p', 'q']]];
    $form = new GeneratedSchemaForm($schema, currentInput: $current, withSave: TRUE);
    $built = $this->browserSubmit($form)->form;
    // The widget whose segments hold no hyphen keeps its name.
    $this->assertSame('a-b-0-remove', $built['a']['b'][0]['remove_one']['#name']);
    $this->assertNotSame('a-b-0-remove', $built['a-b'][0]['remove_one']['#name']);

    $result = $this->browserSubmit($form, [], NULL, 'a-b-0-remove', '⨯');

    $this->assertSame([1], array_values(FormGeneratorDrupal::getPropFormState(['a', 'b'], 'b', $result->formState)['items_indices']));
    $this->assertSame([0, 1], FormGeneratorDrupal::getPropFormState(['a-b'], 'a-b', $result->formState)['items_indices']);
  }

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
