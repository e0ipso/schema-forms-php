<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Shaper\Util\Context;

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
   * @param \Shaper\Util\Context $context
   *   The caller's options: 'ui_hints', 'form_state', 'current_input' and
   *   'namespace'.
   *
   * @return array<int|string, mixed>
   *   The container, without its validation callbacks.
   *
   * @throws \InvalidArgumentException
   *   When the namespace is not a non-empty string.
   */
  public function build(\stdClass $schema, Context $context): array {
    $hints = is_array($context['ui_hints'] ?? NULL) ? $context['ui_hints'] : [];
    $form_state = $context['form_state'] ?? NULL;
    $input = SavedInput::normalize($context['current_input'] ?? []);
    $root = new FieldContext(
      schema: $schema,
      name: '',
      parents: [],
      hints: $hints,
      widgetStates: new WidgetStateStorage($form_state instanceof FormStateInterface ? $form_state : new FormState()),
      currentInput: is_array($input) ? $input : [],
      hasCurrentInput: FALSE,
      namespace: self::namespace($context),
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
   * Reads the namespace that keeps this container apart from others.
   *
   * @param \Shaper\Util\Context $context
   *   The caller's options.
   *
   * @return string|null
   *   The namespace, or NULL when the caller gave none.
   *
   * @throws \InvalidArgumentException
   *   When the namespace is not a non-empty string.
   */
  private static function namespace(Context $context): ?string {
    $namespace = $context['namespace'] ?? NULL;
    if ($namespace === NULL) {
      return NULL;
    }
    if (!is_string($namespace) || $namespace === '') {
      throw new \InvalidArgumentException('The "namespace" context key must be a non-empty string.');
    }
    return $namespace;
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
