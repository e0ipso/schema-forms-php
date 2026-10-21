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
   * Removing an item returns the deletion message for its AJAX target.
   */
  public function testRemoveAjaxResponse(): void {
    $state = new FormState();
    $state->setTriggeringElement(['#delta' => 0]);
    $result = FormGeneratorDrupal::removeOneAjax([], $state);
    $this->assertSame('<div class="ajax-new-content recently-deleted-element"><em>', $result['#prefix']);
    $this->assertSame('- Deleted -', $result['#markup']->getUntranslatedString());
    $this->assertSame('</em></div>', $result['#suffix']);

    $state->setTriggeringElement([]);
    $this->assertNull(FormGeneratorDrupal::removeOneAjax([], $state));
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

}
