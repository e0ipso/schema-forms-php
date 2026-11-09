<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal\SchemaForms;

use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Markup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\MultivalueCallbacks;
use SchemaForms\Drupal\WidgetItems;
use SchemaForms\Drupal\WidgetLocation;
use SchemaForms\Drupal\WidgetStateStorage;
use SchemaForms\FormOptions;
use SchemaForms\Tests\Fixture\TranslationContainer;
use Shaper\Util\Context;

/**
 * Exercises multivalue callbacks with generated widgets and real form state.
 */
final class FormGeneratorDrupalCallbacksTest extends TestCase {

  use TranslationContainer;

  /**
   * Adding and removing items survives rebuilding the generated widget.
   */
  #[DataProvider('savedItemsProvider')]
  public function testAddAndRemoveRoundTrip(array $saved): void {
    $generator = new FormGeneratorDrupal();
    $schema = json_decode('{"type":"object","properties":{"items":{"type":"array","items":{"type":"string"}}}}');
    $state = new FormState();
    $context = new Context(['options' => new FormOptions(formState: $state, currentInput: ['items' => $saved])]);
    $form = ['host' => $generator->transform($schema, $context)];
    $this->assertFalse($state->isRebuilding());
    $state->setTriggeringElement($form['host']['items']['add_more'] + [
      '#array_parents' => ['host', 'items', 'add_more'],
    ]);

    MultivalueCallbacks::appendSubmit($form, $state);

    $appended = count($saved);
    $this->assertSame(range(0, $appended), WidgetItems::read($state, ['items'])->deltas);
    $this->assertTrue($state->isRebuilding());
    $form = ['host' => $generator->transform($schema, $context)];
    $this->assertArrayHasKey($appended, $form['host']['items']);
    $this->assertArrayNotHasKey('#default_value', $form['host']['items'][$appended]['removable_element']);

    $removed = $saved ? 1 : 0;
    $state->setRebuild(FALSE);
    $state->setTriggeringElement($form['host']['items'][$removed]['remove_one'] + [
      '#array_parents' => ['host', 'items', $removed, 'remove_one'],
      '#parents' => ['host', 'items', $removed, 'remove_one'],
    ]);
    MultivalueCallbacks::removeSubmit($form, $state);

    $expected = array_values(array_diff(range(0, $appended), [$removed]));
    $this->assertSame($expected, array_values(WidgetItems::read($state, ['items'])->deltas));
    $this->assertTrue($state->isRebuilding());
    $rebuilt = $generator->transform($schema, $context)['items'];
    $this->assertArrayNotHasKey($removed, $rebuilt);
    foreach ($expected as $delta) {
      $this->assertArrayHasKey($delta, $rebuilt);
      $this->assertSame($saved[$delta] ?? NULL, $rebuilt[$delta]['removable_element']['#default_value'] ?? NULL);
    }
  }

  /**
   * Provides saved item lists to append to and remove from.
   *
   * @return array
   *   The test cases.
   */
  public static function savedItemsProvider(): array {
    return ['empty widget' => [[]], 'existing items' => [['first', 'second']]];
  }

  /**
   * A button finds its widget wherever host code nests it inside the widget.
   *
   * Host code may wrap the buttons in containers through a form alter, so
   * the callbacks cannot count the levels between a button and its widget.
   */
  public function testButtonsNestedDeeperFindTheirWidget(): void {
    $schema = json_decode('{"type":"object","properties":{"items":{"type":"array","items":{"type":"string"}}}}');
    $state = new FormState();
    $context = new Context(['options' => new FormOptions(formState: $state, currentInput: ['items' => ['a', 'b']])]);
    $widget = (new FormGeneratorDrupal())->transform($schema, $context)['items'];
    $widget[1]['actions'] = ['#type' => 'container', 'remove_one' => $widget[1]['remove_one']];
    $widget['actions'] = ['#type' => 'container', 'add_more' => $widget['add_more']];
    unset($widget[1]['remove_one'], $widget['add_more']);
    $form = ['host' => ['wrapper' => ['items' => $widget]]];

    $state->setTriggeringElement($widget[1]['actions']['remove_one'] + [
      '#array_parents' => ['host', 'wrapper', 'items', 1, 'actions', 'remove_one'],
    ]);
    MultivalueCallbacks::removeSubmit($form, $state);
    $this->assertSame([0], array_values(WidgetItems::read($state, ['items'])->deltas));

    $state->setTriggeringElement($widget['actions']['add_more'] + [
      '#array_parents' => ['host', 'wrapper', 'items', 'actions', 'add_more'],
    ]);
    MultivalueCallbacks::appendSubmit($form, $state);
    $this->assertSame([0, 2], array_values(WidgetItems::read($state, ['items'])->deltas));
  }

