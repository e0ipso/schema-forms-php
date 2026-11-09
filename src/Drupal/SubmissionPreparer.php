<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use JsonSchema\Exception\ExceptionInterface;
use SchemaForms\RecursiveTypeCaster;
use SchemaForms\RegisteredSchema;

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
   * @param \SchemaForms\RegisteredSchema $schema
   *   The registered schema of the container.
   *
   * @return bool
   *   FALSE when the schema could not be applied; the error is set.
   */
  public function prepare(array $element, FormStateInterface $form_state, RegisteredSchema $schema): bool {
    $data = UserInputCleaner::cleanUserInput(self::storedValue($element, $form_state), $element);
    try {
      $data = RecursiveTypeCaster::refine($data, $schema);
    }
    catch (ExceptionInterface $e) {
      $form_state->setError($element, ValidationMessages::unsupportedReference());
      return FALSE;
    }
    // Saved NULLs go last: a fixed sub-property must not turn one back into
    // an object.
    $form_state->setValueForElement($element, $this->withSavedNulls($element, $this->withFixedValues($element, $data)));
    return TRUE;
  }

  /**
   * Reads the stored value of an element.
   *
   * A subform state resolves value paths relative to the subform, while
   * #parents are absolute, so the complete form state is read.
   *
   * @param array<int|string, mixed> $element
   *   The element, with its #parents.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state, or a subform state.
   *
   * @return mixed
   *   The stored value, or NULL when there is none.
   */
  public static function storedValue(array $element, FormStateInterface $form_state): mixed {
    $complete_state = $form_state instanceof SubformStateInterface
      ? $form_state->getCompleteFormState()
      : $form_state;
    $parents = $element['#parents'] ?? [];
    return $complete_state->getValue(is_array($parents) || is_string($parents) ? $parents : []);
  }

  /**
   * Supplies the values of generated elements that collect no input.
   *
   * They go in after cleaning and casting, so NULL is not dropped and
   * constants keep their exact type.
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
      static function (mixed $data, array $pair): array {
        $data = is_array($data) ? $data : [];
        NestedArray::setValue($data, $pair[0], $pair[1][RenderKeys::FIXED_VALUE], TRUE);
        return $data;
      },
      $data
    );
  }

  /**
   * Restores the saved NULLs of untouched controls.
   *
   * An editable control cannot show NULL, so it submits its empty value.
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
      static function (mixed $data, array $pair): array {
        $data = is_array($data) ? $data : [];
        NestedArray::setValue($data, $pair[0], NULL, TRUE);
        return $data;
      },
      $data
    );
  }

  /**
   * Decides whether a control still submits what it showed for a saved NULL.
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
