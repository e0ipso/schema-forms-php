<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Render\Markup;

/**
 * Marks the root properties the schema requires.
 *
 * @internal
 */
final class RequiredMarker {

  /**
   * Sets Form API #required on the children of the generated root.
   *
   * @param array<int|string, mixed> $root
   *   The generated root container, with its schema.
   *
   * @return array<int|string, mixed>
   *   The container.
   */
  public static function apply(array $root): array {
    $schema = $root[RenderKeys::SCHEMA] ?? NULL;
    $required = is_object($schema) && is_array($schema->required ?? NULL) ? $schema->required : [];
    $keys = array_filter(
      array_keys($root),
      static fn (int|string $key): bool => !str_starts_with((string) $key, '#')
        && is_array($root[$key])
        && ($root[$key]['#input'] ?? NULL) !== FALSE
    );
    return array_reduce(
      $keys,
      static function (array $root, int|string $key) use ($required): array {
        $root[$key] = is_array($root[$key]) ? self::mark($root[$key], in_array($key, $required)) : $root[$key];
        return $root;
      },
      $root
    );
  }

  /**
   * Marks one control.
   *
   * @param array<int|string, mixed> $element
   *   The control.
   * @param bool $required
   *   Whether the schema requires the property.
   *
   * @return array<int|string, mixed>
   *   The control. Non-input controls, such as constants, keep no #required:
   *   schema validation still enforces the key.
   */
  private static function mark(array $element, bool $required): array {
    $type = $element['#type'] ?? '';
    if (!in_array($type, ['checkbox', 'checkboxes'], TRUE)) {
      $element['#required'] = $required;
      return $element;
    }
    // Schema required means presence, so unchecked values remain valid.
    $element['#required'] = FALSE;
    if (!$required) {
      return $element;
    }
    $attributes = is_array($element['#label_attributes'] ?? NULL) ? $element['#label_attributes'] : [];
    $classes = is_array($attributes['class'] ?? NULL) ? $attributes['class'] : [];
    $attributes['class'] = [...$classes, 'form-required'];
    $element['#label_attributes'] = $attributes;
    // Composite checkboxes render their title inside a legend instead.
    if ($type === 'checkboxes') {
      $title = $element['#title'] ?? '';
      $element['#title'] = Markup::create('<span class="form-required">' . ($title instanceof MarkupInterface || is_string($title) ? $title : '') . '</span>');
    }
    return $element;
  }

}
