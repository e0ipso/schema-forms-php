<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use SchemaForms\SchemaType;

/**
 * Decides which schema type a control is built for.
 *
 * @internal
 */
final class SchemaTypes {

  /**
   * Picks the type of the control for a schema.
   *
   * @param \stdClass $schema
   *   The schema.
   * @param array<mixed> $hints
   *   The UI hints. A 'ui:widget' replaces the schema type.
   *
   * @return string
   *   The type, or the widget name.
   */
  public static function guess(\stdClass $schema, array $hints): string {
    $widget = self::widget($hints);
    if ($widget !== NULL) {
      return $widget;
    }
    if (!isset($schema->type) && !empty($schema->enum) && is_array($schema->enum)) {
      return self::enumType($schema->enum);
    }
    $type = $schema->type ?? 'unknown';
    if (!is_array($type)) {
      return is_string($type) ? $type : 'unknown';
    }
    // 'null' has no control of its own, so a nullable type renders its other
    // member.
    $types = array_values(array_filter($type, static fn (mixed $item): bool => $item !== 'null'));
    return is_string($types[0] ?? NULL) && $types[0] !== '' ? $types[0] : 'null';
  }

  /**
   * Reads the Form API element type a 'ui:widget' hint asks for.
   *
   * @param array<mixed> $hints
   *   The UI hints.
   *
   * @return string|null
   *   The element type, or NULL when the hints name none.
   */
  public static function widget(array $hints): ?string {
    $widget = $hints['ui:widget'] ?? NULL;
    return !empty($widget) && is_scalar($widget) ? (string) $widget : NULL;
  }

  /**
   * Finds the scalar type of a schema that holds a saved scalar.
   *
   * @param \stdClass $schema
   *   The schema, whose type may be a list.
   * @param mixed $value
   *   The saved scalar.
   *
   * @return string|null
   *   The first type whose control shows the value, or NULL when none does.
   */
  public static function forScalar(\stdClass $schema, mixed $value): ?string {
    $candidates = match (TRUE) {
      is_string($value) => ['string'],
      is_int($value) => ['integer', 'number'],
      is_float($value) => ['number'],
      is_bool($value) => ['boolean'],
      default => [],
    };
    $types = array_values(array_intersect($candidates, SchemaType::listOf($schema)));
    return $types[0] ?? NULL;
  }

  /**
   * Decides whether a type is built from sub-schemas.
   *
   * @param string $type
   *   The type.
   *
   * @return bool
   *   TRUE for arrays and objects.
   */
  public static function isCompound(string $type): bool {
    return in_array($type, ['array', 'object'], TRUE);
  }

  /**
   * Infers the type of an enum without a declared type from its members.
   *
   * @param array<mixed> $members
   *   The enum members.
   *
   * @return string
   *   The type all members share. Mixed members use string controls.
   */
  private static function enumType(array $members): string {
    $checks = [
      'integer' => static fn (mixed $member): bool => is_int($member),
      'number' => static fn (mixed $member): bool => is_int($member) || is_float($member),
      'boolean' => static fn (mixed $member): bool => is_bool($member),
      'array' => static fn (mixed $member): bool => is_array($member),
      'object' => static fn (mixed $member): bool => is_object($member),
    ];
    $shared = array_filter(
      $checks,
      static fn (callable $check): bool => count(array_filter($members, $check)) === count($members)
    );
    return array_key_first($shared) ?? 'string';
  }

}
