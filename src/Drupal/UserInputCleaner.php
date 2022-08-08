<?php

namespace SchemaForms\Drupal;

use Drupal\Component\Render\MarkupInterface;

/**
 * Cleans the lingering user input from the form submissions.
 */
class UserInputCleaner {

  /**
   * Cleans the input.
   *
   * @param mixed $data
   *   The input before cleaning.
   *
   * @return array|mixed
   *   The clean input.
   */
  public static function cleanUserInput(mixed $data) {
    return static::filterEmptyItems(
      static::doCleanUserInput($data)
    );
  }

  /**
   * Cleans the input.
   *
   * @param mixed $data
   *   The input before cleaning.
   *
   * @return array|mixed
   *   The clean input.
   */
  private static function doCleanUserInput(mixed $data) {
    if (!is_array($data)) {
      return $data;
    }
    // Get rid of the 'add_more' button. This will allow array_is_list to be
    // more reliable.
    foreach ($data as $key => $datum) {
      if ($key === 'add_more' && $datum instanceof MarkupInterface) {
        unset($data[$key]);
      }
    }
    if (array_is_list($data)) {
      $data = static::cleanRemoveButton($data);
      return static::arrayTrim($data);
    }
    foreach ($data as $key => $datum) {
      $clean_datum = static::cleanUserInput($datum);
      if (is_null($clean_datum)) {
        unset($data[$key]);
        continue;
      }
      $data[$key] = $clean_datum;
    }
    if (array_is_list($data)) {
      return static::arrayTrim($data);
    }
    return $data;
  }

  /**
   * Remove empty items from the form recursively.
   *
   * @param mixed $data
   *   The data.
   *
   * @return mixed
   *   The filtered data.
   */
  private static function filterEmptyItems(mixed $data): mixed {
    if (!is_array($data)) {
      return $data;
    }
    return array_filter(array_map([static::class, 'filterEmptyItems'], $data));
  }

  /**
   * Checks if a simple data structure contains any data.
   *
   * @param mixed $item
   *   The data structure.
   *
   * @return bool
   *   TRUE if it has data. FALSE otherwise.
   */
  private static function hasData(mixed $item): bool {
    if (empty($item) && $item !== 0 && $item !== FALSE) {
      return FALSE;
    }
    if (!is_array($item)) {
      return TRUE;
    }
    return array_reduce(
      $item,
      static fn(bool $res, $el) => $res || static::hasData($el),
      FALSE
    );
  }

  /**
   * Trims an array of values with empty strings.
   *
   * @param array $data
   *   The data to trim.
   *
   * @return array
   *   The trimmed data.
   */
  private static function arrayTrim(array $data): array {
    if (!array_is_list($data)) {
      return $data;
    }
    $last_non_empty = -1;
    foreach ($data as $index => $value) {
      if ($value !== '') {
        $last_non_empty = $index;
      }
    }
    return array_slice($data, 0, $last_non_empty + 1);
  }

  /**
   * Removes the cruft introduced to support the remove button.
   *
   * @param array $data
   *   The input data.
   *
   * [['removable_element' => 'foo', 'remove_one' => TranslatebleMarkup], ...]
   * becomes ['foo', '...'].
   *
   * @return array
   *   The data without the repercussions of the remove button.
   */
  public static function cleanRemoveButton(array $data): array {
    // Make sure we can undo the remove button data structure.
    $can_undo_form_nesting = static fn (array $items) => array_reduce(
      $items,
      static fn(bool $carry, mixed $item) => $carry
        && is_array($item)
        && count(array_intersect(array_keys($item), [
          'removable_element',
          'remove_one',
        ])) === 2
        && $item['remove_one'] instanceof MarkupInterface,
      TRUE
    );
    $undo_form_nesting = static fn (array $items) => array_values(array_map(
      static fn(array $item) => $item['removable_element'] ?? NULL,
      $items
    ));

    return $can_undo_form_nesting($data) ? $undo_form_nesting($data) : $data;
  }

}
