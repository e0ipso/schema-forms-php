<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\Form\FormStateInterface;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Exception\ExceptionInterface;
use JsonSchema\Validator;
use SchemaForms\RegisteredSchema;
use SchemaForms\SchemaType;

/**
 * Validates the stored value of a generated form against its schema.
 *
 * @internal
 */
final class SubmissionValidator {

  /**
   * Creates the validator.
   *
   * @param \SchemaForms\Drupal\ErrorElementMapper $errors
   *   Puts the violations on the elements they belong to.
   */
  public function __construct(
    private readonly ErrorElementMapper $errors = new ErrorElementMapper(),
  ) {}

  /**
   * Validates the stored value and sets the errors on the form.
   *
   * @param array<int|string, mixed> $element
   *   The generated root container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \SchemaForms\RegisteredSchema $schema
   *   The registered schema of the container.
   */
  public function validate(array $element, FormStateInterface $form_state, RegisteredSchema $schema): void {
    try {
      $shaped = JsonShape::fromSchema(SubmissionPreparer::storedValue($element, $form_state), $schema->schema);
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
    $data = $data === [] || $data === NULL ? self::emptyValue($schema->schema) : $data;
    try {
      $validator = new Validator($schema->factory);
      $validator->validate($data, $schema->schema, Constraint::CHECK_MODE_TYPE_CAST);
    }
    catch (ExceptionInterface $e) {
      $form_state->setError($element, ValidationMessages::unsupportedReference());
      return;
    }
    $this->errors->setErrors($element, $form_state, self::errorList($validator->getErrors()));
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
    return in_array('array', SchemaType::listOf($schema), TRUE) ? [] : new \stdClass();
  }

  /**
   * Keeps the validator errors that have the shape of an error.
   *
   * @param array<mixed> $errors
   *   The errors the validator reported.
   *
   * @return list<array<mixed>>
   *   The errors.
   */
  private static function errorList(array $errors): array {
    return array_values(array_filter($errors, 'is_array'));
  }

}
