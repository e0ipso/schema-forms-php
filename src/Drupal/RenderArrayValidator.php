<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Shaper\Validator\ValidateableBase;

/**
 * Checks whether the passed data is a Drupal render array.
 *
 * @internal
 */
final class RenderArrayValidator extends ValidateableBase {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function isValid(mixed $data): bool {
    $this->resetErrors();
    if (self::isRenderArray($data)) {
      return TRUE;
    }
    $this->errors = ['Expected a render array: an array with a "#" key, or with a child that is a render array.'];
    return FALSE;
  }

  /**
   * Checks one level of a render array.
   *
   * A key that contains "#" anywhere is a render property. Without one, any
   * child render array is enough, so an empty container does not reject its
   * siblings.
   *
   * @param mixed $data
   *   The data to check.
   *
   * @return bool
   *   TRUE when the data is a render array.
   */
  private static function isRenderArray(mixed $data): bool {
    if (!is_array($data)) {
      return FALSE;
    }
    foreach ($data as $key => $value) {
      if (str_contains((string) $key, '#') || self::isRenderArray($value)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