  /**
   * Items are numbered by position, and each Remove button names its item.
   */
  public function testItemNumbersFollowPositions(): void {
    $generator = new FormGeneratorDrupal();
    $schema = json_decode('{"type":"object","properties":{"items":{"type":"array","title":"Tags","items":{"type":"string"}}}}');
    $state = new FormState();
    (new WidgetStateStorage($state))->set(new WidgetLocation(['items'], 'items'), [
      'items_indices' => [0 => 0, 2 => 2, 3 => 5],
      'next_delta' => 6,
    ]);
    $widget = $generator->transform($schema, new Context(['options' => new FormOptions(formState: $state)]))['items'];
    $titles = array_map(static fn (int $delta): string => (string) $widget[$delta]['removable_element']['#title'], [
      0,
      2,
      5,
    ]);
    $this->assertSame(['Tags (value 1)', 'Tags (value 2)', 'Tags (value 3)'], $titles);
    $names = array_map(static fn (int $delta): string => (string) $widget[$delta]['remove_one']['#attributes']['aria-label'], [
      0,
      2,
      5,
    ]);
    $this->assertSame(['Remove Tags (value 1)', 'Remove Tags (value 2)', 'Remove Tags (value 3)'], $names);
    $this->assertSame($names[1], (string) $widget[2]['remove_one']['#attributes']['title']);
  }

  /**
   * After-build cleans only submitted values and records the final form path.
   */
  #[DataProvider('processingProvider')]
  public function testAfterBuildCleaning(bool $processing): void {
    $state = (new FormState())->setProcessInput($processing);
    $generator = new FormGeneratorDrupal();
    $schema = json_decode('{"type":"object","properties":{"items":{"type":"array","items":{"type":"string"}}}}');
    $widget = $generator->transform($schema, new Context(['options' => new FormOptions(formState: $state)]))['items'];
    $widget['#parents'] = ['host', 'items'];
    $widget['#array_parents'] = ['host', 'items'];
    $raw = [
      0 => ['removable_element' => 'first', 'remove_one' => Markup::create('Remove')],
      2 => ['removable_element' => [], 'remove_one' => Markup::create('Remove')],
      4 => ['removable_element' => '0', 'remove_one' => Markup::create('Remove')],
      'add_more' => Markup::create('Append an item'),
    ];
    $state->setValues(['host' => ['items' => $raw], 'unrelated' => 'keep']);

    $result = MultivalueCallbacks::afterBuild($widget, $state);

    $this->assertSame($processing ? ['first', '0'] : $raw, $state->getValue(['host', 'items']));
    $this->assertSame('keep', $state->getValue('unrelated'));
    $this->assertSame(['host', 'items'], WidgetItems::read($state, ['items'])->arrayParents);
    $this->assertSame($widget, $result);
  }

  /**
   * Provides whether the form is processing submitted input.
   *
   * @return array
   *   The test cases.
   */
  public static function processingProvider(): array {
    return ['submitted form' => [TRUE], 'initial build' => [FALSE]];
  }

  /**
   * Removing an item sends no commands when no remove button triggered it.
   */
  public function testRemoveAjaxWithoutRemoveButton(): void {
    $state = new FormState();
    $state->setTriggeringElement([]);
    $this->assertSame([], MultivalueCallbacks::removeAjax([], $state)->getCommands());
  }

  /**
   * An item appended after the last item was removed starts empty.
   *
   * Regression: the new item reused the removed item's delta, so it showed
   * the removed item's saved value, and saving brought the value back.
   */
  public function testAppendAfterRemovingTheLastItemStartsEmpty(): void {
    $generator = new FormGeneratorDrupal();
    $schema = json_decode('{"type":"object","properties":{"items":{"type":"array","items":{"type":"string"}}}}');
    $state = new FormState();
    $context = new Context(['options' => new FormOptions(formState: $state, currentInput: ['items' => ['a', 'b']])]);
    $form = $generator->transform($schema, $context);
    $state->setTriggeringElement($form['items'][1]['remove_one'] + [
      '#array_parents' => ['items', 1, 'remove_one'],
      '#parents' => ['items', 1, 'remove_one'],
    ]);
    MultivalueCallbacks::removeSubmit($form, $state);
    // The form cache serializes the state between requests.
    // phpcs:ignore DrupalPractice.FunctionCalls.InsecureUnserialize -- Drupal's form cache restores every class, as this does.
    $state = unserialize(serialize($state));
    $context['options'] = new FormOptions(formState: $state, currentInput: ['items' => ['a', 'b']]);
    $form = $generator->transform($schema, $context);
    $state->setTriggeringElement($form['items']['add_more'] + ['#array_parents' => ['items', 'add_more']]);
    MultivalueCallbacks::appendSubmit($form, $state);

    $items = $generator->transform($schema, $context)['items'];
    $deltas = array_values(array_filter(array_keys($items), 'is_int'));
    $this->assertCount(2, $deltas);
    $this->assertSame('a', $items[$deltas[0]]['removable_element']['#default_value']);
    $this->assertArrayNotHasKey('#default_value', $items[$deltas[1]]['removable_element']);
    $this->assertSame($deltas, array_values(WidgetItems::read($state, ['items'])->deltas));
  }

