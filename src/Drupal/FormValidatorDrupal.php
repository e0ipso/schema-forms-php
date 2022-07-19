<?php

namespace SchemaForms\Drupal;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use JsonSchema\Validator;
use SchemaForms\ArrayToStdClass;

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
    $submitted = $form_state->getValue($element['#parents']);
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
          '%element' => $error_element['#title'],
          '@message' => $message,
        ])
      );
      return NULL;
    }
    return $message;
  }

}
