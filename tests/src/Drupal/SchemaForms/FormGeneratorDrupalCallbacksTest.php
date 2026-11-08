<?php

namespace SchemaForms\Tests\Drupal\SchemaForms;

use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Markup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\FormGeneratorDrupal;
use Shaper\Util\Context;

/**
 * Exercises multivalue callbacks with generated widgets and real form state.
 */
#[CoversClass(FormGeneratorDrupal::class)]
final class FormGeneratorDrupalCallbacksTest extends TestCase {

  /**
   * Adding and removing items survives rebuilding the generated widget.
   */
  #[DataProvider('savedItemsProvider')]
  public function testAddAndRemoveRoundTrip(array $saved): void {
    $generator = new FormGeneratorDrupal();
    $schema = json_decode('{"type":"object","properties":{"items":{"type":"array","items":{"type":"string"}}}}');
    $state = new FormState();
    $context = new Context(['form_state' => $state, 'current_input' => ['items' => $saved]]);
    $form = ['host' => $generator->transform($schema, $context)];
    $this->assertFalse($state->isRebuilding());
    $state->setTriggeringElement($form['host']['items']['add_more'] + ['#array_parents' => ['host', 'items', 'add_more']]);

    FormGeneratorDrupal::addMoreSubmit($form, $state);

    $appended = count($saved);
    $this->assertSame(range(0, $appended), FormGeneratorDrupal::getPropFormState(['items'], 'items', $state)['items_indices']);
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
    FormGeneratorDrupal::removeOneSubmit($form, $state);

    $expected = array_values(array_diff(range(0, $appended), [$removed]));
    $this->assertSame($expected, array_values(FormGeneratorDrupal::getPropFormState(['items'], 'items', $state)['items_indices']));
    $this->assertTrue($state->isRebuilding());
    $rebuilt = $generator->transform($schema, $context)['items'];
    $this->assertArrayNotHasKey($removed, $rebuilt);
    foreach ($expected as $delta) {
      $this->assertArrayHasKey($delta, $rebuilt);
      $this->assertSame($saved[$delta] ?? NULL, $rebuilt[$delta]['removable_element']['#default_value'] ?? NULL);
    }
  }

  public static function savedItemsProvider(): array {
    return ['empty widget' => [[]], 'existing items' => [['first', 'second']]];
  }

  /**
   * After-build cleans only submitted values and records the final form path.
   */
  #[DataProvider('processingProvider')]
  public function testAfterBuildCleaning(bool $processing): void {
    $state = (new FormState())->setProcessInput($processing);
    $generator = new FormGeneratorDrupal();
    $schema = json_decode('{"type":"object","properties":{"items":{"type":"array","items":{"type":"string"}}}}');
    $widget = $generator->transform($schema, new Context(['form_state' => $state]))['items'];
    $widget['#parents'] = ['host', 'items'];
    $widget['#array_parents'] = ['host', 'items'];
    $raw = [
      0 => ['removable_element' => 'first', 'remove_one' => Markup::create('Remove')],
      2 => ['removable_element' => [], 'remove_one' => Markup::create('Remove')],
      4 => ['removable_element' => '0', 'remove_one' => Markup::create('Remove')],
      'add_more' => Markup::create('Append an item'),
    ];
    $state->setValues(['host' => ['items' => $raw], 'unrelated' => 'keep']);

    $result = FormGeneratorDrupal::multiValueAfterBuild($widget, $state);

    $this->assertSame($processing ? ['first', '0'] : $raw, $state->getValue(['host', 'items']));
    $this->assertSame('keep', $state->getValue('unrelated'));
    $this->assertSame(['host', 'items'], FormGeneratorDrupal::getPropFormState(['items'], 'items', $state)['array_parents']);
    $this->assertSame($widget, $result);
  }

  public static function processingProvider(): array {
    return ['submitted form' => [TRUE], 'initial build' => [FALSE]];
  }

