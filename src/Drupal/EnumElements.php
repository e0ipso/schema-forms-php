<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Builds the option controls of enums.
 *
 * @internal
 */
final class EnumElements {

  /**
   * The element type of a single-choice enum.
   */
  public const RADIOS = 'radios';

  /**
   * The element type of a list of enum members.
   */
  public const CHECKBOXES = 'checkboxes';

  /**
   * Turns the element into a choice when the schema has an enum.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param \stdClass $schema
   *   The schema.
   * @param array<mixed> $hints
   *   The UI hints; a 'ui:widget' replaces the radios.
   *
   * @return array<int|string, mixed>
   *   The element.
   */
  public static function radios(array $element, \stdClass $schema, array $hints): array {
    $members = $schema->enum ?? NULL;
    if (empty($members) || !is_array($members)) {
      return $element;
    }
    $element['#type'] = SchemaTypes::widget($hints) ?? self::RADIOS;
    $element['#options'] = self::options($members, $hints);
    return $element;
  }

  /**
   * Turns the element into a list of choices from the item enum.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param array<mixed> $members
   *   The members of the item enum.
   * @param array<mixed> $hints
   *   The UI hints, for the option labels.
   *
   * @return array<int|string, mixed>
   *   The element.
   */
  public static function checkboxes(array $element, array $members, array $hints): array {
    $element['#type'] = self::CHECKBOXES;
    $element['#options'] = self::options($members, $hints);
    $validators = is_array($element['#element_validate'] ?? NULL) ? $element['#element_validate'] : [];
    $element['#element_validate'] = [...$validators, [self::class, 'validateCheckboxes']];
    return $element;
  }

  /**
   * Decides whether an enum the control would offer has a structured member.
   *
   * Option keys can only be scalars, so such an enum has no choice control.
   *
   * @param \stdClass $schema
   *   The schema.
   * @param string $type
   *   The type the control is built for.
   *
   * @return bool
   *   TRUE when an array or object member makes options impossible.
   */
  public static function hasStructuredMember(\stdClass $schema, string $type): bool {
    $items = $schema->items ?? NULL;
    $members = [
      ...(is_array($schema->enum ?? NULL) ? $schema->enum : []),
      ...($type === 'array' && is_object($items) && is_array($items->enum ?? NULL) ? $items->enum : []),
    ];
    return array_filter($members, static fn (mixed $member): bool => is_array($member) || is_object($member)) !== [];
  }

  /**
   * Turns the checked keys into a list; the #element_validate of checkboxes.
   *
   * @param array<int|string, mixed> $element
   *   The checkboxes element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateCheckboxes(array &$element, FormStateInterface $form_state): void {
    $value = is_array($element['#value'] ?? NULL) ? $element['#value'] : [];
    $options = is_array($element['#options'] ?? NULL) ? $element['#options'] : [];
    // Integer 0 marks an unchecked option; the string '0' is a checked key.
    $keys = array_filter(Checkboxes::getCheckedCheckboxes($value), static fn (mixed $key): bool => is_int($key) || is_string($key));
    $checked = array_fill_keys($keys, TRUE);
    // Follow the schema's option order, regardless of submission order.
    $form_state->setValueForElement($element, array_keys(array_intersect_key($options, $checked)));
  }

  /**
   * Builds the options of an enum, keyed by the value Form API submits.
   *
   * Each key is assigned on its own: array_merge() would renumber the integer
   * keys PHP makes of numeric strings. A float key is a string, because PHP
   * truncates a float key to an integer.
   *
   * @param array<mixed> $members
   *   The scalar enum members.
   * @param array<mixed> $hints
   *   The UI hints.
   *
   * @return array<int|string, \Drupal\Component\Render\MarkupInterface|string>
   *   The options.
   */
  private static function options(array $members, array $hints): array {
    $mappings = self::labelMappings($hints);
    $options = [];
    foreach ($members as $member) {
      $key = is_float($member) ? (string) $member : self::scalarKey($member);
      $mapped = $mappings[$key] ?? NULL;
      $options[$key] = $mapped === NULL ? self::label($member) : Labels::markup($mapped);
    }
    return $options;
  }

  /**
   * Gives the label of an enum member that no mapping labels.
   *
   * TRUE, FALSE, NULL and "" would otherwise give "1" or an empty label,
   * which leaves the option without an accessible name.
   *
   * @param mixed $member
   *   The scalar enum member.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The label.
   */
  private static function label(mixed $member): MarkupInterface|string {
    return match (TRUE) {
      $member === TRUE => new TranslatableMarkup('True'),
      $member === FALSE => new TranslatableMarkup('False'),
      $member === NULL => new TranslatableMarkup('None'),
      $member === '' => new TranslatableMarkup('Empty'),
      default => Labels::markup(Labels::humanName(is_scalar($member) ? (string) $member : '')),
    };
  }

  /**
   * Reads the 'ui:enum' label mappings.
   *
   * @param array<mixed> $hints
   *   The UI hints.
   *
   * @return array<mixed>
   *   The labels, keyed by option key.
   */
  private static function labelMappings(array $hints): array {
    $enum = is_array($hints['ui:enum'] ?? NULL) ? $hints['ui:enum'] : [];
    $labels = is_array($enum['labels'] ?? NULL) ? $enum['labels'] : [];
    return (array) ($labels['mappings'] ?? []);
  }

  /**
   * Gives the array key PHP makes of a scalar enum member.
   *
   * @param mixed $member
   *   The member.
   *
   * @return int|string
   *   The key.
   */
  private static function scalarKey(mixed $member): int|string {
    return match (TRUE) {
      is_int($member), is_string($member) => $member,
      is_bool($member) => (int) $member,
      default => '',
    };
  }

}
