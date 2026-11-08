<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Render\Element;

/**
 * Names the buttons of multivalue widgets.
 *
 * FormBuilder finds the button that submitted a form by its name and value,
 * so two buttons with one name act on whichever widget comes first. The
 * names join the schema path of the widget, which hyphens and underscores
 * inside property names can make ambiguous: "tag-list" and "tag_list" share
 * an Append name, and ["a-b"] and ["a", "b"] share a Remove name. Only the
 * buttons whose names collide get a different, unambiguous name, so names
 * that worked before keep working.
 *
 * @internal
 */
final class ButtonNames {

  /**
   * Builds the name of an Append button.
   *
   * @param list<int|string> $parents
   *   The schema path of the widget.
   *
   * @return string
   *   The name.
   */
  public static function append(array $parents): string {
    return strtr(implode('-', $parents), '-', '_') . '_add_more';
  }

  /**
   * Builds the name of a Remove button.
   *
   * @param list<int|string> $parents
   *   The schema path of the item, ending with its delta.
   *
   * @return string
   *   The name.
   */
  public static function remove(array $parents): string {
    $delta = array_pop($parents);
    return sprintf('%s-%d-remove', implode('-', $parents), $delta);
  }

  /**
   * Renames the buttons of a generated tree whose names collide.
   *
   * @param array<int|string, mixed> $root
   *   The generated root container.
   *
   * @return array<int|string, mixed>
   *   The tree, with every button name unique.
   */
  public static function deduplicate(array $root): array {
    $buttons = self::buttons($root, []);
    $counts = array_count_values(array_map(static fn (WidgetButton $button): string => $button->name, $buttons));
    $renamed = array_filter(
      $buttons,
      static fn (WidgetButton $button): bool => $counts[$button->name] > 1 && !self::isPlain($button)
    );
    return array_reduce(
      $renamed,
      static function (array $root, WidgetButton $button): array {
        NestedArray::setValue($root, [...$button->path, '#name'], self::unambiguous($button));
        return $root;
      },
      $root
    );
  }

  /**
   * Lists the multivalue buttons under an element.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param list<int|string> $path
   *   The keys from the root to the element.
   *
   * @return list<\SchemaForms\Drupal\WidgetButton>
   *   The buttons.
   */
  private static function buttons(array $element, array $path): array {
    $own = array_filter([self::appendButton($element, $path), self::removeButton($element, $path)]);
    $children = array_map(
      static fn (int|string $key): array => is_array($element[$key]) ? self::buttons($element[$key], [...$path, $key]) : [],
      Element::children($element)
    );
    return [...array_values($own), ...array_merge(...$children)];
  }

  /**
   * Finds the Append button of a widget.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param list<int|string> $path
   *   The keys from the root to the element.
   *
   * @return \SchemaForms\Drupal\WidgetButton|null
   *   The button, or NULL when the element is not a widget.
   */
  private static function appendButton(array $element, array $path): ?WidgetButton {
    $name = $element['add_more']['#name'] ?? NULL;
    if (!MultivalueWidget::is($element) || !is_string($name)) {
      return NULL;
    }
    return new WidgetButton([...$path, 'add_more'], self::segments($element['#prop_parents'] ?? []), $name, FALSE);
  }

  /**
   * Reads a Remove button.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param list<int|string> $path
   *   The keys from the root to the element.
   *
   * @return \SchemaForms\Drupal\WidgetButton|null
   *   The button, or NULL when the element is not a Remove button.
   */
  private static function removeButton(array $element, array $path): ?WidgetButton {
    $name = $element['#name'] ?? NULL;
    if (!MultivalueWidget::isButton($element) || !isset($element['#delta']) || !is_string($name)) {
      return NULL;
    }
    return new WidgetButton($path, self::segments($element['#prop_parents'] ?? []), $name, TRUE);
  }

  /**
   * Reads a schema path from a render property.
   *
   * @param mixed $parents
   *   The '#prop_parents' value.
   *
   * @return list<string>
   *   The path segments.
   */
  private static function segments(mixed $parents): array {
    $segments = is_array($parents) ? array_values($parents) : [];
    return array_map(static fn (mixed $segment): string => is_scalar($segment) ? (string) $segment : '', $segments);
  }

  /**
   * Decides whether a button's legacy name cannot match another widget's.
   *
   * Legacy Remove names join the path with hyphens, and legacy Append names
   * also turn hyphens into the underscores that join them. A path whose
   * segments hold none of these joiners maps to a name no other path has.
   *
   * @param \SchemaForms\Drupal\WidgetButton $button
   *   The button.
   *
   * @return bool
   *   TRUE when the legacy name is unambiguous.
   */
  private static function isPlain(WidgetButton $button): bool {
    $joiners = $button->removes ? '-' : '-_';
    $ambiguous = array_filter(
      $button->parents,
      static fn (string $segment): bool => strpbrk($segment, $joiners) !== FALSE
    );
    return $ambiguous === [];
  }

  /**
   * Builds a name no other path maps to.
   *
   * Every character outside [A-Za-z0-9] is escaped, so hyphens only join
   * segments. The ":remove" and ":add_more" endings never end a legacy name,
   * which ends in "-remove" or "_add_more".
   *
   * @param \SchemaForms\Drupal\WidgetButton $button
   *   The button.
   *
   * @return string
   *   The name.
   */
  private static function unambiguous(WidgetButton $button): string {
    $segments = array_map([self::class, 'escape'], $button->parents);
    return implode('-', $segments) . ($button->removes ? ':remove' : ':add_more');
  }

  /**
   * Escapes one path segment.
   *
   * @param string $segment
   *   The segment.
   *
   * @return string
   *   The segment with every byte outside [A-Za-z0-9] written as ":" and its
   *   two hexadecimal digits.
   */
  private static function escape(string $segment): string {
    return (string) preg_replace_callback(
      '/[^A-Za-z0-9]/',
      static fn (array $match): string => ':' . bin2hex($match[0]),
      $segment
    );
  }

}
