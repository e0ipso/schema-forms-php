<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Runs the Form API callbacks of multivalue widgets.
 *
 * The generated render arrays name the static methods of
 * FormGeneratorDrupal, which delegate here.
 *
 * @internal
 *
 * @see \SchemaForms\Drupal\MultivalueWidgetBuilder
 */
final class MultivalueCallbacks {

  /**
   * Limits Append validation to the widget's final form value path.
   *
   * @param array<int|string, mixed> $element
   *   The widget, with its Form API parents assigned.
   *
   * @return array<int|string, mixed>
   *   The widget. An element without an Append button is returned as it
   *   is, instead of gaining a button with no type.
   */
  public static function process(array $element): array {
    if (!is_array($element[RenderKeys::APPEND_BUTTON] ?? NULL)) {
      return $element;
    }
    $element[RenderKeys::APPEND_BUTTON]['#limit_validation_errors'] = [$element['#parents'] ?? []];
    return $element;
  }

  /**
   * Cleans the widget value and records where the widget was built.
   *
   * @param array<int|string, mixed> $element
   *   The widget.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<int|string, mixed>
   *   The widget. Any other element is returned untouched, so that no state
   *   is stored for a widget that does not exist.
   */
  public static function afterBuild(array $element, FormStateInterface $form_state): array {
    if (!MultivalueWidget::is($element)) {
      return $element;
    }
    // Each widget cleans its own value here, before any ancestor's after-build
    // runs, so the schema bridge and the submit handlers of buttons that skip
    // it both receive a plain item list.
    if ($form_state->isProcessingInput()) {
      $parents = is_array($element['#parents'] ?? NULL) ? $element['#parents'] : [];
      $form_state->setValue($parents, UserInputCleaner::cleanUserInput($form_state->getValue($parents), $element));
    }
    // Host code may read where the widget ended up in the form.
    $array_parents = is_array($element['#array_parents'] ?? NULL) ? $element['#array_parents'] : [];
    $storage = new WidgetStateStorage($form_state);
    $location = WidgetLocation::ofWidget($element);
    $storage->save($location, $storage->load($location)->withArrayParents($array_parents));
    return $element;
  }

  /**
   * Removes the item whose Remove button submitted the form.
   *
   * @param array<int|string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function removeSubmit(array $form, FormStateInterface $form_state): void {
    $button = self::triggeringButton($form_state);
    $delta = self::delta($button);
    if ($delta === NULL) {
      return;
    }
    $location = WidgetLocation::ofWidget(self::widget($form, $button));
    $storage = new WidgetStateStorage($form_state);
    $state = $storage->load($location);
    if (in_array($delta, $state->deltas(), TRUE)) {
      $storage->save($location, $state->withoutItem($delta));
    }
    $form_state->setRebuild();
  }

  /**
   * Responds to a removal with the rebuilt widget.
   *
   * The whole widget is replaced, so an Append button hidden at the item
   * limit comes back once an item is removed.
   *
   * @param array<int|string, mixed> $form
   *   The rebuilt form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response. It is empty when no Remove button triggered the request.
   */
  public static function removeAjax(array $form, FormStateInterface $form_state): AjaxResponse {
    $button = self::triggeringButton($form_state);
    $delta = self::delta($button);
    if ($delta === NULL) {
      return new AjaxResponse();
    }
    return WidgetAjaxResponse::removed(self::widget($form, $button), $delta);
  }

  /**
   * Adds an item to the widget whose Append button submitted the form.
   *
   * @param array<int|string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function appendSubmit(array $form, FormStateInterface $form_state): void {
    $widget = self::widget($form, self::triggeringButton($form_state));
    $form_state->setRebuild();
    // Without its widget, the item would go to a state no widget reads.
    if (!MultivalueWidget::is($widget)) {
      return;
    }
    $location = WidgetLocation::ofWidget($widget);
    $storage = new WidgetStateStorage($form_state);
    $state = $storage->load($location);
    $cardinality = $widget[RenderKeys::CARDINALITY] ?? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
    // A forced submission of a hidden button cannot exceed the limit either.
    if ($state->isFull(is_int($cardinality) ? $cardinality : FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)) {
      return;
    }
    $storage->save($location, $state->withAppendedItem());
  }

  /**
   * Responds to an appended item with the rebuilt widget.
   *
   * Finite widgets are replaced too, so a hidden Append button disappears.
   *
   * @param array<int|string, mixed> $form
   *   The rebuilt form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public static function appendAjax(array $form, FormStateInterface $form_state): AjaxResponse {
    return WidgetAjaxResponse::appended(self::appendedWidget($form, $form_state));
  }

  /**
   * Gets the rebuilt widget after an item was appended.
   *
   * @param array<int|string, mixed> $form
   *   The rebuilt form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<int|string, mixed>
   *   The widget, with the new item wrapped for the AJAX effect.
   */
  public static function appendedWidget(array $form, FormStateInterface $form_state): array {
    $widget = self::widget($form, self::triggeringButton($form_state));
    $delta = $widget[RenderKeys::MAX_DELTA] ?? NULL;
    if (!is_int($delta) || !is_array($widget[$delta] ?? NULL)) {
      return $widget;
    }
    $prefix = $widget[$delta]['#prefix'] ?? '';
    $suffix = $widget[$delta]['#suffix'] ?? '';
    $widget[$delta]['#prefix'] = '<div class="ajax-new-content">' . (is_string($prefix) ? $prefix : '');
    $widget[$delta]['#suffix'] = (is_string($suffix) ? $suffix : '') . '</div>';
    return $widget;
  }

  /**
   * Gets the button that submitted the form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<int|string, mixed>
   *   The button, or an empty array when no element submitted the form.
   */
  private static function triggeringButton(FormStateInterface $form_state): array {
    return $form_state->getTriggeringElement() ?? [];
  }

  /**
   * Reads the item delta of a Remove button.
   *
   * @param array<int|string, mixed> $button
   *   The button that submitted the form.
   *
   * @return int|null
   *   The delta, or NULL when the button is not a Remove button.
   */
  private static function delta(array $button): ?int {
    $delta = $button['#delta'] ?? NULL;
    return is_int($delta) ? $delta : NULL;
  }

  /**
   * Finds the widget a button belongs to.
   *
   * The button's nearest ancestor that is a multivalue widget owns it. Asking
   * the widget structure, instead of counting levels, keeps this right
   * wherever the builder places the buttons inside the widget.
   *
   * @param array<int|string, mixed> $form
   *   The form.
   * @param array<int|string, mixed> $button
   *   The button that submitted the form.
   *
   * @return array<int|string, mixed>
   *   The widget, or an empty array when no ancestor of the button is one.
   */
  private static function widget(array $form, array $button): array {
    $array_parents = is_array($button['#array_parents'] ?? NULL) ? array_values($button['#array_parents']) : [];
    $lengths = count($array_parents) > 1 ? range(count($array_parents) - 1, 1) : [];
    $widgets = array_filter(
      array_map(static fn (int $length): mixed => NestedArray::getValue($form, array_slice($array_parents, 0, $length)), $lengths),
      static fn (mixed $ancestor): bool => is_array($ancestor) && MultivalueWidget::is($ancestor)
    );
    $widget = reset($widgets);
    return is_array($widget) ? $widget : [];
  }

}
