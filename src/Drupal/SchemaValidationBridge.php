<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\Form\FormStateInterface;
use JsonSchema\Exception\ExceptionInterface;
use SchemaForms\RestrictedSchemaRegistry;
use SchemaForms\SchemaRegistryInterface;

/**
 * Validates a submitted generated container against its schema.
 *
 * Drupal runs #element_validate when the container sits under a key, but on
 * the form itself it runs only the form-level #validate handlers. The
 * generated container names elementValidate() and formValidate() for the two
 * placements. They are static, so a cached form stores only their names,
 * and they build the bridge from the container on each request.
 *
 * @internal
 */
final class SchemaValidationBridge {

  /**
   * Creates the bridge.
   *
   * @param \SchemaForms\Drupal\SubmissionPreparer $preparer
   *   Cleans and casts submitted values.
   * @param \SchemaForms\Drupal\SubmissionValidator $validator
   *   Validates the prepared values against the schema.
   * @param \SchemaForms\SchemaRegistryInterface $registry
   *   Registers the schema, so its references resolve.
   */
  public function __construct(
    private readonly SubmissionPreparer $preparer = new SubmissionPreparer(),
    private readonly SubmissionValidator $validator = new SubmissionValidator(),
    private readonly SchemaRegistryInterface $registry = new RestrictedSchemaRegistry(),
  ) {}

  /**
   * Validates a generated container; its #element_validate callback.
   *
   * @param array<int|string, mixed> $element
   *   The generated container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function elementValidate(array &$element, FormStateInterface $form_state): void {
    self::forContainer($element)->validateElement($element, $form_state);
  }

  /**
   * Validates a generated container that is the form; its #validate handler.
   *
   * @param array<int|string, mixed> $form
   *   The form, which is the generated container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function formValidate(array &$form, FormStateInterface $form_state): void {
    self::forContainer($form)->validateForm($form, $form_state);
  }

  /**
   * Prepares and validates the value of a generated container.
   *
   * @param array<int|string, mixed> $element
   *   The generated container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateElement(array $element, FormStateInterface $form_state): void {
    $schema = $element[RenderKeys::SCHEMA] ?? NULL;
    if (!is_object($schema)) {
      return;
    }
    try {
      // One registration serves casting and validation alike.
      $registered = $this->registry->register($schema);
    }
    catch (ExceptionInterface $e) {
      $form_state->setError($element, ValidationMessages::unsupportedReference());
      return;
    }
    // Validation reads the value preparation stored, so it checks exactly
    // what the submit handlers receive.
    if ($this->preparer->prepare($element, $form_state, $registered)) {
      $this->validator->validate($element, $form_state, $registered);
    }
  }

  /**
   * Prepares and validates a generated container that is the form itself.
   *
   * The form's values also hold Drupal's own keys, such as form_build_id and
   * form_id, and the values of elements the module added next to the
   * generated ones. Only the schema's properties are validated, so those
   * keys cannot fail a schema that forbids additional properties. The other
   * values are put back afterwards.
   *
   * @param array<int|string, mixed> $form
   *   The form, which is the generated container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateForm(array $form, FormStateInterface $form_state): void {
    $schema = $form[RenderKeys::SCHEMA] ?? NULL;
    if (!is_object($schema)) {
      return;
    }
    $values = $form_state->getValues();
    $properties = $schema->properties ?? [];
    $schema_keys = array_flip(array_keys(is_object($properties) || is_array($properties) ? (array) $properties : []));
    $form_state->setValues(array_intersect_key($values, $schema_keys));
    try {
      $this->validateElement($form, $form_state);
    }
    finally {
      $form_state->setValues($form_state->getValues() + array_diff_key($values, $schema_keys));
    }
  }

  /**
   * Builds the bridge for a generated container.
   *
   * @param array<int|string, mixed> $container
   *   The generated container.
   *
   * @return self
   *   A bridge with the registry the generator was given, if any.
   */
  private static function forContainer(array $container): self {
    $registry = $container[RenderKeys::REGISTRY] ?? NULL;
    return new self(registry: $registry instanceof SchemaRegistryInterface ? $registry : new RestrictedSchemaRegistry());
  }

}
