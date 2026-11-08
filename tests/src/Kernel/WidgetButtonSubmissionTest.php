<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SchemaForms\Drupal\ButtonNames;
use SchemaForms\Drupal\ElementScaffold;
use SchemaForms\Drupal\EnumElements;
use SchemaForms\Drupal\FieldBuilder;
use SchemaForms\Drupal\FieldContext;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\FormTreeBuilder;
use SchemaForms\Drupal\Labels;
use SchemaForms\Drupal\MultivalueCallbacks;
use SchemaForms\Drupal\MultivalueWidgetBuilder;
use SchemaForms\Drupal\RenderChildren;
use SchemaForms\Drupal\RequiredMarker;
use SchemaForms\Drupal\SavedInput;
use SchemaForms\Drupal\SchemaPreparer;
use SchemaForms\Drupal\SchemaTypes;
use SchemaForms\Drupal\UnsupportedElement;
use SchemaForms\Drupal\WidgetButton;
use SchemaForms\Drupal\WidgetState;
use SchemaForms\Drupal\WidgetStateStorage;
use SchemaForms\Tests\Kernel\Form\GeneratedSchemaForm;
use SchemaForms\Tests\Kernel\Form\TwoSchemaForm;

/**
 * Multivalue buttons act on their own widget through the real FormBuilder.
 */
#[CoversClass(FormGeneratorDrupal::class)]
#[CoversClass(ButtonNames::class)]
#[CoversClass(MultivalueCallbacks::class)]
#[CoversClass(WidgetState::class)]
#[CoversClass(WidgetStateStorage::class)]
#[CoversClass(ElementScaffold::class)]
#[CoversClass(EnumElements::class)]
#[CoversClass(FieldBuilder::class)]
#[CoversClass(FieldContext::class)]
#[CoversClass(FormTreeBuilder::class)]
#[CoversClass(Labels::class)]
#[CoversClass(MultivalueWidgetBuilder::class)]
#[CoversClass(RenderChildren::class)]
#[CoversClass(RequiredMarker::class)]
#[CoversClass(SavedInput::class)]
#[CoversClass(SchemaPreparer::class)]
#[CoversClass(SchemaTypes::class)]
#[CoversClass(UnsupportedElement::class)]
#[CoversClass(WidgetButton::class)]
#[RunTestsInSeparateProcesses]
final class WidgetButtonSubmissionTest extends SchemaFormsKernelTestBase {

  /**
   * {@inheritdoc}
   */
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
    $names = array_map(static fn (string $key): string => $built[$key]['add_more']['#name'], [
      'tag-list',
      'tag_list',
      'plain',
    ]);
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
   * Two containers with the same schema keep apart when namespaced.
   */
  public function testNamespacedContainers(): void {
    $schema = json_decode('{"type":"object","properties":{"items":{"type":"array","items":{"type":"string"}}}}', flags: JSON_THROW_ON_ERROR);
    $form = new TwoSchemaForm($schema, ['left' => 'left', 'right' => 'right']);
    $built = $this->browserSubmit($form)->form;
    $left = $built['left']['items']['add_more']['#name'];
    $this->assertNotSame($left, $built['right']['items']['add_more']['#name']);

    $result = $this->browserSubmit($form, [], NULL, $left, 'Append an item');

    $this->assertTrue($result->formState->isRebuilding());
    $rebuilt = $this->rebuild($result->formState, $form);
    $this->assertArrayHasKey(0, $rebuilt['left']['items']);
    $this->assertArrayNotHasKey(0, $rebuilt['right']['items']);
  }

  /**
   * Containers without a namespace keep today's names and shared state.
   */
  public function testContainersWithoutNamespace(): void {
    $schema = json_decode('{"type":"object","properties":{"items":{"type":"array","items":{"type":"string"}}}}', flags: JSON_THROW_ON_ERROR);
    $built = $this->browserSubmit(new TwoSchemaForm($schema))->form;
    $this->assertSame('items_add_more', $built['left']['items']['add_more']['#name']);
    $this->assertSame('items_add_more', $built['right']['items']['add_more']['#name']);
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

  /**
   * Rebuilds a form from a submitted state, as the next request would.
   */
  private function rebuild(FormStateInterface $form_state, FormInterface $form): array {
    $form_state->setRequestMethod('POST');
    $form_state->setFormObject($form);
    return $this->container->get('form_builder')->rebuildForm($form->getFormId(), $form_state);
  }

}
