<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use Drupal\Core\Form\FormStateInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\MultivalueCallbacks;
use SchemaForms\Drupal\MultivalueWidget;
use SchemaForms\Drupal\MultivalueWidgetBuilder;
use SchemaForms\Drupal\RenderChildren;
use SchemaForms\Drupal\WidgetAjaxResponse;
use SchemaForms\Tests\Kernel\Form\GeneratedSchemaForm;

/**
 * The widget buttons' AJAX responses announce the change and move focus.
 */
#[CoversClass(FormGeneratorDrupal::class)]
#[CoversClass(MultivalueCallbacks::class)]
#[CoversClass(MultivalueWidget::class)]
#[CoversClass(MultivalueWidgetBuilder::class)]
#[CoversClass(RenderChildren::class)]
#[CoversClass(WidgetAjaxResponse::class)]
#[RunTestsInSeparateProcesses]
final class WidgetAjaxResponseTest extends SchemaFormsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Append replaces the widget, announces the item and focuses it.
   */
  public function testAppendResponse(): void {
    $form = $this->form(['a']);
    $result = $this->browserSubmit($form, [], NULL, 'items_add_more', 'Append an item');
    $rebuilt = $this->rebuild($result->formState, $form);

    $commands = FormGeneratorDrupal::addMoreAjax($rebuilt, $result->formState)->getCommands();

    $this->assertSame(['insert', 'insert', 'announce', 'focusFirst'], array_column($commands, 'command'));
    $this->assertNull($commands[0]['selector']);
    $this->assertNull($commands[0]['method']);
    $this->assertStringContainsString('id="' . $rebuilt['items']['add_more']['#ajax']['wrapper'] . '"', (string) $commands[0]['data']);
    $this->assertStringContainsString('ajax-new-content', (string) $commands[0]['data']);
    $this->assertStringContainsString('aria-label="Remove Items (value 2)"', (string) $commands[0]['data']);
    $this->assertSame('prepend', $commands[1]['method']);
    $this->assertSame('Item added: Items (value 2).', $commands[2]['text']);
    $this->assertSame('#' . $rebuilt['items'][1]['#wrapper_id'], $commands[3]['selector']);
  }

  /**
   * Status messages go into the wrapper the response inserts.
   *
   * Regression: the messages targeted the clicked button's wrapper, whose ID
   * the rebuild replaces, so after the insert they landed nowhere.
   */
  public function testMessagesTargetTheRebuiltWrapper(): void {
    $form = $this->form(['a']);
    $result = $this->browserSubmit($form, [], NULL, 'items_add_more', 'Append an item');
    $clicked = $result->formState->getTriggeringElement()['#ajax']['wrapper'];
    $rebuilt = $this->rebuild($result->formState, $form);
    $wrapper = $rebuilt['items']['add_more']['#ajax']['wrapper'];

    $commands = FormGeneratorDrupal::addMoreAjax($rebuilt, $result->formState)->getCommands();

    $this->assertNotSame($clicked, $wrapper);
    $this->assertSame('prepend', $commands[1]['method']);
    $this->assertSame('#' . $wrapper, $commands[1]['selector']);
    $this->assertStringContainsString('id="' . $wrapper . '"', (string) $commands[0]['data']);
  }

  /**
   * Without an item to announce, the response only replaces the widget.
   */
  public function testAppendResponseWithoutItems(): void {
    $result = $this->browserSubmit($this->form([]));
    $form = $result->form;
    $result->formState->setTriggeringElement($form['items']['add_more']);

    $commands = FormGeneratorDrupal::addMoreAjax($form, $result->formState)->getCommands();

    $this->assertSame(['insert', 'insert'], array_column($commands, 'command'));
  }

  /**
   * Without a triggering widget, Append names no selector it cannot fill.
   */
  public function testAppendWithoutWidget(): void {
    $result = $this->browserSubmit($this->form(['a']));
    $result->formState->setTriggeringElement([]);

    $commands = FormGeneratorDrupal::addMoreAjax($result->form, $result->formState)->getCommands();

    $this->assertSame(['insert', 'insert'], array_column($commands, 'command'));
    $this->assertNull($commands[1]['selector']);
  }

  /**
   * Remove focuses the item that took the removed item's place.
   */
  public function testRemoveFocusesTheNextItem(): void {
    $commands = $this->remove(['a', 'b', 'c'], 1, $rebuilt);

    $this->assertSame(['insert', 'insert', 'announce', 'focusFirst'], array_column($commands, 'command'));
    $this->assertNull($commands[0]['selector']);
    $this->assertStringContainsString('aria-label="Remove Items (value 2)"', (string) $commands[0]['data']);
    $this->assertSame('Item removed: Items (value 2).', $commands[2]['text']);
    $this->assertSame('#' . $rebuilt['items'][2]['#wrapper_id'], $commands[3]['selector']);
  }

  /**
   * Removing the last item focuses the Append button.
   */
  public function testRemoveLastFocusesAppend(): void {
    $commands = $this->remove(['a', 'b'], 1, $rebuilt);

    $this->assertSame('Item removed: Items (value 2).', $commands[2]['text']);
    $this->assertSame('#' . $rebuilt['items']['add_more']['#id'], $commands[3]['selector']);
  }

  /**
   * Without a reachable Append button, focus goes to the widget.
   */
  public function testRemoveAboveTheLimitFocusesTheWidget(): void {
    $commands = $this->remove(['a', 'b', 'c'], 2, $rebuilt, 1);

    $this->assertFalse($rebuilt['items']['add_more']['#access']);
    $this->assertSame('Item removed: Items (value 3).', $commands[2]['text']);
    $this->assertSame('#' . $rebuilt['items']['add_more']['#ajax']['wrapper'], $commands[3]['selector']);
  }

  /**
   * Without a rebuilt widget, the response names no selector it cannot fill.
   *
   * A bare "#" makes jQuery throw, which stops every later command.
   */
  public function testRemoveWithoutRebuiltWidget(): void {
    $result = $this->browserSubmit($this->form(['a']));
    $result->formState->setTriggeringElement([
      '#delta' => 0,
      '#array_parents' => ['gone', 0, 'remove_one'],
    ]);

    $commands = FormGeneratorDrupal::removeOneAjax($result->form, $result->formState)->getCommands();

    $this->assertSame(['insert', 'insert', 'announce'], array_column($commands, 'command'));
    $this->assertNull($commands[1]['selector']);
  }

  /**
   * Removing an item replaces the whole widget, so Append can come back.
   *
   * Regression: Remove replaced only the removed item, so an Append button
   * hidden at the maxItems limit stayed hidden until the page was reloaded.
   */
  public function testRemoveBringsAppendBack(): void {
    $form = $this->form(['a', 'b'], 2);
    $built = $this->browserSubmit($form)->form;
    $this->assertFalse($built['items']['add_more']['#access']);

    $commands = $this->remove(['a', 'b'], 0, $rebuilt, 2);

    $this->assertTrue($rebuilt['items']['add_more']['#access'] ?? TRUE);
    $this->assertStringContainsString('Append an item', (string) $commands[0]['data']);
    $this->assertStringContainsString('id="' . $rebuilt['items']['add_more']['#ajax']['wrapper'] . '"', (string) $commands[0]['data']);
    $this->assertSame('Item removed: Items (value 1).', $commands[2]['text']);
    $this->assertSame('#' . $rebuilt['items'][1]['#wrapper_id'], $commands[3]['selector']);
  }

  /**
   * Builds the test form around a list of saved items.
   *
   * @param list<string> $saved
   *   The saved items.
   * @param int|null $max_items
   *   The maxItems of the list, or NULL for none.
   *
   * @return \SchemaForms\Tests\Kernel\Form\GeneratedSchemaForm
   *   The form.
   */
  private function form(array $saved, ?int $max_items = NULL): GeneratedSchemaForm {
    $list = (object) ['type' => 'array', 'items' => (object) ['type' => 'string']];
    if ($max_items !== NULL) {
      $list->maxItems = $max_items;
    }
    $schema = (object) ['type' => 'object', 'properties' => (object) ['items' => $list]];
    return new GeneratedSchemaForm($schema, currentInput: ['items' => $saved], withSave: TRUE);
  }

  /**
   * Removes one item through FormBuilder and returns the response commands.
   *
   * @param list<string> $saved
   *   The saved items.
   * @param int $delta
   *   The delta of the item to remove.
   * @param array|null $rebuilt
   *   Receives the rebuilt form.
   * @param int|null $max_items
   *   The maxItems of the list, or NULL for none.
   *
   * @return list<array<string, mixed>>
   *   The rendered commands.
   */
  private function remove(array $saved, int $delta, ?array &$rebuilt, ?int $max_items = NULL): array {
    $form = $this->form($saved, $max_items);
    $result = $this->browserSubmit($form, [], NULL, 'items-' . $delta . '-remove', '⨯');
    $this->assertSame($delta, $result->formState->getTriggeringElement()['#delta']);
    $rebuilt = $this->rebuild($result->formState, $form);
    return FormGeneratorDrupal::removeOneAjax($rebuilt, $result->formState)->getCommands();
  }

  /**
   * Rebuilds a form from a submitted state, as the AJAX request does.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The submitted form state.
   * @param \SchemaForms\Tests\Kernel\Form\GeneratedSchemaForm $form
   *   The form.
   *
   * @return array
   *   The rebuilt form.
   */
  private function rebuild(FormStateInterface $form_state, GeneratedSchemaForm $form): array {
    $form_state->setRequestMethod('POST');
    $form_state->setFormObject($form);
    return $this->container->get('form_builder')->rebuildForm($form->getFormId(), $form_state);
  }

}
