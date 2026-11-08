<?php

declare(strict_types=1);

namespace SchemaForms;

/**
 * Maps a submitted option key back to the enum member it was rendered from.
 *
 * Forms submit option keys as strings or integers, so an untyped enum of
 * other scalars needs its members back.
 *
 * @internal
 */
final class EnumMemberMatcher {

  /**
   * Returns the enum member a submitted option key stands for.
   *
   * @param string|int $data
   *   The submitted option key.
   * @param mixed $enum
   *   The enum keyword of an untyped schema.
   *
   * @return mixed
   *   The matching member, or the data when no member matches.
   */
  public static function match(string|int $data, mixed $enum): mixed {
    $members = [];
    // A decoded map lists its members as properties.
    foreach (is_array($enum) || is_object($enum) ? (array) $enum : [] as $member) {
      if (!is_scalar($member) && $member !== NULL) {
        continue;
      }
      // Match rendering keys; the last member wins when keys collide.
      $key = is_float($member) ? (string) $member : ($member ?? '');
      $members[$key] = $member;
    }
    return array_key_exists($data, $members) ? $members[$data] : $data;
  }

}
