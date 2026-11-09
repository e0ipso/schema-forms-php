<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

/**
 * Maps the JSON Schema scalar types to the generator's own elements.
 *
 * @internal
 */
final class ScalarElementMapper implements ElementMapperInterface {

  /**
   * Form API elements for scalar schema types, keyed by type or type:format.
   *
   * Drupal has no 'integer' element; a number input with step 1 makes the
   * browser reject fractions, and ScalarCaster::cast() turns the submitted
   * whole number into an int.
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