  /**
   * Removing an item returns nothing when no remove button triggered it.
   */
  public function testRemoveAjaxWithoutRemoveButton(): void {
    $state = new FormState();
    $state->setTriggeringElement([]);
    $this->assertNull(FormGeneratorDrupal::removeOneAjax([], $state));
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
    $context = new Context(['form_state' => $state, 'current_input' => ['items' => ['a', 'b']]]);
    $form = $generator->transform($schema, $context);
    $state->setTriggeringElement($form['items'][1]['remove_one'] + [
      '#array_parents' => ['items', 1, 'remove_one'],
      '#parents' => ['items', 1, 'remove_one'],
    ]);
    FormGeneratorDrupal::removeOneSubmit($form, $state);
    // The form cache serializes the state between requests.
    $state = unserialize(serialize($state));
    $context['form_state'] = $state;
    $form = $generator->transform($schema, $context);
    $state->setTriggeringElement($form['items']['add_more'] + ['#array_parents' => ['items', 'add_more']]);
    FormGeneratorDrupal::addMoreSubmit($form, $state);

    $items = $generator->transform($schema, $context)['items'];
    $deltas = array_values(array_filter(array_keys($items), 'is_int'));
    $this->assertCount(2, $deltas);
    $this->assertSame('a', $items[$deltas[0]]['removable_element']['#default_value']);
    $this->assertArrayNotHasKey('#default_value', $items[$deltas[1]]['removable_element']);
    $this->assertSame($deltas, array_values(FormGeneratorDrupal::getPropFormState(['items'], 'items', $state)['items_indices']));
  }

  /**
   * Removing an item replaces the whole widget, so Append can come back.
   *
   * Regression: Remove replaced only the removed item, so an Append button
   * hidden at the maxItems limit stayed hidden until the page was reloaded.
   */
  public function testRemoveAjaxReturnsTheWidget(): void {
    $generator = new FormGeneratorDrupal();
    $schema = json_decode('{"type":"object","properties":{"a":{"type":"array","maxItems":2,"items":{"type":"string"}}}}');
    $state = new FormState();
    $context = new Context(['form_state' => $state, 'current_input' => ['a' => ['first', 'second']]]);
    $form = ['host' => $generator->transform($schema, $context)];
    $this->assertFalse($form['host']['a']['add_more']['#access']);
    $wrapper = $form['host']['a']['add_more']['#ajax']['wrapper'];
    $this->assertSame($wrapper, $form['host']['a'][0]['remove_one']['#ajax']['wrapper']);
    $state->setTriggeringElement($form['host']['a'][0]['remove_one'] + [
      '#array_parents' => ['host', 'a', 0, 'remove_one'],
      '#parents' => ['host', 'a', 0, 'remove_one'],
    ]);
    FormGeneratorDrupal::removeOneSubmit($form, $state);
    $rebuilt = ['host' => $generator->transform($schema, $context)];

    $response = FormGeneratorDrupal::removeOneAjax($rebuilt, $state);

    $this->assertSame($rebuilt['host']['a'], $response);
    $this->assertTrue($response['add_more']['#access'] ?? TRUE);
    $this->assertStringContainsString('id="' . $wrapper . '"', $form['host']['a']['#prefix']);
    $this->assertStringContainsString('id="' . $response['add_more']['#ajax']['wrapper'] . '"', $response['#prefix']);
  }

