<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use SchemaForms\FormOptions;

/**
 * Builds the generated root container and the controls of its properties.
 *
 * @internal
 */
final class FormTreeBuilder {

  /**
   * Creates the builder.
   *
   * @param \SchemaForms\Drupal\FieldBuilder $fields
   *   Builds the control of each property.
   */
  public function __construct(
    private readonly FieldBuilder $fields = new FieldBuilder(),
  ) {}

  /**
   * Builds the container for a registered schema.
   *
   * @param \stdClass $schema
   *   The registered root object schema.
   * @param \SchemaForms\FormOptions $options
   *   The caller's options.
   *
   * @return array<int|string, mixed>
   *   The container, without its validation callbacks.
   */
  public function build(\stdClass $schema, FormOptions $options): array {
    $form_state = $options->formState;
    $input = SavedInput::normalize($options->currentInput);
    $root = new FieldContext(
      schema: $schema,
      name: '',
      parents: [],
      hints: $options->uiHints,
      widgetStates: new WidgetStateStorage($form_state instanceof FormStateInterface ? $form_state : new FormState()),
      currentInput: is_array($input) ? $input : [],
      hasCurrentInput: FALSE,
      namespace: $options->namespace,
    );
    $element = [
      '#type' => 'container',
      // Nested objects and multivalue widgets need schema-shaped value paths
      // even under a parent form whose #tree is FALSE.
      '#tree' => TRUE,
      // The validation callbacks read the schema from here.
      RenderKeys::SCHEMA => $schema,
    ] + $this->properties($root);
    return RequiredMarker::apply(ButtonNames::deduplicate($element));
  }

  /**
   * Builds the controls of the root properties.
   *
   * @param \SchemaForms\Drupal\FieldContext $root
   *   The context of the root object.
   *
   * @return array<int|string, array<int|string, mixed>>
   *   The controls, keyed by property name.
   */
  private function properties(FieldContext $root): array {
    // A root object schema may omit 'properties'; that is an empty form.
    $properties = is_object($root->schema->properties ?? NULL) ? (array) $root->schema->properties : [];
    $schemas = array_filter($properties, static fn (mixed $schema): bool => $schema instanceof \stdClass);
    $keys = array_keys($schemas);
    $controls = array_map(
      fn (\stdClass $schema, int|string $key): array => $this->fields->build($root->child($key, $schema, self::hints($root->hints, $key))),
      $schemas,
      $keys
    );
    return array_combine($keys, $controls);
  }

  /**
   * Gets the UI hints of a root property.
   *
   * @param array<mixed> $hints
   *   The UI schema, keyed by property name.
   * @param int|string $key
   *   The property name.
   *
   * @return array<mixed>
   *   The property's hints.
   */
  private static function hints(array $hints, int|string $key): array {
    return is_array($hints[$key] ?? NULL) ? $hints[$key] : [];
  }

}
