<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use SchemaForms\FormOptions;
use SchemaForms\SchemaType;

/**
 * Builds the generated root container and the Form API control of a schema.
 *
 * @internal
 */
final class FieldBuilder {

  /**
   * The element type of an object and of a multivalue widget.
   */
  private const DETAILS = 'details';

  /**
   * The element types the compound builders produce.
   */
  private const COMPOUND_ELEMENT_TYPES = [EnumElements::RADIOS, EnumElements::CHECKBOXES, self::DETAILS];

  /**
   * The element mappers, the default one last.
   *
   * @var list<\SchemaForms\Drupal\ElementMapperInterface>
   */
  private readonly array $mappers;

  /**
   * Creates the builder.
   *
   * @param \SchemaForms\Drupal\MultivalueWidgetBuilder $multivalue
   *   Builds the widgets of arrays with one item schema.
   * @param list<\SchemaForms\Drupal\ElementMapperInterface> $element_mappers
   *   Mappers to ask before ScalarElementMapper.
   */
  public function __construct(
    private readonly MultivalueWidgetBuilder $multivalue = new MultivalueWidgetBuilder(),
    array $element_mappers = [],
  ) {
    $this->mappers = [...$element_mappers, new ScalarElementMapper()];
  }

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
  public function buildForm(\stdClass $schema, FormOptions $options): array {
    $form_state = $options->formState;
    $input = self::normalize($options->currentInput);
    $root = new FieldContext(
      schema: $schema,
      name: '',
      parents: [],
      hints: $options->uiHints,
      formState: $form_state instanceof FormStateInterface ? $form_state : new FormState(),
      currentInput: is_array($input) ? $input : [],
      hasCurrentInput: FALSE,
      namespace: $options->namespace,
    );
    // A root object schema may omit 'properties'; that is an empty form.
    $properties = is_object($schema->properties ?? NULL) ? (array) $schema->properties : [];
    $schemas = array_filter($properties, static fn (mixed $property): bool => $property instanceof \stdClass);
    $keys = array_keys($schemas);
    $controls = array_map(
      fn (\stdClass $property, int|string $key): array => $this->build($root->child($key, $property, is_array($options->uiHints[$key] ?? NULL) ? $options->uiHints[$key] : [])),
      $schemas,
      $keys
    );
    $element = [
      '#type' => 'container',
      // Nested objects and multivalue widgets need schema-shaped value paths
      // even under a parent form whose #tree is FALSE.
      '#tree' => TRUE,
      // The validation callbacks read the schema from here.
      RenderKeys::SCHEMA => $schema,
    ] + array_combine($keys, $controls);
    return RequiredMarker::apply(ButtonNames::deduplicate($element));
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
    $mapped = SchemaTypes::isCompound($type) ? [] : $this->map($context->schema, $type);
    $element = self::scaffold($context, $type, $mapped);
    $element[RenderKeys::PROP_PARENTS] = $context->parents;
    if (property_exists($context->schema, 'const')) {
      return $this->constant($element, $context->schema->const);
    }
    $element['#disabled'] = !$context->enabled;
    // Core never reads #visible, so it is deprecated for removal in 4.0.0.
    // Host code may still read it, and a render key cannot warn them.
    $element['#visible'] = (bool) ($context->hints['ui:visible'] ?? TRUE);
    if (!$element['#visible']) {
      // Form API submits an inaccessible element's default value and ignores
      // its input, so the saved value survives.
      $element['#access'] = FALSE;
    }
    if ($saved_scalar && $type === $guessed) {
      $element = self::unsupportedItem($element, $type, ValidationMessages::mismatchedSavedValue($type));
      $element[RenderKeys::FIXED_VALUE] = $context->currentInput;
      return $element;
    }
    if (EnumElements::hasStructuredMember($context->schema, $type)) {
      return $this->unsupported($element, $context, $type);
    }
    $element = $this->typedElement($element, $this->withCompoundDefault($context, $type), $type);
    if (!$this->isSupported($element, $context->hints, $mapped)) {
      return $this->unsupported($element, $context, $type);
    }
    // An editable control cannot show NULL, so an untouched one would submit
    // a blank value instead. Record the saved NULL to restore it on submit.
    if ($context->hasCurrentInput && $context->currentInput === NULL && in_array('null', SchemaType::listOf($context->schema), TRUE)) {
      $element[RenderKeys::SAVED_NULL] = TRUE;
    }
    return $element;
  }

