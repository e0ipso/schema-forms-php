<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

/**
 * Starts the control of a schema with its type, labels and default value.
 *
 * @internal
 */
final class ElementScaffold {

  /**
   * Creates the start of a control.
   *
   * @param \SchemaForms\Drupal\FieldContext $context
   *   The field context.
   * @param string $type
   *   The type the control is built for. Without mapped properties it
   *   becomes the '#type' as it is, for the compound builders, a 'ui:widget'
   *   or the unsupported check to deal with.
   * @param array<string, mixed> $mapped
   *   The element properties an element mapper gave, if any.
   *
   * @return array<int|string, mixed>
   *   The element.
   */
  public static function create(FieldContext $context, string $type, array $mapped = []): array {
    $schema = $context->schema;
    $hints = $context->hints;
    $element = [
      '#title' => Labels::markup($hints['ui:title'] ?? $schema->title ?? Labels::humanName($context->name)),
      '#type' => $type,
      RenderKeys::PROP_NAME => $context->name,
      ...$mapped,
    ];
    // Saved input wins over the default, and a saved NULL shows no value.
    $default_value = $context->hasCurrentInput ? $context->currentInput : ($schema->default ?? NULL);
    if (!is_null($default_value)) {
      $element['#default_value'] = $default_value;
    }
    $description = $hints['ui:help'] ?? $schema->description ?? NULL;
    if (!empty($description)) {
      $element['#description'] = $description;
    }
    $placeholder = $hints['ui:placeholder'] ?? NULL;
    if (!empty($placeholder)) {
      $element['#placeholder'] = $placeholder;
    }
    return $element;
  }

}
