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
   * Form API elements for scalar schema types, keyed by type or type:format.
   *
   * To map another type or format, add one entry here; a 'type:format' key
   * wins over the plain type. FieldBuilder::supportedElementTypes() derives
   * its list from these entries, so the new element is not replaced by the
   * "cannot be edited" item, and ElementMappingTest checks every entry.
   *
   * Drupal has no 'integer' element. A number input whose step is one is the
   * native spelling of the constraint: the browser rejects a fractional value
   * before the form is submitted, and the value still arrives as an integer
   * because ScalarCaster::cast() turns a numeric string that spells a whole
   * number without a dot into an int.
   */
  public const SCALAR_ELEMENTS = [
    'boolean' => ['#type' => 'checkbox'],
    'integer' => ['#type' => 'number', '#step' => 1],
    'number' => ['#type' => 'number'],
    'string' => ['#type' => 'textfield'],
    'string:email' => ['#type' => 'email'],
  ];

  /**
   * Lists the element types the scalar mappings produce.
   *
   * @return list<string>
   *   The element types.
   */
  public static function scalarElementTypes(): array {
    return array_values(array_unique(array_column(self::SCALAR_ELEMENTS, '#type')));
  }

  /**
   * Creates the start of a control.
   *
   * @param \SchemaForms\Drupal\FieldContext $context
   *   The field context.
   * @param string $type
   *   The type the control is built for. A type with no scalar mapping
   *   becomes the '#type' as it is, for the compound builders, a 'ui:widget'
   *   or the unsupported check to deal with.
   *
   * @return array<int|string, mixed>
   *   The element.
   */
  public static function create(FieldContext $context, string $type): array {
    $schema = $context->schema;
    $hints = $context->hints;
    $format = is_string($schema->format ?? NULL) ? $schema->format : '';
    $element = [
      '#title' => Labels::markup($hints['ui:title'] ?? $schema->title ?? Labels::humanName($context->name)),
      '#type' => $type,
      RenderKeys::PROP_NAME => $context->name,
      ...(self::SCALAR_ELEMENTS[$type . ':' . $format] ?? self::SCALAR_ELEMENTS[$type] ?? []),
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
