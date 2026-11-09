<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Exception\ExceptionInterface;
use SchemaForms\FormGeneratorInterface;
use SchemaForms\JsonSchemaFormValidator;
use Shaper\Transformation\TransformationBase;
use Shaper\Util\Context;
use Shaper\Validator\ValidateableInterface;

/**
 * Generates Drupal Form API forms from JSON-Schema documents.
 *
 * Call transform() to build a form. The public methods other than
 * transform(), getInputValidator() and getOutputValidator() are Form API
 * callbacks that the generated render arrays name, plus the widget state
 * accessors. Each delegates to one internal class. They keep their names
 * and signatures because cached forms built by earlier releases still name
 * them; host code does not call the callbacks.
 *
 * The static callbacks are the only place that creates collaborators with
 * `new`. Form API calls them with nothing but the form and its state, and a
 * library has no service container to ask. What they create is stateless,
 * or a view of the form state they receive, such as WidgetStateStorage, so
 * the form state is their one dependency and tests pass in their own.
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
   *
   * Not promoted: a generator that a form cached before this property
   * existed unserializes with NULL here instead of an uninitialized
   * property.
   */
  private ?AcceptedSchemas $accepted = NULL;

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
   * @param \SchemaForms\Drupal\AcceptedSchemas|null $accepted
   *   Remembers the schemas that passed the input validation, or NULL for a
   *   memory with the default limit.
   */
  public function __construct(
    private readonly SchemaPreparer $schemas = new SchemaPreparer(),
    private readonly FormTreeBuilder $tree = new FormTreeBuilder(),
    private readonly SubmissionPreparer $preparer = new SubmissionPreparer(),
    private readonly SubmissionValidator $validator = new SubmissionValidator(),
    ?AcceptedSchemas $accepted = NULL,
  ) {
    $this->accepted = $accepted;
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
   *   The options: 'ui_hints', 'form_state', 'current_input' and
   *   'namespace'.
   *
   * @return array<int|string, mixed>
   *   The generated container.
   */
  public function doTransform($data, ?Context $context = NULL): array {
    try {
      $schema = $this->accepted()->register($data, $this->schemas);
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
   * Validation callbacks reference this generator, so a cached form serializes
   * it. The input validator only matters while a schema is being accepted,
   * and getInputValidator() builds a new one on demand.
   *
   * @return string[]
   *   The names of the properties to serialize.
   */
  public function __sleep(): array {
    return ['schemas', 'tree', 'preparer', 'validator', 'accepted'];
  }

  /**
   * Validates a generated container; its #element_validate callback.
   *
   * @param array<int|string, mixed> $element
   *   The generated container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateWithSchema(array &$element, FormStateInterface $form_state): void {
    $this->bridge()->validateElement($element, $form_state);
  }

  /**
   * Validates a generated container that is the form; its #validate handler.
   *
   * @param array<int|string, mixed> $form
   *   The form, which is the generated container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateFormWithSchema(array &$form, FormStateInterface $form_state): void {
    $this->bridge()->validateForm($form, $form_state);
  }

  /**
   * Limits Append validation; the #process callback of multivalue widgets.
   *
   * @param array<int|string, mixed> $element
   *   The widget, with its Form API parents assigned.
   *
   * @return array<int|string, mixed>
   *   The widget.
   */
  public static function multiValueProcess(array $element): array {
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
   */
  public static function removeOneSubmit(array $form, FormStateInterface $form_state): void {
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
   */
  public static function removeOneAjax(array $form, FormStateInterface $form_state): AjaxResponse {
    return MultivalueCallbacks::removeAjax($form, $form_state);
  }

  /**
   * Appends an item; the #submit callback of Append buttons.
   *
   * @param array<int|string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function addMoreSubmit(array $form, FormStateInterface $form_state): void {
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
   */
  public static function addMoreAjax(array $form, FormStateInterface $form_state): AjaxResponse {
    return MultivalueCallbacks::appendAjax($form, $form_state);
  }

  /**
   * Turns checked keys into a list; the #element_validate of checkboxes.
   *
   * @param array<int|string, mixed> $element
   *   The checkboxes element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
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
   */
  public static function getPropFormState(array $parents, string $prop_name, FormStateInterface $form_state, ?string $namespace = NULL): mixed {
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
   */
  public static function setPropFormState(array $parents, string $prop_name, FormStateInterface $form_state, array $prop_state, ?string $namespace = NULL): void {
    (new WidgetStateStorage($form_state))->set(new WidgetLocation($parents, $prop_name, $namespace), $prop_state);
  }

  /**
   * Builds the bridge the validation callbacks delegate to.
   *
   * It is built on demand from the injected collaborators, so a generator
   * that a form cached before the bridge existed still validates.
   *
   * @return \SchemaForms\Drupal\SchemaValidationBridge
   *   The bridge.
   */
  private function bridge(): SchemaValidationBridge {
    return new SchemaValidationBridge($this->preparer, $this->validator);
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
