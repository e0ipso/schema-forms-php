<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\Form\FormStateInterface;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Exception\ExceptionInterface;
use JsonSchema\Validator;
use SchemaForms\FormGeneratorInterface;
use SchemaForms\JsonSchemaFormValidator;
use SchemaForms\RenderArrayValidator;
use SchemaForms\SchemaReferenceResolver;
use Shaper\Transformation\TransformationBase;
use Shaper\Util\Context;
use Shaper\Validator\ValidateableInterface;

/**
 * Generates Drupal Form API forms from JSON-Schema documents.
 *
 * Call transform() to build a form. The public methods other than
 * transform(), getInputValidator() and getOutputValidator() are Form API
 * callbacks that the generated render arrays name; they are internal.
 *
 * @api
 */
final class FormGeneratorDrupal extends TransformationBase implements FormGeneratorInterface {

  /**
   * The input validator, kept so a rejection message can read its errors.
   *
   * Shaper asks for the input validator once to validate and again to build
   * the TypeError message. A new instance each time would print no errors.
   */
  private ?JsonSchemaFormValidator $inputValidator = NULL;

  /**
   * Creates the generator.
   *
   * @param \SchemaForms\Drupal\SchemaPreparer $schemas
   *   Registers the schema and checks its property names.
   * @param \SchemaForms\Drupal\FormTreeBuilder $tree
   *   Builds the generated container.
   * @param \SchemaForms\Drupal\SubmissionPreparer $preparer
   *   Cleans and casts submitted values.
   * @param \SchemaForms\Drupal\SubmissionValidator $validator
   *   Validates the prepared values against the schema.
   */
  public function __construct(
    private readonly SchemaPreparer $schemas = new SchemaPreparer(),
    private readonly FormTreeBuilder $tree = new FormTreeBuilder(),
    private readonly SubmissionPreparer $preparer = new SubmissionPreparer(),
    private readonly SubmissionValidator $validator = new SubmissionValidator(),
  ) {}

  /**
   * Builds the generated container; call transform() instead.
   *
   * Shaper declares this method protected. It stays public here because it
   * always was, but transform() is the entry point: it also validates the
   * schema and the result.
   *
   * @param mixed $data
   *   The decoded schema.
   * @param \Shaper\Util\Context|null $context
   *   The options: 'ui_hints', 'form_state', 'current_input' and
   *   'namespace'.
   *
   * @return array<int|string, mixed>
   *   The generated container.
   *
   * @internal
   */
  public function doTransform($data, ?Context $context = NULL): array {
    try {
      $schema = $this->schemas->prepare($data);
    }
    catch (ExceptionInterface $e) {
      return $this->withValidation([
        '#type' => 'container',
        '#tree' => TRUE,
        RenderKeys::SCHEMA => $data,
        '#markup' => ValidationMessages::unsupportedReference(),
      ]);
    }
    return $this->withValidation($this->tree->build($schema, $context ?? new Context()));
  }

