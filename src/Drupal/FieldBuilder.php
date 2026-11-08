<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

/**
 * Builds the Form API control for one schema.
 *
 * @internal
 */
final class FieldBuilder {

  /**
   * The element type of an object and of a multivalue widget.
   */
  private const DETAILS = 'details';

  /**
   * Creates the builder.
   *
   * @param \SchemaForms\Drupal\MultivalueWidgetBuilder $multivalue
   *   Builds the widgets of arrays with one item schema.
   */
  public function __construct(
    private readonly MultivalueWidgetBuilder $multivalue = new MultivalueWidgetBuilder(),
  ) {}

  /**
   * Lists the element types this builder produces on purpose.
   *
   * Any other value means a JSON Schema type reached '#type' unchanged, and
   * no JSON Schema type is a Drupal element: ElementInfoManager::getInfo()
   * returns an empty array for an unknown type, so the element renders as an
   * empty string with no exception, no warning and no log entry. The
   * property then looks exactly like one the schema never declared, so such
   * an element is replaced by an explanation instead. The list is derived
   * from the mappings, so a new mapping is supported as soon as it exists.
   *
   * @return list<string>
   *   The element types.
   */
  public static function supportedElementTypes(): array {
    return [
      ...ElementScaffold::scalarElementTypes(),
      EnumElements::RADIOS,
      EnumElements::CHECKBOXES,
      self::DETAILS,
    ];
  }

  /**
   * Builds the control for a schema.
   *
   * @param \SchemaForms\Drupal\FieldContext $context
   *   The field context.
   *
   * @return array<int|string, mixed>
   *   The form element.
   */
  public function build(FieldContext $context): array {
    $context = $context->withOwnEnabledHint();
    $guessed = SchemaTypes::guess($context->schema, $context->hints);
    // A saved scalar cannot fill a compound control, but a union type may
    // offer a control that shows it.
    $saved_scalar = SchemaTypes::isCompound($guessed) && $context->hasCurrentInput
      && $context->currentInput !== NULL && !is_array($context->currentInput);
    $type = $saved_scalar ? SchemaTypes::forScalar($context->schema, $context->currentInput) ?? $guessed : $guessed;
    $element = ElementScaffold::create($context, $type);
    $element[RenderKeys::PROP_PARENTS] = $context->parents;
    if (property_exists($context->schema, 'const')) {
      return $this->constant($element, $context->schema->const);
    }
    $element['#disabled'] = !$context->enabled;
    $element['#visible'] = (bool) ($context->hints['ui:visible'] ?? TRUE);
    if (!$element['#visible']) {
      // Core never reads #visible. Form API submits an inaccessible element's
      // default value and ignores its input, so the saved value survives.
      $element['#access'] = FALSE;
    }
    if ($saved_scalar && $type === $guessed) {
      $element = UnsupportedElement::apply($element, $type, ValidationMessages::mismatchedSavedValue($type));
      $element[RenderKeys::FIXED_VALUE] = $context->currentInput;
      return $element;
    }
    if (EnumElements::hasStructuredMember($context->schema, $type)) {
      return $this->unsupported($element, $context, $type);
    }
    $element = $this->typedElement($element, $this->withCompoundDefault($context, $type), $type);
    if (!$this->isSupported($element, $context->hints)) {
      return $this->unsupported($element, $context, $type);
    }
    // An editable control cannot show NULL, so an untouched one would submit
    // a blank value instead. Record the saved NULL to restore it on submit.
    if ($context->hasCurrentInput && $context->currentInput === NULL && in_array('null', (array) ($context->schema->type ?? []), TRUE)) {
      $element[RenderKeys::SAVED_NULL] = TRUE;
    }
    return $element;
  }