  /**
   * A namespaced container keeps its widget state apart.
   */
  public function testNamespacedWidgetState(): void {
    $schema = json_decode('{"type":"object","properties":{"items":{"type":"array","items":{"type":"string"}}}}');
    $state = new FormState();
    $plain = (new FormGeneratorDrupal())->transform($schema, new Context(['options' => new FormOptions(formState: $state)]));
    $options = new FormOptions(formState: $state, namespace: 'host');
    $form = ['host' => (new FormGeneratorDrupal())->transform($schema, new Context(['options' => $options]))];
    $this->assertArrayNotHasKey('#schema_namespace', $plain['items']);
    $this->assertSame('host', $form['host']['items']['#schema_namespace']);
    $state->setTriggeringElement($form['host']['items']['add_more'] + [
      '#array_parents' => ['host', 'items', 'add_more'],
    ]);

    MultivalueCallbacks::appendSubmit($form, $state);

    $this->assertSame([0], WidgetItems::read($state, ['items'], 'host')->deltas);
    $this->assertSame([], WidgetItems::read($state, ['items'])->deltas);
    $this->assertArrayHasKey('host', $state->getStorage()['prop_storage']['#namespaces']);
    (new WidgetStateStorage($state))->set(new WidgetLocation(['items'], 'items', 'host'), ['items_indices' => [3]]);
    $options = new FormOptions(
      formState: $state,
      namespace: 'host',
    );
    $rebuilt = (new FormGeneratorDrupal())->transform($schema, new Context(['options' => $options]));
    $this->assertArrayHasKey(3, $rebuilt['items']);
  }

  /**
   * Widgets with matching names in different parents keep separate state.
   *
   * It runs through the deprecated accessors, which host code may still
   * call.
   */
  #[IgnoreDeprecations]
  public function testWidgetStateIsIsolated(): void {
    $this->expectUserDeprecationMessage('FormGeneratorDrupal::getPropFormState() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. Use WidgetItems::read() instead. See https://github.com/e0ipso/schema-forms-php#upgrading');
    $this->expectUserDeprecationMessage('FormGeneratorDrupal::setPropFormState() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. The Append and Remove buttons keep the widget state; read it with WidgetItems::read(). See https://github.com/e0ipso/schema-forms-php#upgrading');
    $state = new FormState();
    $left = ['items_indices' => [0, 2], 'array_parents' => ['left', 'items']];
    $right = ['items_indices' => [0], 'array_parents' => ['right', 'items']];
    FormGeneratorDrupal::setPropFormState(['left', 'items'], 'items', $state, $left);
    FormGeneratorDrupal::setPropFormState(['right', 'items'], 'items', $state, $right);
    FormGeneratorDrupal::setPropFormState(['left', 'items'], 'other', $state, $right);

    $this->assertSame($left, FormGeneratorDrupal::getPropFormState(['left', 'items'], 'items', $state));
    $this->assertSame($right, FormGeneratorDrupal::getPropFormState(['right', 'items'], 'items', $state));
    $this->assertSame($right, FormGeneratorDrupal::getPropFormState(['left', 'items'], 'other', $state));
    FormGeneratorDrupal::setPropFormState(['left', 'items'], 'items', $state, []);
    $this->assertSame([], FormGeneratorDrupal::getPropFormState(['left', 'items'], 'items', $state));
    $this->assertSame($right, FormGeneratorDrupal::getPropFormState(['right', 'items'], 'items', $state));
    $this->assertSame($right, FormGeneratorDrupal::getPropFormState(['left', 'items'], 'other', $state));
  }

  /**
   * Invalid or absent limits retain the documented unlimited fallback.
   */
  #[DataProvider('cardinalityProvider')]
  public function testCardinalitySelection(mixed $max_items, int $expected, bool $valid): void {
    $property = (object) ['type' => 'array', 'items' => (object) ['type' => 'string']];
    if ($max_items !== 'missing') {
      $property->maxItems = $max_items;
    }
    $schema = (object) ['type' => 'object', 'properties' => (object) ['list' => $property]];
    $generator = new FormGeneratorDrupal();
    // transform() rejects an invalid limit, so build those without it.
    $form = $valid ? $generator->transform($schema) : $generator->doTransform($schema);
    $this->assertSame($expected, $form['list']['#cardinality']);
  }

