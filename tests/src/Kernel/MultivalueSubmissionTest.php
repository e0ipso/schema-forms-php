<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SchemaForms\Drupal\WidgetItems;
use SchemaForms\Tests\Kernel\Form\GeneratedSchemaForm;

/**
 * Multivalue items keep their values through removals and saves.
 */
#[RunTestsInSeparateProcesses]
final class MultivalueSubmissionTest extends SchemaFormsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Values the browser does not send follow their item after a removal.
   *
   * Regression: constants and saved values of unsupported types were written
   * at the item's form delta, while the list had already been compacted, so
   * the first remaining item lost them and a phantom item appeared.
   */
  public function testFixedValuesFollowItemsAfterRemoval(): void {
    $schema = json_decode('{"type":"object","properties":{"rows":{"type":"array","items":{"type":"object","properties":{
      "kind":{"const":"row"},
      "label":{"type":"string"},
      "tags":{"type":"array"}
    }}}}}', flags: JSON_THROW_ON_ERROR);
    $current = [
      'rows' => [
      ['kind' => 'row', 'label' => 'a', 'tags' => ['t-a']],
      ['kind' => 'row', 'label' => 'b', 'tags' => ['t-b']],
      ['kind' => 'row', 'label' => 'c', 'tags' => ['t-c']],
      ],
    ];
    $form = new GeneratedSchemaForm($schema, currentInput: $current, withSave: TRUE);
    $removed = $this->browserSubmit($form, [], NULL, 'rows-0-remove', '⨯');
    $this->assertTrue($removed->formState->isRebuilding());
    $result = $this->browserSubmit($form, storage: $removed->formState->getStorage());
    $this->assertSame([], $result->errors);
    // Values the browser does not send are added after the submitted ones.
    $this->assertSame([
      ['label' => 'b', 'kind' => 'row', 'tags' => ['t-b']],
      ['label' => 'c', 'kind' => 'row', 'tags' => ['t-c']],
    ], $result->values['rows']);
  }

  /**
   * An optional choice left empty inside an item is omitted, as at the top.
   *
   * Regression: items were not cleaned, so the empty choice reached the type
   * caster and failed its enum.
   */
  public function testEmptyChoiceInsideItem(): void {
    $schema = json_decode('{"type":"object","properties":{
      "choice":{"type":"string","enum":["a","b"]},
      "rows":{"type":"array","items":{"type":"object","properties":{"choice":{"type":"string","enum":["a","b"]},"label":{"type":"string"}}}}
    }}', flags: JSON_THROW_ON_ERROR);
    $form = new GeneratedSchemaForm($schema, currentInput: ['rows' => [['label' => 'x']]], withSave: TRUE);
    $result = $this->browserSubmit($form);
    $this->assertSame([], $result->errors);
    $this->assertArrayNotHasKey('choice', $result->values);
    $this->assertSame([['label' => 'x']], $result->values['rows']);
  }

  /**
   * Saved empty lists stay in place when the form is saved unchanged.
   *
   * Regression: every empty item was dropped, so a saved [[], ["a"]] became
   * [["a"]].
   */
  public function testSavedEmptyItemsSurviveSave(): void {
    $schema = json_decode('{"type":"object","properties":{"grid":{"type":"array","items":{"type":"array","items":{"type":"string"}}}}}', flags: JSON_THROW_ON_ERROR);
    $form = new GeneratedSchemaForm($schema, currentInput: ['grid' => [[], ['a']]], withSave: TRUE);
    $result = $this->browserSubmit($form);
    $this->assertSame([], $result->errors);
    $this->assertSame([[], ['a']], $result->values['grid']);
  }

  /**
   * An item appended and never filled is not stored.
   */
  public function testAppendedEmptyItemIsDropped(): void {
    $schema = json_decode('{"type":"object","properties":{"grid":{"type":"array","items":{"type":"array","items":{"type":"string"}}}}}', flags: JSON_THROW_ON_ERROR);
    $form = new GeneratedSchemaForm($schema, currentInput: ['grid' => [['a']]], withSave: TRUE);
    $appended = $this->browserSubmit($form, [], NULL, 'grid_add_more', 'Append an item');
    $this->assertSame([0, 1], WidgetItems::read($appended->formState, ['grid'])->deltas);
    $result = $this->browserSubmit($form, storage: $appended->formState->getStorage());
    $this->assertSame([], $result->errors);
    $this->assertSame([['a']], $result->values['grid']);
  }

  /**
   * A saved item whose own items were all removed is saved as empty.
   *
   * The emptied item showed a saved value, so it is not taken for an item
   * that was appended and never filled.
   */
  public function testItemEmptiedByRemovalIsSaved(): void {
    $schema = json_decode('{"type":"object","properties":{"grid":{"type":"array","items":{"type":"array","items":{"type":"string"}}}}}', flags: JSON_THROW_ON_ERROR);
    $form = new GeneratedSchemaForm($schema, currentInput: ['grid' => [['a'], ['b']]], withSave: TRUE);
    $removed = $this->browserSubmit($form, [], NULL, 'grid-0-0-remove', '⨯');
    $this->assertTrue($removed->formState->isRebuilding());
    $result = $this->browserSubmit($form, storage: $removed->formState->getStorage());
    $this->assertSame([], $result->errors);
    $this->assertSame([[], ['b']], $result->values['grid']);
  }

  /**
   * An appended item that shows an empty default is saved as empty.
   */
  public function testAppendedItemWithEmptyDefaultIsSaved(): void {
    $schema = json_decode('{"type":"object","properties":{"grid":{"type":"array","items":{"type":"array","default":[],"items":{"type":"string"}}}}}', flags: JSON_THROW_ON_ERROR);
    $form = new GeneratedSchemaForm($schema, currentInput: ['grid' => [['a']]], withSave: TRUE);
    $appended = $this->browserSubmit($form, [], NULL, 'grid_add_more', 'Append an item');
    $result = $this->browserSubmit($form, storage: $appended->formState->getStorage());
    $this->assertSame([], $result->errors);
    $this->assertSame([['a'], []], $result->values['grid']);
  }

}
