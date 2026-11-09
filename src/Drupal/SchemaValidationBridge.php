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
 * The static elementValidate() and formValidate() serve the two placements
 * of the container, so a cached form stores only their names.
 *
 * @internal
 */
final class SchemaValidationBridge {

  /**
   * Creates the bridge.
   *
   * @param \SchemaForms\SchemaRegistryInterface $registry
   *   Registers the schema, so its references resolve.
   */
  public function __construct(
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
      $registered = $this->registry->register($schema);
    }
    catch (ExceptionInterface $e) {
      $form_state->setError($element, ValidationMessages::unsupportedReference());
      return;
    }
    // Validation reads the value preparation stored, so it checks exactly
    // what the submit handlers receive.
    if ((new SubmissionPreparer())->prepare($element, $form_state, $registered)) {
      (new SubmissionValidator())->validate($element, $form_state, $registered);
    }
  }

  /**
   * Prepares and validates a generated container that is the form itself.
   *
   * Only the schema's properties are validated, so Drupal's own keys, such
   * as form_id, cannot fail a schema that forbids additional properties.
   * The other values are put back afterwards.
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
   *   The bridge.
   */
  private static function forContainer(array $container): self {
    $registry = $container[RenderKeys::REGISTRY] ?? NULL;
    return new self($registry instanceof SchemaRegistryInterface ? $registry : new RestrictedSchemaRegistry());
  }

}
