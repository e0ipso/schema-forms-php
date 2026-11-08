<?php

declare(strict_types=1);

namespace SchemaForms;

/**
 * Deep-copies the decoded objects and arrays of a schema.
 *
 * Objects other than stdClass are not schema data and stay shared. Shared and
 * cyclic members keep their shape in the copy.
 *
 * @internal
 */
final class SchemaCopier {

  /**
   * Starts a copy with no members copied yet.
   *
   * @param \WeakMap<object, mixed> $copies
   *   Copies already made, keyed by the original.
   */
  private function __construct(
    private readonly \WeakMap $copies = new \WeakMap(),
  ) {}

  /**
   * Copies a schema.
   *
   * @param object $schema
   *   The decoded schema.
   *
   * @return object
   *   The copy; the same object when it is not a stdClass.
   */
  public static function copy(object $schema): object {
    return (new self())->copyValue($schema);
  }

  /**
   * Copies a schema or one of its members.
   *
   * @param mixed $value
   *   The schema or one of its members.
   *
   * @return mixed
   *   The copy.
   *
   * @phpstan-return ($value is object ? object : mixed)
   */
  private function copyValue(mixed $value): mixed {
    if (is_array($value)) {
      return array_map(fn(mixed $member): mixed => $this->copyValue($member), $value);
    }
    if (!$value instanceof \stdClass) {
      return $value;
    }
    if (isset($this->copies[$value])) {
      return $this->copies[$value];
    }
    $copy = clone $value;
    $this->copies[$value] = $copy;
    foreach (get_object_vars($copy) as $name => $member) {
      $copy->{$name} = $this->copyValue($member);
    }
    return $copy;
  }

}