  /**
   * Provides maxItems values and the cardinality each selects.
   *
   * @return array
   *   The test cases.
   */
  public static function cardinalityProvider(): array {
    $cases = ['missing' => ['missing', -1, TRUE], 'zero' => [0, -1, TRUE]];
    foreach ([NULL, -1, 2.5, '2', 2.0, FALSE] as $value) {
      $cases[json_encode($value, JSON_PRESERVE_ZERO_FRACTION)] = [$value, -1, FALSE];
    }
    $cases['one'] = [1, 1, TRUE];
    $cases['two'] = [2, 2, TRUE];
    return $cases;
  }

  /**
   * Finite limits count surviving items, not the last delta, after removal.
   */
  public function testFiniteRemovalReappendAndForcedAppend(): void {
    $generator = new FormGeneratorDrupal();
    $schema = json_decode('{"type":"object","properties":{"a":{"type":"array","maxItems":2,"items":{"type":"string"}},"b":{"type":"array","maxItems":1,"items":{"type":"string"}}}}');
    $state = new FormState();
    $context = new Context(['options' => new FormOptions(formState: $state, currentInput: ['a' => ['first', 'second']])]);
    $form = $generator->transform($schema, $context);
    $this->assertFalse($form['a']['add_more']['#access']);
    $this->assertTrue($form['b']['add_more']['#access'] ?? TRUE);
    $state->setTriggeringElement($form['a']['add_more'] + ['#array_parents' => ['a', 'add_more']]);
    MultivalueCallbacks::appendSubmit($form, $state);
    $this->assertSame([0, 1], WidgetItems::read($state, ['a'])->deltas);
    $this->assertTrue($state->isRebuilding());
    $state->setTriggeringElement($form['a'][0]['remove_one'] + [
      '#array_parents' => ['a', 0, 'remove_one'],
      '#parents' => ['a', 0, 'remove_one'],
    ]);
    MultivalueCallbacks::removeSubmit($form, $state);
    $form = $generator->transform($schema, $context);
    $this->assertTrue($form['a']['add_more']['#access'] ?? TRUE);
    $this->assertArrayNotHasKey(0, $form['a']);
    $this->assertSame('second', $form['a'][1]['removable_element']['#default_value']);
    $state->setTriggeringElement($form['a']['add_more'] + ['#array_parents' => ['a', 'add_more']]);
    MultivalueCallbacks::appendSubmit($form, $state);
    $form = $generator->transform($schema, $context);
    $this->assertSame([1, 2], array_values(WidgetItems::read($state, ['a'])->deltas));
    $this->assertFalse($form['a']['add_more']['#access']);
    $this->assertArrayNotHasKey(0, $form['b']);
    $this->assertSame(2, $form['a']['#max_delta']);
    $ajax = MultivalueCallbacks::appendedWidget($form, $state);
    $this->assertFalse($ajax['add_more']['#access']);
    $this->assertStringContainsString('ajax-new-content', $ajax[2]['#prefix']);
    $this->assertStringNotContainsString('ajax-new-content', $ajax[1]['#prefix']);
    $this->assertSame($ajax['add_more']['#ajax']['wrapper'], $ajax[2]['remove_one']['#ajax']['wrapper']);
    $this->assertStringContainsString($ajax[2]['#wrapper_id'], $ajax[2]['#prefix']);
    $this->assertNotSame($ajax[1]['#wrapper_id'], $ajax[2]['#wrapper_id']);
    $this->assertArrayNotHasKey('#default_value', $ajax[2]['removable_element']);
    $state->setTriggeringElement($form['b']['add_more'] + ['#array_parents' => ['b', 'add_more']]);
    $empty = MultivalueCallbacks::appendedWidget($form, $state);
    $this->assertNull($empty['#max_delta']);
    $this->assertArrayNotHasKey(0, $empty);
    MultivalueCallbacks::appendSubmit($form, $state);
    $form = $generator->transform($schema, $context);
    $this->assertFalse($form['b']['add_more']['#access']);
    $this->assertSame([1, 2], array_values(WidgetItems::read($state, ['a'])->deltas));
    $this->assertSame('invisible', $form['b'][0]['removable_element']['#title_display']);
    $this->assertSame('fade', $form['b']['add_more']['#ajax']['effect']);
  }

}
