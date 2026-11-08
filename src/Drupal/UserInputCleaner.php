<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Component\Render\MarkupInterface;

/**
 * Removes the form scaffolding from submitted values.
 *
 * Multivalue widgets nest each item under its remove button and add their
 * Append button's value. Cleaning turns those into the plain item list and
 * drops the NULL that a control left without a choice submits.
 *
 * @internal
 */
final class UserInputCleaner {

  /**
   * Cleans submitted values.
   *
   * @param mixed $data
   *   The submitted values.
   * @param array<int|string, mixed>|null $element
   *   The generated element the values belong to. It tells multivalue lists
   *   from objects and saved items from appended ones. Without it, lists are
   *   recognised by the button values the widget adds.
   *
   * @return mixed
   *   The clean values.
   */
  public static function cleanUserInput(mixed $data, ?array $element = NULL): mixed {
    return match (TRUE) {
      !is_array($data) => $data,
      $element === NULL => self::cleanWithoutElement($data),
      MultivalueWidget::is($element) => self::cleanItems($data, $element),
      default => self::cleanProperties($data, $element),
    };
  }

  /**
   * Cleans the value of a descendant element.
   *
   * @param mixed $data
   *   The descendant's value.
   * @param array<int|string, mixed>|null $element
   *   The descendant element, if there is one.
   *
   * @return mixed
   *   The clean value.
   */
  private static function cleanDescendant(mixed $data, ?array $element): mixed {
    // Each multivalue widget cleans its own value in #after_build, which runs
    // before any ancestor's, so its value is already a clean list here.
    if ($element !== NULL && MultivalueWidget::is($element)) {
      return $data;
    }
    return self::cleanUserInput($data, $element);
  }

  /**
   * Turns a multivalue widget's value into its stored item list.
   *
   * @param array<int|string, mixed> $data
   *   The widget value, keyed by form delta.
   * @param array<int|string, mixed> $widget
   *   The widget.
   *
   * @return list<mixed>
   *   The item values.
   */
  private static function cleanItems(array $data, array $widget): array {
    $entries = array_filter(
      $data,
      static fn (int|string $key): bool => !MultivalueWidget::isButton(self::child($widget, $key) ?? []),
      ARRAY_FILTER_USE_KEY
    );
    $items = array_map(
      static fn (int|string $key): ?array => MultivalueWidget::item($widget, $key),
      array_keys($entries)
    );
    $values = array_map([self::class, 'itemValue'], array_values($entries), $items);
    $stored = array_filter(
      array_keys($values),
      static fn (int $index): bool => self::isStored($values[$index], $items[$index])
    );
    return array_values(array_map(
      static fn (int $index): mixed => self::cleanDescendant($values[$index], $items[$index]),
      $stored
    ));
  }

  /**
   * Takes an item's value out of its container.
   *
   * @param mixed $entry
   *   The submitted entry for one form delta.
   * @param array<int|string, mixed>|null $item
   *   The item control, if the widget has one at that delta.
   *
   * @return mixed
   *   The item value.
   */
  private static function itemValue(mixed $entry, ?array $item): mixed {
    if ($item === NULL && !self::isItemContainer($entry)) {
      return $entry;
    }
    return is_array($entry) ? ($entry['removable_element'] ?? NULL) : NULL;
  }

  /**
   * Decides whether an item belongs in the stored list.
   *
   * @param mixed $value
   *   The item value.
   * @param array<int|string, mixed>|null $item
   *   The item control, if there is one.
   *
   * @return bool
   *   TRUE when the item is stored.
   */
  private static function isStored(mixed $value, ?array $item): bool {
    // Without its control, a saved item and an appended, unfilled one look
    // alike. One that submitted nothing at all is taken to be unfilled.
    if ($item === NULL) {
      return $value !== [];
    }
    return !MultivalueWidget::isNeverFilled($item);
  }

  /**
   * Gets the child element under a key.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param int|string $key
   *   The child key.
   *
   * @return array<int|string, mixed>|null
   *   The child element, or NULL when there is none.
   */
  private static function child(array $element, int|string $key): ?array {
    return is_array($element[$key] ?? NULL) ? $element[$key] : NULL;
  }

  /**
   * Cleans the values under named keys.
   *
   * @param array<int|string, mixed> $data
   *   The values, keyed by property name.
   * @param array<int|string, mixed> $element
   *   The element that holds them.
   *
   * @return array<int|string, mixed>
   *   The clean values.
   */
  private static function cleanProperties(array $data, array $element): array {
    $keys = array_keys($data);
    $children = array_map(static fn (int|string $key): ?array => self::child($element, $key), $keys);
    $values = array_combine($keys, array_map([self::class, 'cleanDescendant'], $data, $children));
    // A control left without a choice submits NULL, and the property is then
    // absent, as it is when a JSON object leaves it out. A NULL inside a
    // single control's list value has no element and keeps its position.
    return array_filter(
      $values,
      static fn (mixed $value, int|string $key): bool => $value !== NULL || self::child($element, $key) === NULL,
      ARRAY_FILTER_USE_BOTH
    );
  }

  /**
   * Cleans values whose element is unknown.
   *
   * @param array<int|string, mixed> $data
   *   The values.
   *
   * @return array<int|string, mixed>
   *   The clean values.
   */
  private static function cleanWithoutElement(array $data): array {
    $has_append_button = ($data['add_more'] ?? NULL) instanceof MarkupInterface;
    $values = $has_append_button ? array_diff_key($data, ['add_more' => TRUE]) : $data;
    $containers = array_filter($values, [self::class, 'isItemContainer']);
    $is_widget = $has_append_button || ($values !== [] && count($containers) === count($values));
    if ($is_widget) {
      return self::cleanItemsWithoutElement($values);
    }
    if (array_is_list($values)) {
      return array_map([self::class, 'cleanUserInput'], $values);
    }
    return array_map([self::class, 'cleanUserInput'], array_filter($values, static fn (mixed $value): bool => $value !== NULL));
  }

  /**
   * Turns the items of a multivalue value into a list, without its widget.
   *
   * @param array<int|string, mixed> $items
   *   The item containers, keyed by form delta.
   *
   * @return list<mixed>
   *   The item values.
   */
  private static function cleanItemsWithoutElement(array $items): array {
    $values = array_map(static fn (mixed $entry): mixed => self::itemValue($entry, NULL), array_values($items));
    $stored = array_filter($values, static fn (mixed $value): bool => self::isStored($value, NULL));
    return array_map([self::class, 'cleanUserInput'], array_values($stored));
  }

  /**
   * Decides whether a value is an item nested under its remove button.
   *
   * @param mixed $item
   *   The value.
   *
   * @return bool
   *   TRUE for an item container.
   */
  private static function isItemContainer(mixed $item): bool {
    return is_array($item)
      && array_key_exists('removable_element', $item)
      && ($item['remove_one'] ?? NULL) instanceof MarkupInterface;
  }

}
