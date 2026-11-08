<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\Render\Element;

/**
 * Pairs generated elements with the paths of their values.
 *
 * Cleaning compacts multivalue items into a list, while their form deltas
 * keep the gaps that removals leave. Every step that writes into the cleaned
 * value, or reads errors about it, goes through these paths so that item
 * positions agree.
 *
 * @internal
 */
final class ElementDataPaths {

  /**
   * Lists every element of a generated tree with the path of its value.
   *
   * @param array<int|string, mixed> $root
   *   The generated root container.
   *
   * @return list<\SchemaForms\Drupal\ElementDataPath>
   *   The root and its descendants in form order. Buttons and item containers
   *   hold no value and are left out.
   */
  public static function all(array $root): array {
    return self::collect($root, [], static fn (array $element): bool => FALSE);
  }

  /**
   * Lists the outermost elements of a generated tree that match a condition.
   *
   * @param array<int|string, mixed> $root
   *   The generated root container.
   * @param callable(array<int|string, mixed>): bool $matches
   *   Decides whether an element matches.
   *
   * @return list<\SchemaForms\Drupal\ElementDataPath>
   *   The matching elements in form order, without their descendants.
   */
  public static function outermost(array $root, callable $matches): array {
    return array_values(array_filter(
      self::collect($root, [], $matches),
      static fn (ElementDataPath $pair): bool => $matches($pair->element)
    ));
  }

  /**
   * Walks an element and its descendants.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param list<int|string> $path
   *   The path of the element's value.
   * @param callable(array<int|string, mixed>): bool $stop
   *   Decides whether to leave out an element's descendants.
   *
   * @return list<\SchemaForms\Drupal\ElementDataPath>
   *   The element followed by its descendants.
   */
  private static function collect(array $element, array $path, callable $stop): array {
    $pair = new ElementDataPath($path, $element);
    if ($stop($element)) {
      return [$pair];
    }
    $descendants = array_map(
      static fn (ElementDataPath $child): array => self::collect($child->element, $child->path, $stop),
      self::children($element, $path)
    );
    return [$pair, ...array_merge(...$descendants)];
  }

  /**
   * Lists the children of an element that hold a value.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param list<int|string> $path
   *   The path of the element's value.
   *
   * @return list<\SchemaForms\Drupal\ElementDataPath>
   *   The children with the paths of their values.
   */
  private static function children(array $element, array $path): array {
    if (MultivalueWidget::is($element)) {
      $items = array_values(MultivalueWidget::storedItems($element));
      return array_map(
        static fn (int $position, array $item): ElementDataPath => new ElementDataPath([...$path, $position], $item),
        array_keys($items),
        $items
      );
    }
    $keys = array_filter(
      Element::children($element),
      static fn (int|string $key): bool => !MultivalueWidget::isButton($element[$key])
    );
    return array_map(
      static fn (int|string $key): ElementDataPath => new ElementDataPath([...$path, $key], $element[$key]),
      array_values($keys)
    );
  }

}
