<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

/**
 * Reads the structure of a generated multivalue widget.
 *
 * The widget is a details element with a '#max_delta' property. Each child
 * that is not a button is an item container holding the item control under
 * 'removable_element' and its remove button. Schema property names never
 * appear at either level, so these markers cannot collide with them.
 *
 * @internal
 *
 * @see \SchemaForms\Drupal\MultivalueWidgetBuilder
 */
final class MultivalueWidget {

  /**
   * Decides whether an element is a multivalue widget.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   *
   * @return bool
   *   TRUE for a multivalue widget.
   */
  public static function is(array $element): bool {
    return array_key_exists(RenderKeys::MAX_DELTA, $element);
  }

  /**
   * Decides whether an element is a button, which submits no data.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   *
   * @return bool
   *   TRUE for a submit or plain button.
   */
  public static function isButton(array $element): bool {
    return in_array($element['#type'] ?? '', ['submit', 'button'], TRUE);
  }

  /**
   * Gets the item control at a form delta.
   *
   * @param array<int|string, mixed> $widget
   *   The multivalue widget.
   * @param int|string $delta
   *   The form delta.
   *
   * @return array<int|string, mixed>|null
   *   The item control, or NULL when the widget has no item there.
   */
  public static function item(array $widget, int|string $delta): ?array {
    $container = $widget[$delta] ?? NULL;
    if (!is_array($container) || self::isButton($container) || !is_array($container[RenderKeys::ITEM] ?? NULL)) {
      return NULL;
    }
    return $container[RenderKeys::ITEM];
  }

  /**
   * Lists the item controls whose values the stored list keeps.
   *
   * @param array<int|string, mixed> $widget
   *   The multivalue widget.
   *
   * @return array<int|string, array<int|string, mixed>>
   *   The item controls in form order, keyed by form delta. The position of
   *   each one is its index in the stored list.
   */
  public static function storedItems(array $widget): array {
    $deltas = RenderChildren::keys($widget);
    $items = array_map(
      static fn (int|string $delta): ?array => self::item($widget, $delta),
      array_combine($deltas, $deltas)
    );
    return array_filter(
      $items,
      static fn (?array $item): bool => $item !== NULL && !self::isNeverFilled($item)
    );
  }

  /**
   * Decides whether an item was appended and left without a value.
   *
   * Only a list item can submit nothing at all: every other control submits
   * at least an empty string or an unchecked state. A list item with no
   * sub-items that showed no saved value or default was appended and never
   * filled, so it is not stored. A saved empty or NULL list is kept.
   *
   * @param array<int|string, mixed> $item
   *   The item control.
   *
   * @return bool
   *   TRUE when the item is left out of the stored list.
   */
  public static function isNeverFilled(array $item): bool {
    return self::is($item)
      && $item[RenderKeys::MAX_DELTA] === NULL
      && empty($item[RenderKeys::SHOWED_VALUE])
      && empty($item[RenderKeys::SAVED_NULL]);
  }

}
