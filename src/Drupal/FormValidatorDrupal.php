<?php

namespace SchemaForms\Drupal;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use JsonSchema\Validator;
use SchemaForms\ArrayToStdClass;
use SchemaForms\RecursiveTypeCaster;

/**
 * Generates Drupal Form API forms from JSON-Schema documents.
 */
final class FormValidatorDrupal {

  /**
   * Validation callback against the schema.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param object $schema
   *   The form schema.
   */
  public static function validateWithSchema(array &$element, FormStateInterface $form_state, object $schema): void {
    $parents = $element['#parents'];
    $submitted = $form_state->getValue($parents);
    // Filter data to avoid empty string to trigger format errors.
    $data = (new ArrayToStdClass())->transform(array_filter($submitted));
    $validator = new Validator();
    // Validate the massaged data against the schema.
    $num_errors = $validator->validate($data, $schema);
    if ($num_errors) {
      $errors = $validator->getErrors();
      // Now check if we can find a specific form element to trigger the error.
      $generic_errors = array_filter(
        array_map(
          static fn(array $error) => self::errorForProp($element, $form_state, $error),
          $errors
        )
      );
      if (empty($generic_errors)) {
        return;
      }
      $full_message = implode(
        ', ',
        array_map([Html::class, 'escape'], $generic_errors)
      );
      $form_state->setError(
        $element,
        new TranslatableMarkup(
          '<p>Invalid data, please make sure all data is valid according to the schema. Schema validation returned the following errors errors: @full_message</p>',
          ['@full_message' => $full_message]
        )
      );
    }
  }

  /**
   * Sets or returns the error for a prop.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $error
   *   The error data from the JSON-Schema validation.
   *
   * @return string|null
   *   String if we could not find the particular prop this error is for. NULL
   *   if the error could be set.
   */
  private static function errorForProp(array $element, FormStateInterface $form_state, array $error): ?string {
    $message = $error['message'] . ' ' . $error['constraint'];
    $error_parents = array_filter(explode('/', $error['pointer']));
    $key_exists = FALSE;
    $error_element = NestedArray::getValue($element, $error_parents, $key_exists);
    if ($key_exists) {
      $form_state->setError(
        $error_element,
        new TranslatableMarkup('%element: @message', [
          '%element' => $error_element['#title'] ?? '',
          '@message' => $message,
        ])
      );
      return NULL;
    }
    return $message;
  }

  /**
   * Casts form submissions to their corresponding JSON types.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param object $schema
   *   The data schema.
   */
  public static function typeCastRecursive(array $element, FormStateInterface $form_state, object $schema): void {
    $data = $form_state->getValue($element['#parents']);
    $data = static::cleanUserInput($data);
    $data = RecursiveTypeCaster::recursiveTypeRefinements($data, $schema);
    $form_state->setValueForElement($element, $data);
  }

  private static function cleanUserInput($data) {
    if (!is_array($data)) {
      return $data;
    }
    if (array_is_list($data)) {
      return static::arrayTrim($data);
    }
    foreach ($data as $key => $datum) {
      // If the data was left empty in a fieldset, remove it.
      if ($datum === '') {
        unset($data[$key]);
        continue;
      }
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
