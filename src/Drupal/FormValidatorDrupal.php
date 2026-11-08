<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\Form\FormStateInterface;

/**
 * Prepares and validates Drupal Form API submissions of generated forms.
 *
 * @internal
 *
 * @see \SchemaForms\Drupal\SubmissionPreparer
 * @see \SchemaForms\Drupal\SubmissionValidator
 */
final class FormValidatorDrupal {

  /**
   * Validates the stored value of a generated form against its schema.
   *
   * @param array<int|string, mixed> $element
   *   The generated root container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param object $schema
   *   The form schema.
   */
  public static function validateWithSchema(array &$element, FormStateInterface $form_state, object $schema): void {
    SubmissionValidator::validate($element, $form_state, $schema);
  }

  /**
   * Casts form submissions to their corresponding JSON types.
   *
   * @param array<int|string, mixed> $element
   *   The generated root container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param object $schema
   *   The data schema.
   */
  public static function typeCastRecursive(array $element, FormStateInterface $form_state, object $schema): void {
    SubmissionPreparer::prepare($element, $form_state, $schema);
  }

}
