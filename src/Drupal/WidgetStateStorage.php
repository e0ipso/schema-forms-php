<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;

/**
 * Keeps the state of multivalue widgets in form state storage.
 *
 * The state of a widget is placed at
 * ['prop_storage', '#parents', ...$parents, '#props', $prop_name], so that a
 * property name never clashes with a segment of another widget's parents.
 *
 * @internal
 */
final class WidgetStateStorage {

  /**
   * Reads the stored state of a widget.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<mixed> $parents
   *   The schema path of the widget.
   * @param string $prop_name
   *   The widget's property name.
   *
   * @return mixed
   *   The stored array, or NULL when nothing is stored.
   */
  public static function get(FormStateInterface $form_state, array $parents, string $prop_name): mixed {
    return NestedArray::getValue($form_state->getStorage(), self::parents($parents, $prop_name));
  }

  /**
   * Stores the state of a widget.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<mixed> $parents
   *   The schema path of the widget.
   * @param string $prop_name
   *   The widget's property name.
   * @param array<mixed> $state
   *   The array to store.
   */
  public static function set(FormStateInterface $form_state, array $parents, string $prop_name, array $state): void {
    NestedArray::setValue($form_state->getStorage(), self::parents($parents, $prop_name), $state);
  }

  /**
   * Reads the typed state of a built widget.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<int|string, mixed> $widget
   *   The widget element.
   *
   * @return \SchemaForms\Drupal\WidgetState
   *   The state.
   */
  public static function load(FormStateInterface $form_state, array $widget): WidgetState {
    return WidgetState::fromStorage(self::get($form_state, ...self::location($widget)));
  }

  /**
   * Stores the typed state of a built widget.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<int|string, mixed> $widget
   *   The widget element.
   * @param \SchemaForms\Drupal\WidgetState $state
   *   The state.
   */
  public static function save(FormStateInterface $form_state, array $widget, WidgetState $state): void {
    [$parents, $prop_name] = self::location($widget);
    self::set($form_state, $parents, $prop_name, $state->toStorage());
  }

  /**
   * Reads where a built widget keeps its state.
   *
   * @param array<int|string, mixed> $widget
   *   The widget element.
   *
   * @return array{0: array<mixed>, 1: string}
   *   The schema path and the property name.
   */
  private static function location(array $widget): array {
    $parents = $widget[RenderKeys::PROP_PARENTS] ?? [];
    $prop_name = $widget[RenderKeys::PROP_NAME] ?? '';
    return [is_array($parents) ? $parents : [], is_scalar($prop_name) ? (string) $prop_name : ''];
  }

  /**
   * Builds the storage location of a widget's state.
   *
   * @param array<mixed> $parents
   *   The schema path of the widget.
   * @param string $prop_name
   *   The widget's property name.
   *
   * @return array<mixed>
   *   The keys into form state storage.
   */
  private static function parents(array $parents, string $prop_name): array {
    return ['prop_storage', '#parents', ...$parents, '#props', $prop_name];
  }

}
