<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

/**
 * Finds the Append and Remove buttons of a generated tree.
 *
 * @internal
 *
 * @see \SchemaForms\Drupal\ButtonNames
 */
final class WidgetButtons {

  /**
   * Lists the multivalue buttons under an element.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param list<int|string> $path
   *   The keys from the generated root to the element.
   *
   * @return list<\SchemaForms\Drupal\WidgetButton>
   *   The buttons, in form order.
   */
  public static function in(array $element, array $path = []): array {
    $own = array_filter([self::append($element, $path), self::remove($element, $path)]);
    $children = RenderChildren::of($element);
    $nested = array_map(
      static fn (array $child, int|string $key): array => self::in($child, [...$path, $key]),
      $children,
      array_keys($children)
    );
    return [...array_values($own), ...array_merge(...$nested)];
  }

  /**
   * Reads the Append button of a widget.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param list<int|string> $path
   *   The keys from the generated root to the element.
   *
   * @return \SchemaForms\Drupal\WidgetButton|null
   *   The button, or NULL when the element is not a widget.
   */
  private static function append(array $element, array $path): ?WidgetButton {
    $button = $element[RenderKeys::APPEND_BUTTON] ?? NULL;
    $name = is_array($button) ? ($button['#name'] ?? NULL) : NULL;
    if (!MultivalueWidget::is($element) || !is_string($name)) {
      return NULL;
    }
    return new WidgetButton([...$path, RenderKeys::APPEND_BUTTON], self::schemaPath($element), $name, FALSE);
  }

  /**
   * Reads a Remove button.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param list<int|string> $path
   *   The keys from the generated root to the element.
   *
   * @return \SchemaForms\Drupal\WidgetButton|null
   *   The button, or NULL when the element is not a Remove button.
   */
  private static function remove(array $element, array $path): ?WidgetButton {
    $name = $element['#name'] ?? NULL;
    if (!MultivalueWidget::isButton($element) || !isset($element['#delta']) || !is_string($name)) {
      return NULL;
    }
    return new WidgetButton($path, self::schemaPath($element), $name, TRUE);
  }

  /**
   * Reads the schema path an element records.
   *
   * @param array<int|string, mixed> $element
   *   The element, with its '#prop_parents'.
   *
   * @return list<string>
   *   The path segments.
   */
  private static function schemaPath(array $element): array {
    $parents = $element[RenderKeys::PROP_PARENTS] ?? [];
    $segments = is_array($parents) ? array_values($parents) : [];
    return array_map(static fn (mixed $segment): string => is_scalar($segment) ? (string) $segment : '', $segments);
  }

}
