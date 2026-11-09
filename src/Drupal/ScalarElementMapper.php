<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

/**
 * Maps the JSON Schema scalar types to the generator's own elements.
 *
 * The generator asks it after any mapper the caller passed.
 *
 * @internal
 */
final class ScalarElementMapper implements ElementMapperInterface {

  /**
   * Form API elements for scalar schema types, keyed by type or type:format.
   *
   * A 'type:format' key wins over the plain type.
   *
   * Drupal has no 'integer' element. A number input whose step is one is the
   * native spelling of the constraint: the browser rejects a fractional value
   * before the form is submitted, and the value still arrives as an integer
   * because ScalarCaster::cast() turns a numeric string that spells a whole
   * number without a dot into an int.
   */
  public const ELEMENTS = [
    'boolean' => ['#type' => 'checkbox'],
    'integer' => ['#type' => 'number', '#step' => 1],
    'number' => ['#type' => 'number'],
    'string' => ['#type' => 'textfield'],
    'string:email' => ['#type' => 'email'],
  ];

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function map(\stdClass $schema, string $type): ?array {
    $format = is_string($schema->format ?? NULL) ? $schema->format : '';
    return self::ELEMENTS[$type . ':' . $format] ?? self::ELEMENTS[$type] ?? NULL;
  }

}