  /**
   * Starts a control with its type, labels and default value.
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
  private static function scaffold(FieldContext $context, string $type, array $mapped): array {
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
    unset($element['#default_value']);
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
    $default = self::normalize($context->schema->default);
    // A union may select a compound widget but supply a scalar default.
    return $context->withCurrentInput(is_array($default) ? $default : NULL);
  }

  /**
   * Builds the parts of the control that depend on the schema keywords.
   *
   * An enum makes a choice first, then an array or an object builds its
   * sub-controls over it.
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
      return self::unsupportedItem($element, $type);
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
    // Core reads #default_value only on inputs. The controls inside show the
    // saved value; a copy here would store the subtree in the cached form
    // once more for each level.
    unset($element['#default_value']);
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
   * Asks the element mappers for the properties of a control.
   *
   * @param \stdClass $schema
   *   The schema.
   * @param string $type
   *   The type the control is built for.
   *
   * @return array<string, mixed>
   *   The first answer, or an empty array when no mapper handles the schema.
   */
  private function map(\stdClass $schema, string $type): array {
    $answers = array_filter(array_map(
      static fn (ElementMapperInterface $mapper): ?array => $mapper->map($schema, $type),
      $this->mappers
    ));
    return reset($answers) ?: [];
  }

  /**
   * Decides whether the generator built the element on purpose.
   *
   * Otherwise a JSON Schema type reached '#type' unchanged, and no JSON
   * Schema type is a Drupal element: ElementInfoManager::getInfo() returns
   * an empty array for an unknown type, so the element renders as an empty
   * string with no exception, no warning and no log entry. The property
   * would then look exactly like one the schema never declared.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param array<mixed> $hints
   *   The UI hints.
   * @param array<string, mixed> $mapped
   *   The properties an element mapper gave the element.
   *
   * @return bool
   *   TRUE for a supported element. A 'ui:widget' names an element the caller
   *   picked deliberately, so it is taken at face value, and so is the
   *   element a mapper picked.
   */
  private function isSupported(array $element, array $hints, array $mapped): bool {
    $type = $element['#type'] ?? NULL;
    return SchemaTypes::widget($hints) !== NULL
      || in_array($type, self::COMPOUND_ELEMENT_TYPES, TRUE)
      || ($type !== NULL && $type === ($mapped['#type'] ?? NULL));
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
    $element = self::unsupportedItem($element, $type);
    if ($context->hasCurrentInput) {
      $element[RenderKeys::FIXED_VALUE] = $context->currentInput;
      return $element;
    }
    if (property_exists($context->schema, 'default')) {
      $element[RenderKeys::FIXED_VALUE] = $context->schema->default;
    }
    return $element;
  }

  /**
   * Turns an element into a disabled item that explains why.
   *
   * A property with no control and no message would look exactly like one
   * the schema never declared.
   *
   * @param array<int|string, mixed> $element
   *   The element.
   * @param string $type
   *   The type that could not be turned into a control.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $reason
   *   The explanation, when it is not the missing element for the type.
   *
   * @return array<int|string, mixed>
   *   The element.
   */
  private static function unsupportedItem(array $element, string $type, ?TranslatableMarkup $reason = NULL): array {
    $element['#type'] = 'item';
    // An 'item' would submit an empty string, which casting and validation
    // would then reject, so the form could never be saved. Any saved value
    // or default is supplied separately before validation.
    $element['#input'] = FALSE;
    $element['#markup'] = $reason ?? ValidationMessages::unsupportedType($type);
    $element['#disabled'] = TRUE;
    unset($element['#default_value'], $element['#placeholder']);
    return $element;
  }

  /**
   * Converts decoded objects to arrays, recursively.
   *
   * Callers may pass saved values as decoded objects or associative arrays,
   * and child controls look their value up by key either way.
   *
   * @param mixed $value
   *   The value.
   *
   * @return mixed
   *   The value with every object and array as an array.
   */
  private static function normalize(mixed $value): mixed {
    if (!is_array($value) && !$value instanceof \stdClass) {
      return $value;
    }
    return array_map([self::class, 'normalize'], (array) $value);
  }

}
