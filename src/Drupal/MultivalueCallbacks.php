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
   *   The widget.
   */
  public static function process(array $element): array {
    $button = is_array($element[RenderKeys::APPEND_BUTTON] ?? NULL) ? $element[RenderKeys::APPEND_BUTTON] : [];
    $button['#limit_validation_errors'] = [$element['#parents'] ?? []];
    $element[RenderKeys::APPEND_BUTTON] = $button;
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
   *   The widget.
   */
  public static function afterBuild(array $element, FormStateInterface $form_state): array {
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
    // The widget is two levels up from the button: widget, item, button.
    $location = WidgetLocation::ofWidget(self::widget($form, $button, 2));
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
    return WidgetAjaxResponse::removed(self::widget($form, $button, 2), $delta);
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
    $widget = self::widget($form, self::triggeringButton($form_state), 1);
    $location = WidgetLocation::ofWidget($widget);
    $storage = new WidgetStateStorage($form_state);
    $state = $storage->load($location);
    $form_state->setRebuild();
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
    $widget = self::widget($form, self::triggeringButton($form_state), 1);
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
   * @param array<int|string, mixed> $form
   *   The form.
   * @param array<int|string, mixed> $button
   *   The button that submitted the form.
   * @param int $levels
   *   How many levels the widget sits above the button.
   *
   * @return array<int|string, mixed>
   *   The widget, or an empty array when the form holds none there.
   */
  private static function widget(array $form, array $button, int $levels): array {
    $array_parents = $button['#array_parents'] ?? [];
    $widget = NestedArray::getValue($form, array_slice(is_array($array_parents) ? $array_parents : [], 0, -$levels));
    return is_array($widget) ? $widget : [];
  }

}
