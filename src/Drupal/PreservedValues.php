<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Component\Utility\NestedArray;

/**
 * Puts back the values that generated controls cannot submit.
 *
 * @internal
 */
final class PreservedValues {

  /**
   * Adds fixed values, then restores the saved NULLs of untouched controls.
   *
   * Saved NULLs go last: a fixed sub-property must not turn one back into
   * an object.
   *
   * @param array<int|string, mixed> $element
   *   The generated root container.
   * @param mixed $data
   *   The cleaned and cast values.
   *
   * @return mixed
   *   The values to store.
   */
  public function restore(array $element, mixed $data): mixed {
    return $this->withSavedNulls($element, $this->withFixedValues($element, $data));
  }

  /**
   * Supplies the values of generated elements that collect no input.
   *
   * Fixed values are already JSON values. They go in after cleaning and
   * casting, so NULL is not dropped and constants keep their exact type.
   *
   * @param array<int|string, mixed> $element
   *   The generated root container.
   * @param mixed $data
   *   The cleaned and cast values.
   *
   * @return mixed
   *   The values with the fixed ones.
   */
  private function withFixedValues(array $element, mixed $data): mixed {
    $fixed = ElementDataPaths::outermost(
      $element,
      static fn (array $candidate): bool => array_key_exists(RenderKeys::FIXED_VALUE, $candidate)
    );
    return array_reduce(
      $fixed,
      static function (mixed $data, ElementDataPath $pair): array {
        $data = is_array($data) ? $data : [];
        NestedArray::setValue($data, $pair->path, $pair->element[RenderKeys::FIXED_VALUE], TRUE);
        return $data;
      },
      $data
    );
  }

  /**
   * Restores the saved NULLs of untouched controls.
   *
   * An editable control cannot show NULL, so one that held a saved NULL
   * submits its empty value instead, which cleaning may drop or casting may
   * change. A control the user left as it was gets its NULL back.
   *
   * @param array<int|string, mixed> $element
   *   The generated root container.
   * @param mixed $data
   *   The prepared values.
   *
   * @return mixed
   *   The values with the saved NULLs.
   */
  private function withSavedNulls(array $element, mixed $data): mixed {
    $untouched = ElementDataPaths::outermost(
      $element,
      fn (array $candidate): bool => !empty($candidate[RenderKeys::SAVED_NULL]) && $this->isUntouched($candidate)
    );
    return array_reduce(
      $untouched,
      static function (mixed $data, ElementDataPath $pair): array {
        $data = is_array($data) ? $data : [];
        NestedArray::setValue($data, $pair->path, NULL, TRUE);
        return $data;
      },
      $data
    );
  }

  /**
   * Decides whether a control still submits what it showed for a saved NULL.
   *
   * A saved NULL renders as an unchecked checkbox, an empty selection, an
   * empty text input or a list with no items. Buttons and fixed values do not
   * count.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   *
   * @return bool
   *   TRUE when no control under the element holds a value.
   */
  private function isUntouched(array $element): bool {
    if (MultivalueWidget::isButton($element) || array_key_exists(RenderKeys::FIXED_VALUE, $element)) {
      return TRUE;
    }
    if (!empty($element['#input'])) {
      $value = $element['#value'] ?? NULL;
      // An unchecked checkbox has integer 0; a checked one its return value.
      return ($element['#type'] ?? '') === 'checkbox'
        ? $value === 0
        : in_array($value, ['', NULL, []], TRUE);
    }
    return array_reduce(
      RenderChildren::of($element),
      fn (bool $untouched, array $child): bool => $untouched && $this->isUntouched($child),
      TRUE
    );
  }

}
