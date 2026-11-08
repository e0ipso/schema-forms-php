<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\Form\FormStateInterface;
use JsonSchema\Constraints\Factory;
use JsonSchema\Exception\ExceptionInterface;
use SchemaForms\RecursiveTypeCaster;

/**
 * Turns a generated form's submitted values into the JSON value to store.
 *
 * @internal
 */
final class SubmissionPreparer {

  /**
   * Creates the preparer.
   *
   * @param \SchemaForms\Drupal\PreservedValues $preserved
   *   Puts back the values controls cannot submit.
   */
  public function __construct(
    private readonly PreservedValues $preserved = new PreservedValues(),
  ) {}

  /**
   * Cleans and casts the submitted values, then stores them.
   *
   * @param array<int|string, mixed> $element
   *   The generated root container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param object $schema
   *   The schema. With a factory, the schema that factory registered.
   * @param \JsonSchema\Constraints\Factory|null $factory
   *   The restricted factory of this submission, or NULL to create one.
   *
   * @return bool
   *   FALSE when the schema could not be applied; the error is set.
   */
  public function prepare(array $element, FormStateInterface $form_state, object $schema, ?Factory $factory = NULL): bool {
    $data = UserInputCleaner::cleanUserInput(SubmittedValue::read($element, $form_state), $element);
    try {
      $data = RecursiveTypeCaster::recursiveTypeRefinements($data, $schema, $factory);
    }
    catch (ExceptionInterface $e) {
      $form_state->setError($element, ValidationMessages::unsupportedReference());
      return FALSE;
    }
    $form_state->setValueForElement($element, $this->preserved->restore($element, $data));
    return TRUE;
  }

}
