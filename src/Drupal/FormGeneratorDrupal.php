<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Exception\ExceptionInterface;
use SchemaForms\Exception\InvalidOptionException;
use SchemaForms\FormGeneratorInterface;
use SchemaForms\JsonSchemaFormValidator;
use SchemaForms\RestrictedSchemaRegistry;
use SchemaForms\SchemaRegistryInterface;
use Shaper\Transformation\TransformationBase;
use Shaper\Util\Context;
use Shaper\Validator\ValidateableInterface;

/**
 * Generates Drupal Form API forms from JSON-Schema documents.
 *
 * Call transform() to build a form. The generated render arrays name static
 * callbacks on the classes that do the work, so a cached form stores no
 * generator. The Form API callbacks still declared here are what forms
 * cached by releases before 3.2.0 name; each one is deprecated and forwards
 * to the callback generated forms name now.
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
   * The schemas that passed the input validation.
   */
  private ?AcceptedSchemas $accepted = NULL;

  /**
   * The registry of the generated forms' schemas, or NULL for the default.
   */
  private ?SchemaRegistryInterface $registry = NULL;

  /**
   * The element mappers to ask before the generator's own.
   *
   * @var list<\SchemaForms\Drupal\ElementMapperInterface>
   */
  private array $elementMappers = [];

  /**
   * Creates the generator.
   *
   * The properties are not promoted and have defaults, so a generator that a
   * form cached before they existed unserializes with the defaults.
   *
   * @param \SchemaForms\SchemaRegistryInterface|null $registry
   *   Registers each schema, so its references resolve; NULL for a
   *   RestrictedSchemaRegistry. Generated forms keep it, so validation
   *   registers the schema with it again, and a cached form serializes it.
   * @param array<mixed> $element_mappers
   *   ElementMapperInterface objects to ask, in order, before the generator's
   *   own mapper, for the element of a schema with no compound control.
   *
   * @throws \SchemaForms\Exception\InvalidOptionException
   *   When an element mapper does not implement ElementMapperInterface.
   */
  public function __construct(
    ?SchemaRegistryInterface $registry = NULL,
    array $element_mappers = [],
  ) {
    $mappers = array_values(array_filter(
      $element_mappers,
      static fn (mixed $mapper): bool => $mapper instanceof ElementMapperInterface
    ));
    if (count($mappers) !== count($element_mappers)) {
      throw new InvalidOptionException('Every element mapper must implement ' . ElementMapperInterface::class . '.');
    }
    $this->registry = $registry;
    $this->elementMappers = $mappers;
  }

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
   *   The context, whose "options" key holds a \SchemaForms\FormOptions.
   *
   * @return array<int|string, mixed>
   *   The generated container.
   */
  public function doTransform($data, ?Context $context = NULL): array {
    try {
      $schema = $this->accepted()->register($data, new SchemaPreparer($this->registry()));
    }
    catch (ExceptionInterface $e) {
      return $this->withValidation([
        '#type' => 'container',
        '#tree' => TRUE,
        RenderKeys::SCHEMA => $data,
        '#markup' => ValidationMessages::unsupportedReference(),
      ]);
    }
    $tree = new FormTreeBuilder(new FieldBuilder(new MultivalueWidgetBuilder(), $this->elementMappers));
    return $this->withValidation($tree->build($schema, ContextOptions::read($context ?? new Context())));
  }

  /**
   * {@inheritdoc}
   *
   * A schema this generator already accepted is not validated again.
   */
  public function conformsToExpectedInputShape($data, ?Context $context = NULL): bool {
    if ($this->accepted()->contains($data)) {
      return TRUE;
    }
    if (!$this->getInputValidator()->isValid($data)) {
      return FALSE;
    }
    $this->accepted()->add($data);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputValidator(): ValidateableInterface {
    $this->inputValidator ??= new JsonSchemaFormValidator(Constraint::CHECK_MODE_TYPE_CAST);
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
   * Forms cached by releases before 3.2.0 reference the generator, and a host
   * may keep one in its form object. The input validator only matters while
   * a schema is being accepted, and getInputValidator() builds a new one on
   * demand.
   *
   * @return string[]
   *   The names of the properties to serialize.
   */
  public function __sleep(): array {
    return ['accepted', 'registry', 'elementMappers'];
  }

  // phpcs:disable Drupal.Commenting.Deprecated.DeprecatedWrongSeeUrlFormat, Drupal.Semantics.FunctionTriggerError.TriggerErrorSeeUrlFormat -- The project is not on drupal.org.

  /**
   * Validates a generated container; its #element_validate callback.
   *
   * @param array<int|string, mixed> $element
   *   The generated container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0.
   *   Generated forms name SchemaValidationBridge::elementValidate() instead.
   *
   * @see https://github.com/e0ipso/schema-forms-php#upgrading
   */
  public function validateWithSchema(array &$element, FormStateInterface $form_state): void {
    @trigger_error('FormGeneratorDrupal::validateWithSchema() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. Generated forms name SchemaValidationBridge::elementValidate() instead. See https://github.com/e0ipso/schema-forms-php#upgrading', E_USER_DEPRECATED);
    SchemaValidationBridge::elementValidate($element, $form_state);
  }

  /**
   * Validates a generated container that is the form; its #validate handler.
   *
   * @param array<int|string, mixed> $form
   *   The form, which is the generated container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0.
   *   Generated forms name SchemaValidationBridge::formValidate() instead.
   *
   * @see https://github.com/e0ipso/schema-forms-php#upgrading
   */
  public function validateFormWithSchema(array &$form, FormStateInterface $form_state): void {
    @trigger_error('FormGeneratorDrupal::validateFormWithSchema() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. Generated forms name SchemaValidationBridge::formValidate() instead. See https://github.com/e0ipso/schema-forms-php#upgrading', E_USER_DEPRECATED);
    SchemaValidationBridge::formValidate($form, $form_state);
  }

  /**
   * Limits Append validation; the #process callback of multivalue widgets.
   *
   * @param array<int|string, mixed> $element
   *   The widget, with its Form API parents assigned.
   *
   * @return array<int|string, mixed>
   *   The widget.
   *
   * @deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0.
   *   Generated forms name MultivalueCallbacks::process() instead.
   *
   * @see https://github.com/e0ipso/schema-forms-php#upgrading
   */
  public static function multiValueProcess(array $element): array {
    @trigger_error('FormGeneratorDrupal::multiValueProcess() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. Generated forms name MultivalueCallbacks::process() instead. See https://github.com/e0ipso/schema-forms-php#upgrading', E_USER_DEPRECATED);
    return MultivalueCallbacks::process($element);
  }

  /**
   * Cleans the widget value; the #after_build callback of multivalue widgets.
   *
   * @param array<int|string, mixed> $element
   *   The widget.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<int|string, mixed>
   *   The widget.
   *
   * @deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0.
   *   Generated forms name MultivalueCallbacks::afterBuild() instead.
   *
   * @see https://github.com/e0ipso/schema-forms-php#upgrading
   */
  public static function multiValueAfterBuild(array $element, FormStateInterface $form_state): array {
    @trigger_error('FormGeneratorDrupal::multiValueAfterBuild() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. Generated forms name MultivalueCallbacks::afterBuild() instead. See https://github.com/e0ipso/schema-forms-php#upgrading', E_USER_DEPRECATED);
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
   * @deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0.
   *   Generated forms name MultivalueCallbacks::removeSubmit() instead.
   *
   * @see https://github.com/e0ipso/schema-forms-php#upgrading
   */
  public static function removeOneSubmit(array $form, FormStateInterface $form_state): void {
    @trigger_error('FormGeneratorDrupal::removeOneSubmit() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. Generated forms name MultivalueCallbacks::removeSubmit() instead. See https://github.com/e0ipso/schema-forms-php#upgrading', E_USER_DEPRECATED);
    MultivalueCallbacks::removeSubmit($form, $form_state);
  }

  /**
   * Replaces the widget after a removal; the #ajax callback of Remove buttons.
   *
   * @param array<int|string, mixed> $form
   *   The rebuilt form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response, which also announces the removal and moves focus. It is
   *   empty when no Remove button triggered the request.
   *
   * @deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0.
   *   Generated forms name MultivalueCallbacks::removeAjax() instead.
   *
   * @see https://github.com/e0ipso/schema-forms-php#upgrading
   */
  public static function removeOneAjax(array $form, FormStateInterface $form_state): AjaxResponse {
    @trigger_error('FormGeneratorDrupal::removeOneAjax() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. Generated forms name MultivalueCallbacks::removeAjax() instead. See https://github.com/e0ipso/schema-forms-php#upgrading', E_USER_DEPRECATED);
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
   * @deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0.
   *   Generated forms name MultivalueCallbacks::appendSubmit() instead.
   *
   * @see https://github.com/e0ipso/schema-forms-php#upgrading
   */
  public static function addMoreSubmit(array $form, FormStateInterface $form_state): void {
    @trigger_error('FormGeneratorDrupal::addMoreSubmit() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. Generated forms name MultivalueCallbacks::appendSubmit() instead. See https://github.com/e0ipso/schema-forms-php#upgrading', E_USER_DEPRECATED);
    MultivalueCallbacks::appendSubmit($form, $form_state);
  }

  /**
   * Replaces the widget after an append; the #ajax callback of Append buttons.
   *
   * @param array<int|string, mixed> $form
   *   The rebuilt form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response, which also announces the new item and moves focus to it.
   *
   * @deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0.
   *   Generated forms name MultivalueCallbacks::appendAjax() instead.
   *
   * @see https://github.com/e0ipso/schema-forms-php#upgrading
   */
  public static function addMoreAjax(array $form, FormStateInterface $form_state): AjaxResponse {
    @trigger_error('FormGeneratorDrupal::addMoreAjax() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. Generated forms name MultivalueCallbacks::appendAjax() instead. See https://github.com/e0ipso/schema-forms-php#upgrading', E_USER_DEPRECATED);
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
   * @deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0.
   *   Generated forms name EnumElements::validateCheckboxes() instead.
   *
   * @see https://github.com/e0ipso/schema-forms-php#upgrading
   */
  public static function validateCheckboxes(array &$element, FormStateInterface $form_state): void {
    @trigger_error('FormGeneratorDrupal::validateCheckboxes() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. Generated forms name EnumElements::validateCheckboxes() instead. See https://github.com/e0ipso/schema-forms-php#upgrading', E_USER_DEPRECATED);
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
   * @deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0.
   *   Use WidgetItems::read() instead.
   *
   * @see https://github.com/e0ipso/schema-forms-php#upgrading
   */
  public static function getPropFormState(array $parents, string $prop_name, FormStateInterface $form_state, ?string $namespace = NULL): mixed {
    @trigger_error('FormGeneratorDrupal::getPropFormState() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. Use WidgetItems::read() instead. See https://github.com/e0ipso/schema-forms-php#upgrading', E_USER_DEPRECATED);
    return (new WidgetStateStorage($form_state))->get(new WidgetLocation($parents, $prop_name, $namespace));
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
   * @deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0.
   *   The Append and Remove buttons keep the widget state; read it with
   *   WidgetItems::read().
   *
   * @see https://github.com/e0ipso/schema-forms-php#upgrading
   */
  public static function setPropFormState(array $parents, string $prop_name, FormStateInterface $form_state, array $prop_state, ?string $namespace = NULL): void {
    @trigger_error('FormGeneratorDrupal::setPropFormState() is deprecated in schema_forms:3.2.0 and is removed from schema_forms:4.0.0. The Append and Remove buttons keep the widget state; read it with WidgetItems::read(). See https://github.com/e0ipso/schema-forms-php#upgrading', E_USER_DEPRECATED);
    (new WidgetStateStorage($form_state))->set(new WidgetLocation($parents, $prop_name, $namespace), $prop_state);
  }

  // phpcs:enable

  /**
   * Gets the schema registry.
   *
   * @return \SchemaForms\SchemaRegistryInterface
   *   The registry the caller gave, or a RestrictedSchemaRegistry.
   */
  private function registry(): SchemaRegistryInterface {
    return $this->registry ?? new RestrictedSchemaRegistry();
  }

  /**
   * Gets the memory of accepted schemas.
   *
   * @return \SchemaForms\Drupal\AcceptedSchemas
   *   The memory.
   */
  private function accepted(): AcceptedSchemas {
    $this->accepted ??= new AcceptedSchemas();
    return $this->accepted;
  }

  /**
   * Adds the schema validation callbacks to a generated container.
   *
   * Drupal runs #element_validate when the container sits under a key, but
   * not on the form itself: there it runs only the form-level #validate
   * handlers, and it ignores #validate on any other element. Each placement
   * therefore runs the bridge exactly once. FormBuilder::prepareForm()
   * appends the form's own ::validateForm after the #validate handler. A
   * registry the caller gave goes along, so validation registers the schema
   * the same way.
   *
   * @param array<int|string, mixed> $element
   *   The generated container.
   *
   * @return array<int|string, mixed>
   *   The container.
   */
  private function withValidation(array $element): array {
    $element['#element_validate'] = [[SchemaValidationBridge::class, 'elementValidate']];
    $element['#validate'] = [[SchemaValidationBridge::class, 'formValidate']];
    if ($this->registry !== NULL) {
      $element[RenderKeys::REGISTRY] = $this->registry;
    }
    return $element;
  }

}
