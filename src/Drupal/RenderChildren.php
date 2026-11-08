<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\Render\Element;

/**
 * Reads the child elements of a render array.
 *
 * @internal
 */
final class RenderChildren {

  /**
   * Lists the keys of the child elements.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   *
   * @return list<int|string>
   *   The keys, in render order.
   */
  public static function keys(array $element): array {
    return array_values(array_filter(
      Element::children($element),
      static fn (mixed $key): bool => is_int($key) || is_string($key)
    ));
  }

  /**
   * Gets the child elements.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   *
   * @return array<int|string, array<int|string, mixed>>
   *   The children that are render arrays, keyed as in the element.
   */
  public static function of(array $element): array {
    $keys = self::keys($element);
    $children = array_combine($keys, array_map(static fn (int|string $key): mixed => $element[$key], $keys));
    return array_filter($children, 'is_array');
  }

}
