<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Component\Utility\NestedArray;

/**
 * Names the buttons of multivalue widgets.
 *
 * FormBuilder finds the button that submitted a form by its name and value,
 * so two buttons with one name act on whichever widget comes first. Two
 * naming schemes exist:
 *
 * - Legacy names join the schema path with hyphens and underscores, which
 *   property names may also hold: "tag-list" and "tag_list" share an Append
 *   name, and ["a-b"] and ["a", "b"] share a Remove name. Forms built by
 *   earlier releases use them, so they stay wherever they are unique.
 * - Escaped names cannot collide; see escaped(). Buttons whose legacy names
 *   collide get one, and inside a namespaced container every button does,
 *   so that two containers in one form keep apart.
 *
 * @internal
 */
final class ButtonNames {

  /**
   * The ending of an escaped Append name.
   */
  private const APPEND_ENDING = ':add_more';

  /**
   * The ending of an escaped Remove name.
   */
  private const REMOVE_ENDING = ':remove';

  /**
   * Builds the name of an Append button.
   *
   * @param list<int|string> $parents
   *   The schema path of the widget.
   * @param string|null $namespace
   *   The namespace of the generated container, if it has one.
   *
   * @return string
   *   The name.
   */
  public static function append(array $parents, ?string $namespace = NULL): string {
    if ($namespace !== NULL) {
      return self::escaped($parents, self::APPEND_ENDING, $namespace);
    }
    return strtr(implode('-', $parents), '-', '_') . '_add_more';
  }

  /**
   * Builds the name of a Remove button.
   *
   * @param list<int|string> $parents
   *   The schema path of the item, ending with its delta.
   * @param string|null $namespace
   *   The namespace of the generated container, if it has one.
   *
   * @return string
   *   The name.
   */
  public static function remove(array $parents, ?string $namespace = NULL): string {
    if ($namespace !== NULL) {
      return self::escaped($parents, self::REMOVE_ENDING, $namespace);
    }
    $delta = array_pop($parents);
    return sprintf('%s-%d-remove', implode('-', $parents), $delta);
  }

  /**
   * Renames the buttons of a generated tree whose names collide.
   *
   * Of two buttons that share a name, the one whose path holds a joiner is
   * renamed, so a plain path keeps the name it always had.
   *
   * @param array<int|string, mixed> $root
   *   The generated root container.
   *
   * @return array<int|string, mixed>
   *   The tree, with every button name unique.
   */
  public static function deduplicate(array $root): array {
    $buttons = WidgetButtons::in($root);
    $counts = array_count_values(array_map(static fn (WidgetButton $button): string => $button->name, $buttons));
    $renamed = array_filter(
      $buttons,
      static fn (WidgetButton $button): bool => $counts[$button->name] > 1 && self::hasJoiner($button)
    );
    return array_reduce(
      $renamed,
      static function (array $root, WidgetButton $button): array {
        $ending = $button->removes ? self::REMOVE_ENDING : self::APPEND_ENDING;
        NestedArray::setValue($root, [...$button->path, '#name'], self::escaped($button->parents, $ending));
        return $root;
      },
      $root
    );
  }

  /**
   * Decides whether another path could share a button's legacy name.
   *
   * Legacy Remove names join segments with hyphens, and legacy Append names
   * turn hyphens into the underscores that join them. Only a segment that
   * holds one of these joiners can make two paths give one name.
   *
   * @param \SchemaForms\Drupal\WidgetButton $button
   *   The button.
   *
   * @return bool
   *   TRUE when a segment of the button's path holds a joiner.
   */
  private static function hasJoiner(WidgetButton $button): bool {
    $joiners = $button->removes ? '-' : '-_';
    $joined = array_filter(
      $button->parents,
      static fn (string $segment): bool => strpbrk($segment, $joiners) !== FALSE
    );
    return $joined !== [];
  }

  /**
   * Builds a name no other path, namespace or button kind maps to.
   *
   * Escaping leaves only letters, digits and three-character escapes that
   * start with ":" followed by two hexadecimal digits. Hence:
   * - a hyphen never appears inside a segment, so it only joins segments;
   * - "::" never appears inside an escaped part, so it only ends the
   *   namespace;
   * - the endings cannot be escapes, because "r" is not a hexadecimal digit
   *   and escaped text holds no "_", so they only tell the kinds apart.
   * Each part can thus be read back, and different inputs give different
   * names. Legacy names end in "-remove" or "_add_more" instead, so the two
   * schemes never meet either.
   *
   * @param array<int|string> $parents
   *   The schema path.
   * @param string $ending
   *   The ending of the button kind.
   * @param string|null $namespace
   *   The namespace of the generated container, if it has one.
   *
   * @return string
   *   The name.
   */
  private static function escaped(array $parents, string $ending, ?string $namespace = NULL): string {
    $segments = array_map(static fn (int|string $segment): string => self::escape((string) $segment), $parents);
    $prefix = $namespace === NULL ? '' : self::escape($namespace) . '::';
    return $prefix . implode('-', $segments) . $ending;
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