  /**
   * Widgets with matching names in different parents keep separate state.
   */
  public function testWidgetStateIsIsolated(): void {
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
  public function testCardinalitySelection(object $schema, int $expected): void {
    // Invalid schemas can be rejected upstream, so test this pure policy here.
    $method = new \ReflectionMethod(FormGeneratorDrupal::class, 'cardinality');
    $this->assertSame($expected, $method->invoke(NULL, $schema));
  }

  public static function cardinalityProvider(): array {
    $cases = ['missing' => [(object) [], -1]];
    foreach ([NULL, 0, -1, 2.5, '2', 2.0, FALSE] as $value) {
      $cases[json_encode($value, JSON_PRESERVE_ZERO_FRACTION)] = [(object) ['maxItems' => $value], -1];
    }
    $cases['one'] = [(object) ['maxItems' => 1], 1];
    $cases['two'] = [(object) ['maxItems' => 2], 2];
    return $cases;
  }

  /**
   * Finite limits count surviving items, not the last delta, after removal.
   */
  public function testFiniteRemovalReappendAndForcedAppend(): void {
    $generator = new FormGeneratorDrupal();
    $schema = json_decode('{"type":"object","properties":{"a":{"type":"array","maxItems":2,"items":{"type":"string"}},"b":{"type":"array","maxItems":1,"items":{"type":"string"}}}}');
    $state = new FormState();
    $context = new Context(['form_state' => $state, 'current_input' => ['a' => ['first', 'second']]]);
    $form = $generator->transform($schema, $context);
    $this->assertFalse($form['a']['add_more']['#access']);
    $this->assertTrue($form['b']['add_more']['#access'] ?? TRUE);
    $state->setTriggeringElement($form['a']['add_more'] + ['#array_parents' => ['a', 'add_more']]);
    FormGeneratorDrupal::addMoreSubmit($form, $state);
    $this->assertSame([0, 1], FormGeneratorDrupal::getPropFormState(['a'], 'a', $state)['items_indices']);
    $this->assertTrue($state->isRebuilding());
    $state->setTriggeringElement($form['a'][0]['remove_one'] + ['#array_parents' => ['a', 0, 'remove_one'], '#parents' => ['a', 0, 'remove_one']]);
    FormGeneratorDrupal::removeOneSubmit($form, $state);
    $form = $generator->transform($schema, $context);
    $this->assertTrue($form['a']['add_more']['#access'] ?? TRUE);
    $this->assertArrayNotHasKey(0, $form['a']);
    $this->assertSame('second', $form['a'][1]['removable_element']['#default_value']);
    $state->setTriggeringElement($form['a']['add_more'] + ['#array_parents' => ['a', 'add_more']]);
    FormGeneratorDrupal::addMoreSubmit($form, $state);
    $form = $generator->transform($schema, $context);
    $this->assertSame([1, 2], array_values(FormGeneratorDrupal::getPropFormState(['a'], 'a', $state)['items_indices']));
    $this->assertFalse($form['a']['add_more']['#access']);
    $this->assertArrayNotHasKey(0, $form['b']);
    $this->assertSame(2, $form['a']['#max_delta']);
    $ajax = FormGeneratorDrupal::addMoreAjax($form, $state);
    $this->assertFalse($ajax['add_more']['#access']);
    $this->assertStringContainsString('ajax-new-content', $ajax[2]['#prefix']);
    $this->assertStringNotContainsString('ajax-new-content', $ajax[1]['#prefix']);
    $this->assertSame($ajax['add_more']['#ajax']['wrapper'], $ajax[2]['remove_one']['#ajax']['wrapper']);
    $this->assertStringContainsString($ajax[2]['#wrapper_id'], $ajax[2]['#prefix']);
    $this->assertNotSame($ajax[1]['#wrapper_id'], $ajax[2]['#wrapper_id']);
    $this->assertArrayNotHasKey('#default_value', $ajax[2]['removable_element']);
    $state->setTriggeringElement($form['b']['add_more'] + ['#array_parents' => ['b', 'add_more']]);
    $empty = FormGeneratorDrupal::addMoreAjax($form, $state);
    $this->assertNull($empty['#max_delta']);
    $this->assertArrayNotHasKey(0, $empty);
    FormGeneratorDrupal::addMoreSubmit($form, $state);
    $form = $generator->transform($schema, $context);
    $this->assertFalse($form['b']['add_more']['#access']);
    $this->assertSame([1, 2], array_values(FormGeneratorDrupal::getPropFormState(['a'], 'a', $state)['items_indices']));
    $this->assertSame('invisible', $form['b'][0]['removable_element']['#title_display']);
    $this->assertSame('fade', $form['b']['add_more']['#ajax']['effect']);
  }

}
