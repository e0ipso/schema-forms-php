<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Puts JSON Schema violations on the generated elements they belong to.
 *
 * @internal
 */
final class ErrorElementMapper {

  /**
   * Sets the form errors for a list of violations.
   *
   * @param array<int|string, mixed> $root
   *   The generated root container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<int, array<string, mixed>> $errors
   *   The errors the JSON Schema validator reported.
   */
  public static function setErrors(array $root, FormStateInterface $form_state, array $errors): void {
    if ($errors === []) {
      return;
    }
    $targets = self::elementsByPointer($root);
    $mapped = array_filter(
      $errors,
      static fn (array $error): bool => isset($targets[$error['pointer'] ?? ''])
    );
    foreach ($mapped as $error) {
      $target = $targets[$error['pointer']];
      $form_state->setError($target, ValidationMessages::elementViolation($target['#title'] ?? '', self::violation($error)));
    }
    $unmapped = array_diff_key($errors, $mapped);
    if ($unmapped === []) {
      return;
    }
    $form_state->setError($root, ValidationMessages::invalidData(array_values(array_map([self::class, 'violation'], $unmapped))));
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
      array_map(static fn (ElementDataPath $pair): string => self::pointer($pair->path), $pairs),
      array_map(static fn (ElementDataPath $pair): array => $pair->element, $pairs)
    );
  }

  /**
   * Builds the JSON pointer of a value path.
   *
   * @param list<int|string> $path
   *   The value path.
   *
   * @return string
   *   The pointer, escaped per segment the way both supported json-schema
   *   majors escape the pointers of their errors.
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
   * @param array<string, mixed> $error
   *   The error the JSON Schema validator reported.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The description.
   */
  private static function violation(array $error): MarkupInterface|string {
    return ValidationMessages::violation((string) $error['message'], self::constraintName($error));
  }

  /**
   * Names the constraint a validation error violated.
   *
   * `justinrainbow/json-schema` changed the shape of this value in 6.0: it was
   * the constraint's name as a plain string, and it is now
   * `['name' => <name>, 'params' => [...]]`. This library supports
   * `^5.2 || ^6.3`, so both shapes reach here and neither may be assumed.
   *
   * @param array<string, mixed> $error
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
