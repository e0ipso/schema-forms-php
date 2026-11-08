<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\Form\FormStateInterface;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Exception\ExceptionInterface;
use JsonSchema\Validator;
use SchemaForms\SchemaReferenceResolver;

/**
 * Validates the stored value of a generated form against its schema.
 *
 * @internal
 */
final class SubmissionValidator {

  /**
   * Validates the stored value and sets the errors on the form.
   *
   * @param array<int|string, mixed> $element
   *   The generated root container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param object $schema
   *   The schema.
   */
  public static function validate(array $element, FormStateInterface $form_state, object $schema): void {
    try {
      $shaped = JsonShape::fromSchema(SubmittedValue::read($element, $form_state), $schema);
      $encoded = json_encode($shaped, JSON_THROW_ON_ERROR);
      $data = json_decode($encoded, flags: JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      // Invalid UTF-8, recursion or a non-finite number has no JSON form, and
      // must not be validated as an empty object instead.
      $form_state->setError($element, ValidationMessages::unencodableSubmission());
      return;
    }
    // Form API cannot tell an empty object from an empty list.
    $data = $data === [] || $data === NULL ? self::emptyValue($schema) : $data;
    try {
      $validator = new Validator(SchemaReferenceResolver::createFactory($schema));
      $validator->validate($data, $schema, Constraint::CHECK_MODE_TYPE_CAST);
    }
    catch (ExceptionInterface $e) {
      $form_state->setError($element, ValidationMessages::unsupportedReference());
      return;
    }
    ErrorElementMapper::setErrors($element, $form_state, $validator->getErrors());
  }

  /**
   * Picks the empty value a schema expects.
   *
   * @param object $schema
   *   The schema.
   *
   * @return array{}|\stdClass
   *   An empty list when the schema allows arrays, otherwise an empty object.
   */
  private static function emptyValue(object $schema): array|\stdClass {
    return in_array('array', (array) ($schema->type ?? []), TRUE) ? [] : new \stdClass();
  }

}
