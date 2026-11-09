<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal\SchemaForms;

use Drupal\Core\Form\FormState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\EnumElements;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\MultivalueCallbacks;
use SchemaForms\Drupal\MultivalueWidget;
use SchemaForms\Drupal\SchemaValidationBridge;
use SchemaForms\Drupal\WidgetItems;
use SchemaForms\Drupal\WidgetState;
use SchemaForms\Tests\Fixture\TranslationContainer;

/**
 * Each Form API callback copes with a request it was not built for.
 *
 * Form API calls these with whatever the form and the submission hold. A
 * missing triggering element, a button whose widget is gone or an element
 * without the keys the generator adds must leave the form state as it was
 * and still return the declared type.
 */
#[CoversClass(FormGeneratorDrupal::class)]
#[CoversClass(EnumElements::class)]
#[CoversClass(MultivalueCallbacks::class)]
#[CoversClass(MultivalueWidget::class)]
#[CoversClass(SchemaValidationBridge::class)]
final class FormGeneratorDrupalFailurePathTest extends TestCase {

  use TranslationContainer;

  /**
   * A widget without an Append button gains no untyped child.
   */
  public function testProcessWithoutAppendButton(): void {
    $element = ['#type' => 'details', '#parents' => ['items'], '#max_delta' => NULL];

    $this->assertSame($element, MultivalueCallbacks::process($element));
  }

  /**
   * After-build stores nothing for an element that is not a widget.
   */
  #[DataProvider('processingProvider')]
  public function testAfterBuildWithoutWidget(bool $processing): void {
    $form_state = (new FormState())->setProcessInput($processing)->setValues(['text' => 'a']);
    $element = ['#type' => 'textfield', '#parents' => ['text'], '#array_parents' => ['text']];

    $this->assertSame($element, MultivalueCallbacks::afterBuild($element, $form_state));
    $this->assertSame([], $form_state->getStorage());
    $this->assertSame(['text' => 'a'], $form_state->getValues());
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
   * Append without a widget stores no item.
   */
  #[DataProvider('triggeringElementProvider')]
  public function testAppendWithoutWidget(?array $button): void {
    $form_state = new FormState();
    $form_state->setTriggeringElement($button);

    MultivalueCallbacks::appendSubmit(['items' => ['#type' => 'textfield']], $form_state);

    $this->assertSame([], $form_state->getStorage());
    $this->assertSame([], MultivalueCallbacks::appendedWidget([], $form_state));
  }

  /**
   * Remove without a Remove button, or without its widget, stores nothing.
   */
  #[DataProvider('triggeringElementProvider')]
  public function testRemoveWithoutWidget(?array $button): void {
    $form_state = new FormState();
    $form_state->setTriggeringElement($button === NULL ? NULL : $button + ['#delta' => 0]);

    MultivalueCallbacks::removeSubmit([], $form_state);

    $this->assertSame([], $form_state->getStorage());
    $this->assertSame($button !== NULL, $form_state->isRebuilding());
  }

  /**
   * Provides triggering elements that point at no widget.
   *
   * @return array
   *   The test cases.
   */
  public static function triggeringElementProvider(): array {
    return [
      'no triggering element' => [NULL],
      'no array parents' => [['#type' => 'submit']],
      'parents outside the form' => [['#type' => 'submit', '#array_parents' => ['gone', 0, 'remove_one']]],
      'parents that are not a list' => [['#type' => 'submit', '#array_parents' => 'items']],
    ];
  }

  /**
   * Remove leaves the list alone for a delta the widget does not hold.
   */
  public function testRemoveUnknownDelta(): void {
    $form_state = new FormState();
    WidgetState::store($form_state, WidgetState::keys(['items'], 'items'), ['items_indices' => [0, 1]]);
    $widget = ['#max_delta' => 1, '#prop_parents' => ['items'], '#prop_name' => 'items'];
    $form_state->setTriggeringElement(['#delta' => 7, '#array_parents' => ['items', 7, 'remove_one']]);

    MultivalueCallbacks::removeSubmit(['items' => $widget], $form_state);

    $this->assertSame(['items_indices' => [0, 1]], WidgetState::stored($form_state, WidgetState::keys(['items'], 'items')));
    $this->assertTrue($form_state->isRebuilding());
  }

  /**
   * A checkboxes element without a value or options submits an empty list.
   */
  public function testCheckboxesWithoutOptions(): void {
    $form_state = new FormState();
    $element = ['#parents' => ['picks']];

    EnumElements::validateCheckboxes($element, $form_state);

    $this->assertSame(['picks' => []], $form_state->getValues());
  }

  /**
   * Validation skips a container without a schema in both placements.
   */
  public function testValidationWithoutSchema(): void {
    $form_state = (new FormState())->setValues(['a' => '1', 'form_id' => 'x']);
    $element = ['#parents' => []];

    SchemaValidationBridge::elementValidate($element, $form_state);
    SchemaValidationBridge::formValidate($element, $form_state);
    (new SchemaValidationBridge())->validateForm(['#json_schema' => 'not an object'], $form_state);

    $this->assertSame(['a' => '1', 'form_id' => 'x'], $form_state->getValues());
    $this->assertSame([], $form_state->getErrors());
  }

  /**
   * A widget nobody stored state for reads as nothing.
   */
  public function testMissingWidgetState(): void {
    $this->assertNull(WidgetItems::read(new FormState(), ['items'], 'host'));
  }

}
