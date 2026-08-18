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
  public static function cleanUserInput($data) {
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
  private static function doCleanUserInput($data) {
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
    $data = static::reKey($data);
    if (array_is_list($data)) {
      return static::cleanRemoveButton($data);
    }
    foreach ($data as $key => $datum) {
      $clean_datum = static::cleanUserInput($datum);
      if (is_null($clean_datum)) {
        unset($data[$key]);
        continue;
      }
      $data[$key] = $clean_datum;
    }
    return $data;
  }

  /**
   * Removes the items a multivalue submitted nothing for, recursively.
   *
   * Only a list is filtered, and only ::doCleanUserInput() produces one: it is
   * what a multivalue's item list becomes once the `add_more` button is gone
   * and ::cleanRemoveButton() has undone the per-item form nesting. An item
   * left at `[]` there is the scaffolding an appended-but-never-filled item
   * leaves behind, and it must not become a stored item.
   *
   * An empty array under a *named* key is not that. It is the whole value of a
   * property whose type is `array` and whose item list is empty, which is a
   * value the user chose and a value the schema accepts. Filtering both meant
   * a property could be given `[]` and have it silently disappear from the
   * submission, with no way to tell the two apart.
   *
   * @param mixed $data
   *   The data.
   *
   * @return mixed
   *   The filtered data.
   */
  private static function filterEmptyItems($data) {
    if (!is_array($data)) {
      return $data;
    }
    $data = array_map([static::class, 'filterEmptyItems'], $data);
    if (!array_is_list($data)) {
      return $data;
    }
    // array_values(): dropping an item has to leave a list behind. A gapped
    // array is an object by the time it reaches the JSON Schema validator,
    // and the property it belongs to declares `array`.
    return array_values(array_filter(
      $data,
      static fn ($item): bool => !is_array($item) || $item !== []
    ));
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
  private static function hasData($item): bool {
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
   * Removes the cruft introduced to support the remove button.
   *
   * [['removable_element' => 'foo', 'remove_one' => TranslatebleMarkup], ...]
   * becomes ['foo', '...'].
   *
   * @param array $data
   *   The input data.
   *
   * @return array
   *   The data without the repercussions of the remove button.
   */
  public static function cleanRemoveButton(array $data): array {
    $two = 2;
    // Make sure we can undo the remove button data structure.
    $can_undo_form_nesting = static fn (array $items) => array_reduce(
      $items,
      static fn(bool $carry, $item) => $carry
        && is_array($item)
        && count(array_intersect(array_keys($item), [
          'removable_element',
          'remove_one',
        ])) === $two
        && $item['remove_one'] instanceof MarkupInterface,
      TRUE
    );
    $undo_form_nesting = static fn (array $items) => array_values(array_map(
      static fn(array $item) => $item['removable_element'] ?? NULL,
      $items
    ));

    return $can_undo_form_nesting($data) ? $undo_form_nesting($data) : $data;
  }

  /**
   * Re-keys the data to account for deleted indices.
   *
   * @param array $data
   *   The input data.
   *
   * @return array
   *   The re-keyed data.
   */
  private static function reKey(array $data): array {
    $all_keys_numeric = array_reduce(
      array_keys($data),
      static fn (bool $carry, $key) => $carry && is_numeric($key),
      TRUE
    );
    return $all_keys_numeric ? array_values($data) : $data;
  }

}
