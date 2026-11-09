<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Component\Render\MarkupInterface;
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
    self::setErrors($element, $form_state, array_values(array_filter($validator->getErrors(), 'is_array')));
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
   * Puts the violations on the generated elements they belong to.
   *
   * @param array<int|string, mixed> $root
   *   The generated root container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param list<array<mixed>> $errors
   *   The errors the JSON Schema validator reported.
   */
  private static function setErrors(array $root, FormStateInterface $form_state, array $errors): void {
    if ($errors === []) {
      return;
    }
    $targets = self::elementsByPointer($root);
    $mapped = array_filter(
      $errors,
      static fn (array $error): bool => isset($targets[self::pointerOf($error)])
    );
    foreach ($mapped as $error) {
      $target = $targets[self::pointerOf($error)];
      $form_state->setError($target, ValidationMessages::elementViolation($target['#title'] ?? '', self::violation($error)));
    }
    $unmapped = array_diff_key($errors, $mapped);
    if ($unmapped === []) {
      return;
    }
    $form_state->setError($root, ValidationMessages::invalidData(array_values(array_map([self::class, 'violation'], $unmapped))));
  }

  /**
   * Reads the JSON pointer of an error.
   *
   * @param array<mixed> $error
   *   The error the JSON Schema validator reported.
   *
   * @return string
   *   The pointer, or '' when the error has none.
   */
  private static function pointerOf(array $error): string {
    $pointer = $error['pointer'] ?? '';
    return is_string($pointer) ? $pointer : '';
  }

  /**
   * Indexes the generated elements by the JSON pointer of their value.
   *
   * @param array<int|string, mixed> $root
   *   The generated root container.
   *
   * @return array<string, array<int|string, mixed>>
   *   The elements, keyed by JSON pointer. The root is '/'.
   */
  private static function elementsByPointer(array $root): array {
    $pairs = ElementDataPaths::all($root);
    return array_combine(
      array_map(static fn (array $pair): string => self::pointer($pair[0]), $pairs),
      array_column($pairs, 1)
    );
  }

  /**
   * Builds the JSON pointer of a value path.
   *
   * @param list<int|string> $path
   *   The value path.
   *
   * @return string
   *   The pointer, escaped as both json-schema majors escape theirs.
   */
  private static function pointer(array $path): string {
    $segments = array_map(
      static fn (int|string $segment): string => strtr((string) $segment, ['/' => '~1', '~' => '~0', '%' => '%25']),
      $path
    );
    return '/' . implode('/', $segments);
  }

  /**
   * Describes a violation.
   *
   * @param array<mixed> $error
   *   The error the JSON Schema validator reported.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The description.
   */
  private static function violation(array $error): MarkupInterface|string {
    $message = $error['message'] ?? '';
    return ValidationMessages::violation(is_scalar($message) ? (string) $message : '', self::constraintName($error));
  }

  /**
   * Names the constraint a validation error violated.
   *
   * The json-schema 5.x error gives the name as a string, 6.x as
   * `['name' => <name>, 'params' => [...]]`.
   *
   * @param array<mixed> $error
   *   The error the JSON Schema validator reported.
   *
   * @return string|null
   *   The constraint name, or NULL when the error does not give one.
   */
  private static function constraintName(array $error): ?string {
    $constraint = $error['constraint'] ?? NULL;
    $name = is_array($constraint) ? ($constraint['name'] ?? NULL) : $constraint;
    return is_string($name) && $name !== '' ? $name : NULL;
  }

}