  /**
   * {@inheritdoc}
   */
  public function getInputValidator(): ValidateableInterface {
    $this->inputValidator ??= new JsonSchemaFormValidator(new Validator(SchemaReferenceResolver::createFactory()), Constraint::CHECK_MODE_TYPE_CAST);
    return $this->inputValidator;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputValidator(): ValidateableInterface {
    return new RenderArrayValidator();
  }

  /**
   * Keeps the input validator out of serialized forms.
   *
   * Validation callbacks reference this generator, so a cached form serializes
   * it. An unserialized JsonSchemaFormValidator would lose the restricted
   * reference resolver; a fresh one is built on the next request instead.
   *
   * @return string[]
   *   The names of the properties to serialize.
   */
  public function __sleep(): array {
    return ['schemas', 'tree', 'preparer', 'validator'];
  }

  /**
   * Validates a generated container against its schema.
   *
   * The #element_validate callback of the generated container.
   *
   * @param array<int|string, mixed> $element
   *   The generated container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @internal
   */
  public function validateWithSchema(array &$element, FormStateInterface $form_state): void {
    $schema = $element[RenderKeys::SCHEMA] ?? NULL;
    if (!is_object($schema)) {
      return;
    }
    try {
      // One restricted factory serves casting and validation alike.
      $factory = SchemaReferenceResolver::createFactory($schema);
    }
    catch (ExceptionInterface $e) {
      $form_state->setError($element, ValidationMessages::unsupportedReference());
      return;
    }
    $registered = SchemaReferenceResolver::registeredSchema($factory);
    // Validation reads the value preparation stored, so it checks exactly
    // what the submit handlers receive.
    if ($this->preparer->prepare($element, $form_state, $registered, $factory)) {
      $this->validator->validate($element, $form_state, $registered, $factory);
    }
  }

  /**
   * Validates a generated container that is the form itself.
   *
   * The form-level #validate callback of the generated container. The form's
   * values also hold Drupal's own keys, such as form_build_id and form_id,
   * and the values of elements the module added next to the generated ones.
   * Only the schema's properties are validated, so those keys cannot fail a
   * schema that forbids additional properties. The other values are put
   * back afterwards.
   *
   * @param array<int|string, mixed> $form
   *   The form, which is the generated container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @internal
   */
  public function validateFormWithSchema(array &$form, FormStateInterface $form_state): void {
    $schema = $form[RenderKeys::SCHEMA] ?? NULL;
    if (!is_object($schema)) {
      return;
    }
    $values = $form_state->getValues();
    $properties = $schema->properties ?? [];
    $schema_keys = array_flip(array_keys(is_object($properties) || is_array($properties) ? (array) $properties : []));
    $form_state->setValues(array_intersect_key($values, $schema_keys));
    try {
      $this->validateWithSchema($form, $form_state);
    }
    finally {
      $form_state->setValues($form_state->getValues() + array_diff_key($values, $schema_keys));
    }
  }

  /**
   * Limits Append validation to the widget's final form value path.
   *
   * The #process callback of multivalue widgets.
   *
   * @param array<int|string, mixed> $element
   *   The widget, with its Form API parents assigned.
   *
   * @return array<int|string, mixed>
   *   The widget.
   *
   * @internal
   */
  public static function multiValueProcess(array $element): array {
    return MultivalueCallbacks::process($element);
  }

  /**
   * Cleans the widget value and records where the widget was built.
   *
   * The #after_build callback of multivalue widgets.
   *
   * @param array<int|string, mixed> $element
   *   The widget.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<int|string, mixed>
   *   The widget.
   *
   * @internal
   */
  public static function multiValueAfterBuild(array $element, FormStateInterface $form_state): array {
    return MultivalueCallbacks::afterBuild($element, $form_state);
  }

  /**
   * Removes an item; the #submit callback of Remove buttons.
   *
   * @param array<int|string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @internal
   */
  public static function removeOneSubmit(array $form, FormStateInterface $form_state): void {
    MultivalueCallbacks::removeSubmit($form, $form_state);
  }

  /**
   * Returns the rebuilt widget; the #ajax callback of Remove buttons.
   *
   * @param array<int|string, mixed> $form
   *   The rebuilt form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<int|string, mixed>|null
   *   The widget, or NULL when no Remove button triggered the request.
   *
   * @internal
   */
  public static function removeOneAjax(array $form, FormStateInterface $form_state): ?array {
    return MultivalueCallbacks::removeAjax($form, $form_state);
  }

  /**
   * Appends an item; the #submit callback of Append buttons.
   *
   * @param array<int|string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @internal
   */
  public static function addMoreSubmit(array $form, FormStateInterface $form_state): void {
    MultivalueCallbacks::appendSubmit($form, $form_state);
  }

  /**
   * Returns the rebuilt widget; the #ajax callback of Append buttons.
   *
   * @param array<int|string, mixed> $form
   *   The rebuilt form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<int|string, mixed>
   *   The widget.
   *
   * @internal
   */
  public static function addMoreAjax(array $form, FormStateInterface $form_state): array {
    return MultivalueCallbacks::appendAjax($form, $form_state);
  }

  /**
   * Turns checked keys into a list; the #element_validate of checkboxes.
   *
   * @param array<int|string, mixed> $element
   *   The checkboxes element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @internal
   */
  public static function validateCheckboxes(array &$element, FormStateInterface $form_state): void {
    EnumElements::validateCheckboxes($element, $form_state);
  }

  /**
   * Gets the stored state of a multivalue widget.
   *
   * @param array<mixed> $parents
   *   The schema path of the widget.
   * @param string $prop_name
   *   The widget's property name.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string|null $namespace
   *   The 'namespace' context key the container was built with, if any.
   *
   * @return mixed
   *   The stored array, with 'items_indices' and 'array_parents', or NULL.
   *
   * @internal
   */
  public static function getPropFormState(array $parents, string $prop_name, FormStateInterface $form_state, ?string $namespace = NULL): mixed {
    return WidgetStateStorage::get($form_state, $parents, $prop_name, $namespace);
  }

  /**
   * Stores the state of a multivalue widget.
   *
   * @param array<mixed> $parents
   *   The schema path of the widget.
   * @param string $prop_name
   *   The widget's property name.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<mixed> $prop_state
   *   The state to store.
   * @param string|null $namespace
   *   The 'namespace' context key the container was built with, if any.
   *
   * @internal
   */
  public static function setPropFormState(array $parents, string $prop_name, FormStateInterface $form_state, array $prop_state, ?string $namespace = NULL): void {
    WidgetStateStorage::set($form_state, $parents, $prop_name, $prop_state, $namespace);
  }

  /**
   * Adds the schema validation callbacks to a generated container.
   *
   * Drupal runs #element_validate when the container sits under a key, but
   * not on the form itself: there it runs only the form-level #validate
   * handlers, and it ignores #validate on any other element. Each placement
   * therefore runs the bridge exactly once. FormBuilder::prepareForm()
   * appends the form's own ::validateForm after the #validate handler.
   *
   * @param array<int|string, mixed> $element
   *   The generated container.
   *
   * @return array<int|string, mixed>
   *   The container.
   */
  private function withValidation(array $element): array {
    $element['#element_validate'] = [[$this, 'validateWithSchema']];
    $element['#validate'] = [[$this, 'validateFormWithSchema']];
    return $element;
  }

}
