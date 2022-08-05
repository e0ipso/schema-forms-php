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
    if (!is_array($data)) {
      return $data;
    }
    if (array_is_list($data)) {
      return static::arrayTrim($data);
    }
    foreach ($data as $key => $datum) {
      if ($key === 'add_more' && $datum instanceof MarkupInterface) {
        unset($data[$key]);
        continue;
      }
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

}
