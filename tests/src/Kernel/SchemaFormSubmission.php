<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use Drupal\Core\Form\FormStateInterface;

/**
 * The outcome of submitting a generated form through the FormBuilder.
 */
final class SchemaFormSubmission {

  /**
   * Creates the submission result.
   *
   * @param array $form
   *   The processed form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state after validation and submission.
   * @param array<string, string> $errors
   *   The form errors as plain strings, keyed by element name (the element
   *   #parents joined with '][').
   * @param array $values
   *   The processed values, after the schema validation type-casting.
   */
  public function __construct(
    public readonly array $form,
    public readonly FormStateInterface $formState,
    public readonly array $errors,
    public readonly array $values,
  ) {}

}
