<?php

declare(strict_types=1);

namespace SchemaForms;

/**
 * Reads the "type" keyword of a schema.
 *
 * @internal
 */
final class SchemaType {

  /**
   * Lists the type names a schema declares.
   *
   * "type" holds one name or a list of them. Members that are not strings,
   * such as the schemas a draft-03 union allows, name no type.
   *
   * @param object $schema
   *   The schema.
   *
   * @return list<string>
   *   The declared type names, in schema order; empty when there are none.
   */
  public static function listOf(object $schema): array {
    $type = $schema->type ?? [];
    return array_values(array_filter(is_array($type) ? $type : [$type], 'is_string'));
  }

}
