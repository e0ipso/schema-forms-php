<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

/**
 * Gives submitted arrays the JSON shape their schema describes.
 *
 * PHP holds an object whose property names are "0" to "n-1" as a list, and
 * json_encode() writes a list as a JSON array. The key shape alone cannot
 * tell such an object from an array, so the schema decides.
 *
 * @internal
 */
final class JsonShape {

  /**
   * Turns the arrays the schema describes as objects into objects.
   *
   * @param mixed $data
   *   The submitted value.
   * @param mixed $schema
   *   The schema of the value, or NULL when it is unknown.
   *
   * @return mixed
   *   The value, with objects as \stdClass where the schema asks for one.
   *   Arrays the schema does not describe keep the shape json_encode() gives
   *   them.
   */
  public static function fromSchema(mixed $data, mixed $schema): mixed {
    if (!is_array($data)) {
      return $data;
    }
    $schema = $schema instanceof \stdClass ? $schema : NULL;
    if (!self::isObject($data, $schema)) {
      $items = $schema === NULL ? NULL : ($schema->items ?? NULL);
      return array_map(static fn (mixed $item): mixed => self::fromSchema($item, $items), $data);
    }
    $object = new \stdClass();
    foreach ($data as $name => $value) {
      $object->{$name} = self::fromSchema($value, self::propertySchema($schema, (string) $name));
    }
    return $object;
  }

  /**
   * Decides whether an array holds a JSON object.
   *
   * @param array<int|string, mixed> $data
   *   The array.
   * @param \stdClass|null $schema
   *   The schema of the array, if known.
   *
   * @return bool
   *   TRUE when the array is an object.
   */
  private static function isObject(array $data, ?\stdClass $schema): bool {
    $types = $schema === NULL ? [] : (array) ($schema->type ?? []);
    if (in_array('object', $types, TRUE) && !in_array('array', $types, TRUE)) {
      return TRUE;
    }
    return !array_is_list($data);
  }

  /**
   * Finds the schema of a declared property.
   *
   * Generated forms only render declared properties, so other names keep
   * the shape json_encode() gives them.
   *
   * @param \stdClass|null $schema
   *   The object schema, if known.
   * @param string $name
   *   The property name.
   *
   * @return mixed
   *   The property schema, or NULL.
   */
  private static function propertySchema(?\stdClass $schema, string $name): mixed {
    $properties = $schema === NULL ? NULL : ($schema->properties ?? NULL);
    return is_object($properties) ? ($properties->{$name} ?? NULL) : NULL;
  }

}
