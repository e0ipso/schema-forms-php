<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use JsonSchema\Exception\ExceptionInterface;
use SchemaForms\RecursiveTypeCaster;

/**
 * Turns a generated form's submitted values into the JSON value to store.
 *
 * @internal
 */
final class SubmissionPreparer {

  /**
   * Cleans and casts the submitted values, then stores them.
   *
   * @param array<int|string, mixed> $element
   *   The generated root container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param object $schema
   *   The schema.
   *
   * @return bool
   *   FALSE when the schema could not be applied; the error is set.
   */
  public static function prepare(array $element, FormStateInterface $form_state, object $schema): bool {
    $data = UserInputCleaner::cleanUserInput(SubmittedValue::read($element, $form_state), $element);
    try {
      $data = RecursiveTypeCaster::recursiveTypeRefinements($data, $schema);
    }
    catch (ExceptionInterface $e) {
      $form_state->setError($element, ValidationMessages::unsupportedReference());
      return FALSE;
    }
    // Saved NULLs go last: a fixed sub-property must not turn one back into
    // an object.
    $data = self::withSavedNulls($element, self::withFixedValues($element, $data));
    $form_state->setValueForElement($element, $data);
    return TRUE;
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
  private static function withFixedValues(array $element, mixed $data): mixed {
    $fixed = ElementDataPaths::outermost(
      $element,
      static fn (array $candidate): bool => array_key_exists('#schema_fixed_value', $candidate)
    );
    return array_reduce(
      $fixed,
      static function (mixed $data, ElementDataPath $pair): array {
        $data ??= [];
        NestedArray::setValue($data, $pair->path, $pair->element['#schema_fixed_value'], TRUE);
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
  private static function withSavedNulls(array $element, mixed $data): mixed {
    $untouched = ElementDataPaths::outermost(
      $element,
      static fn (array $candidate): bool => !empty($candidate['#schema_saved_null']) && self::isUntouched($candidate)
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
  private static function isUntouched(array $element): bool {
    if (MultivalueWidget::isButton($element) || array_key_exists('#schema_fixed_value', $element)) {
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
      Element::children($element),
      static fn (bool $untouched, int|string $key): bool => $untouched && self::isUntouched($element[$key]),
      TRUE
    );
  }

}
