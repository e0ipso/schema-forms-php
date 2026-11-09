<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

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
   * @return list<array{list<int|string>, array<int|string, mixed>}>
   *   The path and element of the root and its descendants, in form order,
   *   without buttons and item containers.
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
   * @return list<array{list<int|string>, array<int|string, mixed>}>
   *   The path and element of each match, in form order.
   */
  public static function outermost(array $root, callable $matches): array {
    return array_values(array_filter(
      self::collect($root, [], $matches),
      static fn (array $pair): bool => $matches($pair[1])
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
   * @return list<array{list<int|string>, array<int|string, mixed>}>
   *   The element followed by its descendants.
   */
  private static function collect(array $element, array $path, callable $stop): array {
    if ($stop($element)) {
      return [[$path, $element]];
    }
    $descendants = array_map(
      static fn (array $child): array => self::collect($child[1], $child[0], $stop),
      self::children($element, $path)
    );
    return [[$path, $element], ...array_merge(...$descendants)];
  }

  /**
   * Lists the children of an element that hold a value.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param list<int|string> $path
   *   The path of the element's value.
   *
   * @return list<array{list<int|string>, array<int|string, mixed>}>
   *   The children with the paths of their values. Multivalue items count by
   *   their position in the stored list, not by their form delta.
   */
  private static function children(array $element, array $path): array {
    if (MultivalueWidget::is($element)) {
      $items = array_values(MultivalueWidget::storedItems($element));
      return array_map(
        static fn (int $position, array $item): array => [[...$path, $position], $item],
        array_keys($items),
        $items
      );
    }
    $children = array_filter(
      RenderChildren::of($element),
      static fn (array $child): bool => !MultivalueWidget::isButton($child)
    );
    return array_map(
      static fn (int|string $key, array $child): array => [[...$path, $key], $child],
      array_keys($children),
      array_values($children)
    );
  }

}
