<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\Render\Element\RenderCallbackInterface;

/**
 * Marks the properties the schema requires.
 *
 * Root properties get Form API #required, except checkboxes: schema required
 * means the key must exist, so FALSE and an empty selection stay valid.
 * Nested properties only show the marker, because Form API would reject a
 * blank value the schema accepts. Schema validation enforces both.
 *
 * A marker that Form API does not enforce is visual only, so the control
 * also gets aria-required. The HTML required attribute is not an option:
 * the browser would refuse to submit the blank value the schema accepts.
 *
 * Generated render arrays name afterBuild() and preRender(), and cached forms
 * keep those names, so the class and both methods keep their names.
 *
 * @internal
 */
final class RequiredMarker implements RenderCallbackInterface {

  /**
   * Element types that show their title in a fieldset legend.
   *
   * Core reads #label_attributes only for labels, and shows the legend marker
   * only when #required is set.
   */
  private const GROUP_TYPES = ['checkboxes', 'radios'];

  /**
   * Element types that collect no value, so Form API never enforces them.
   */
  private const CONTAINER_TYPES = ['details'];

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
   * Shows the marker on a required property inside an object.
   *
   * @param array<int|string, mixed> $element
   *   The property's control.
   * @param bool $required
   *   Whether the object schema requires the property.
   *
   * @return array<int|string, mixed>
   *   The control. Non-input controls, such as constants, get no marker.
   */
  public static function nested(array $element, bool $required): array {
    if (!$required || ($element['#input'] ?? NULL) === FALSE) {
      return $element;
    }
    return self::showMarker($element);
  }

  /**
   * Adds the marker callback once the element has its default properties.
   *
   * Setting #pre_render on the element itself would replace the element
   * type's own callbacks, which build the fieldset around the options.
   *
   * @param array<int|string, mixed> $element
   *   The built element.
   *
   * @return array<int|string, mixed>
   *   The element.
   */
  public static function afterBuild(array $element): array {
    $pre_render = is_array($element['#pre_render'] ?? NULL) ? $element['#pre_render'] : [];
    $element['#pre_render'] = [...$pre_render, [self::class, 'preRender']];
    return $element;
  }

  /**
   * Lets core render its own required marker.
   *
   * Validation is over when an element renders, so #required set here only
   * changes the markup. Attributes set here reach only the fieldset, because
   * the options copied the group's attributes when they were built. A radio
   * group tells assistive technology it is required through the radiogroup
   * role; ARIA has no required state for a group of checkboxes, so those
   * keep the visible marker alone.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   *
   * @return array<int|string, mixed>
   *   The element.
   */
  public static function preRender(array $element): array {
    $element['#required'] = TRUE;
    if (($element['#type'] ?? NULL) !== EnumElements::RADIOS) {
      return $element;
    }
    return self::withAttributes($element, ['role' => 'radiogroup', 'aria-required' => 'true']);
  }

  /**
   * Marks one root control.
   *
   * @param array<int|string, mixed> $element
   *   The control.
   * @param bool $required
   *   Whether the schema requires the property.
   *
   * @return array<int|string, mixed>
   *   The control.
   */
  private static function mark(array $element, bool $required): array {
    if (!in_array($element['#type'] ?? '', ['checkbox', 'checkboxes'], TRUE)) {
      $element['#required'] = $required;
      return $element;
    }
    $element['#required'] = FALSE;
    return $required ? self::showMarker($element) : $element;
  }

  /**
   * Shows the required marker without Form API enforcing it.
   *
   * @param array<int|string, mixed> $element
   *   The control.
   *
   * @return array<int|string, mixed>
   *   The control.
   */
  private static function showMarker(array $element): array {
    $type = $element['#type'] ?? '';
    if (in_array($type, self::CONTAINER_TYPES, TRUE)) {
      $element['#required'] = TRUE;
      return $element;
    }
    if (in_array($type, self::GROUP_TYPES, TRUE)) {
      $after_build = is_array($element['#after_build'] ?? NULL) ? $element['#after_build'] : [];
      $element['#after_build'] = [...$after_build, [self::class, 'afterBuild']];
      return $element;
    }
    $attributes = is_array($element['#label_attributes'] ?? NULL) ? $element['#label_attributes'] : [];
    $classes = is_array($attributes['class'] ?? NULL) ? $attributes['class'] : [];
    $attributes['class'] = [...$classes, 'form-required'];
    $element['#label_attributes'] = $attributes;
    return self::withAttributes($element, ['aria-required' => 'true']);
  }

  /**
   * Adds HTML attributes to an element.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param array<string, string> $attributes
   *   The attributes to add.
   *
   * @return array<int|string, mixed>
   *   The element.
   */
  private static function withAttributes(array $element, array $attributes): array {
    $existing = is_array($element['#attributes'] ?? NULL) ? $element['#attributes'] : [];
    $element['#attributes'] = $attributes + $existing;
    return $element;
  }

}