  /**
   * Turns the element into the plain text of a constant.
   *
   * An item element shows the title as a label next to the text; without a
   * type the text would render with no label at all.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param mixed $value
   *   The constant.
   *
   * @return array<int|string, mixed>
   *   The element, which collects no input.
   */
  private function constant(array $element, mixed $value): array {
    $element['#type'] = 'item';
    $element['#plain_text'] = is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR);
    $element['#input'] = FALSE;
    $element[RenderKeys::FIXED_VALUE] = $value;
    return $element;
  }

  /**
   * Lets the default of a compound schema fill the controls inside it.
   *
   * @param \SchemaForms\Drupal\FieldContext $context
   *   The field context.
   * @param string $type
   *   The type the control is built for.
   *
   * @return \SchemaForms\Drupal\FieldContext
   *   The context, showing the default when no value was saved.
   */
  private function withCompoundDefault(FieldContext $context, string $type): FieldContext {
    if ($context->hasCurrentInput || !SchemaTypes::isCompound($type) || !property_exists($context->schema, 'default')) {
      return $context;
    }
    $default = SavedInput::normalize($context->schema->default);
    // A union may select a compound widget but supply a scalar default.
    return $context->withCurrentInput(is_array($default) ? $default : NULL);
  }

  /**
   * Builds the parts of the control that depend on the schema keywords.
   *
   * The steps apply in order: an enum makes a choice, then an array or an
   * object builds its sub-controls over it.
   *
   * @param array<int|string, mixed> $element
   *   The scaffolded element.
   * @param \SchemaForms\Drupal\FieldContext $context
   *   The field context.
   * @param string $type
   *   The type the control is built for.
   *
   * @return array<int|string, mixed>
   *   The element.
   */
  private function typedElement(array $element, FieldContext $context, string $type): array {
    $element = EnumElements::radios($element, $context->schema, $context->hints);
    return match ($type) {
      'array' => $this->arrayElement($element, $context, $type),
      'object' => $this->objectElement($element, $context),
      default => $element,
    };
  }

  /**
   * Builds the control of an array.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param \SchemaForms\Drupal\FieldContext $context
   *   The field context.
   * @param string $type
   *   The type the control is built for.
   *
   * @return array<int|string, mixed>
   *   The element.
   */
  private function arrayElement(array $element, FieldContext $context, string $type): array {
    $items = $context->schema->items ?? NULL;
    // Draft-04 makes 'items' optional and also allows a list of schemas.
    // Neither gives one schema for every item, so no item control exists.
    if (!$items instanceof \stdClass) {
      return UnsupportedElement::apply($element, $type);
    }
    if (empty($items->enum) || !is_array($items->enum)) {
      return $this->multivalue->build($element, $context, $items, $this->build(...));
    }
    return EnumElements::checkboxes($element, $items->enum, $context->hints);
  }

  /**
   * Builds the control of an object, with one control per property.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param \SchemaForms\Drupal\FieldContext $context
   *   The field context.
   *
   * @return array<int|string, mixed>
   *   The element. An object without properties is left for the unsupported
   *   check.
   */
  private function objectElement(array $element, FieldContext $context): array {
    $properties = $context->schema->properties ?? NULL;
    if (empty($properties) || !is_object($properties)) {
      return $element;
    }
    $element['#type'] = self::DETAILS;
    $element['#open'] = TRUE;
    $schemas = array_filter((array) $properties, static fn (mixed $schema): bool => $schema instanceof \stdClass);
    // The paths of nested properties have always used string names, even
    // where the array conversion makes a numeric name an integer key.
    $names = array_map('strval', array_keys($schemas));
    $required = is_array($context->schema->required ?? NULL) ? $context->schema->required : [];
    $children = array_map(
      fn (\stdClass $schema, string $name): array => RequiredMarker::nested(
        $this->build($context->child($name, $schema, self::childHints($context->hints, $name))),
        in_array($name, $required, TRUE)
      ),
      $schemas,
      $names
    );
    return $element + array_combine($names, $children);
  }

  /**
   * Gets the UI hints of a sub-property from its parent's hints.
   *
   * The parent's own hints share the array with its children's, so a child
   * named like a hint, such as "ui:title", has no hints of its own.
   *
   * @param array<mixed> $hints
   *   The parent's UI hints.
   * @param string $name
   *   The sub-property name.
   *
   * @return array<mixed>
   *   The sub-property's UI hints.
   */
  private static function childHints(array $hints, string $name): array {
    $child = str_starts_with($name, 'ui:') ? NULL : ($hints[$name] ?? NULL);
    return is_array($child) ? $child : [];
  }

  /**
   * Decides whether the generator built the element on purpose.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param array<mixed> $hints
   *   The UI hints.
   *
   * @return bool
   *   TRUE for a supported element. A 'ui:widget' names an element the caller
   *   picked deliberately, so it is taken at face value.
   */
  private function isSupported(array $element, array $hints): bool {
    return SchemaTypes::widget($hints) !== NULL
      || in_array($element['#type'] ?? '', self::supportedElementTypes(), TRUE);
  }

  /**
   * Replaces the element with the "cannot be edited" item.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param \SchemaForms\Drupal\FieldContext $context
   *   The field context.
   * @param string $type
   *   The type that has no control.
   *
   * @return array<int|string, mixed>
   *   The element, which keeps a saved value or the default for submission.
   */
  private function unsupported(array $element, FieldContext $context, string $type): array {
    $element = UnsupportedElement::apply($element, $type);
    if ($context->hasCurrentInput) {
      $element[RenderKeys::FIXED_VALUE] = $context->currentInput;
      return $element;
    }
    if (property_exists($context->schema, 'default')) {
      $element[RenderKeys::FIXED_VALUE] = $context->schema->default;
    }
    return $element;
  }

}
