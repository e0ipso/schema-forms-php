<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Fixture;

/**
 * Constants of every JSON type, with the text a generated form shows.
 */
trait ConstantSchemas {

  /**
   * Lists constants and their displayed text.
   *
   * @return array<string, array{0: mixed, 1: string}>
   *   The constant and its text, keyed by case name.
   */
  public static function constantProvider(): array {
    return [
      'zero' => [0, '0'],
      'false' => [FALSE, 'false'],
      'empty string' => ['', ''],
      'string zero' => ['0', '0'],
      'array' => [['a'], '["a"]'],
      'object' => [(object) ['k' => 1], '{"k":1}'],
      'null' => [NULL, 'null'],
      'unsafe text' => ['<script>alert(1)</script>', '<script>alert(1)</script>'],
    ];
  }

  /**
   * Builds a root schema with one constant property named "fixed".
   *
   * @param mixed $value
   *   The constant.
   *
   * @return object
   *   The schema, whose property type matches the constant.
   */
  private static function constantSchema(mixed $value): object {
    $type = match (gettype($value)) {
      'integer' => 'integer',
      'double' => 'number',
      'boolean' => 'boolean',
      'array' => 'array',
      'object' => 'object',
      'NULL' => 'null',
      default => 'string',
    };
    return (object) [
      'type' => 'object',
      'properties' => (object) ['fixed' => (object) ['type' => $type, 'const' => $value]],
    ];
  }

}
