<?php

namespace SchemaForms\Drupal;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Constraints\Factory;
use JsonSchema\Validator;
use SchemaForms\FormGeneratorInterface;
use SchemaForms\JsonSchemaFormValidator;
use SchemaForms\RenderArrayValidator;
use Shaper\Transformation\TransformationBase;
use Shaper\Util\Context;

/**
 * Generates Drupal Form API forms from JSON-Schema documents.
 */
final class FormGeneratorDrupal extends TransformationBase implements FormGeneratorInterface {

  /**
   * The Form API element types this generator produces on purpose.
   *
   * Any other value means a JSON-Schema type reached '#type' unchanged, and no
   * JSON-Schema type is a Drupal element: ElementInfoManager::getInfo() returns
   * an empty array for an unknown type, so the element renders as an empty
   * string with no exception, no warning and no log entry. The property then
   * looks exactly like one the schema never declared. Such an element is
   * replaced by an explanation instead.
   *
   * @see \SchemaForms\Drupal\FormGeneratorDrupal::transformUnsupported()
   */
  private const SUPPORTED_ELEMENT_TYPES = [
    'checkbox',
    'checkboxes',
    'details',
    'email',
    'number',
    'radios',
    'textfield',
  ];

  /**
   * Creates the Form API element based on the JSON-Schema input.
   *
   * {@inheritdoc}
   */
  public function doTransform($data, ?Context $context = NULL) {
    $context = $context ?: new Context();
    $form_state = $context['form_state'] ?? new FormState();
    $schema = $this->getSchema($data);
    $props = $schema->properties;
    // Store the schema for the validation callbacks to access.
    $element = ['#type' => 'container', '#json_schema' => $schema];
    $ui_hints = $context['ui_hints'] ?? [];
    $current_input = $context['current_input'] ?? [];
    foreach ((array) $props as $key => $prop) {
      // If there is UI context, grab it and pass it along.
      $ui_schema_data = $ui_hints[$key] ?? [];
      $element[$key] = $this->doTransformOneField($prop, $key, [$key], $ui_schema_data, $form_state, $current_input[$key] ?? NULL);
    }
    return $this->addValidationRules($element, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function getInputValidator() {
    // Validate the JSON-Schema input.
    return new JsonSchemaFormValidator(new Validator());
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputValidator() {
    return new RenderArrayValidator();
  }

  /**
   * Validation callback against the schema.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateWithSchema(array &$element, FormStateInterface $form_state): void {
    $schema = $element['#json_schema'] ?? [];
    // If the schema is empty do not perform additional validation.
    if (!empty($schema)) {
      FormValidatorDrupal::typeCastRecursive($element, $form_state, $schema);
      FormValidatorDrupal::validateWithSchema($element, $form_state, $schema);
    }
  }

  /**
   * Creates a Drupal form element from a property definition.
   *
   * @param mixed $json_schema
   *   The JSON Schema for the element.
   * @param string $machine_name
   *   The machine name for the form element. Used for fallback metadata.
   * @param array $prop_parents
   *   The parents.
   * @param array $ui_schema_data
   *   The schema for the UI refinements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param mixed $current_input
   *   The current input.
   *
   * @return array
   *   The form element.
   */
  private function doTransformOneField($json_schema, string $machine_name, array $prop_parents, array $ui_schema_data, FormStateInterface $form_state, $current_input): array {
    $form_element = $this->scaffoldFormElement($json_schema, $machine_name, $ui_schema_data, $current_input);
    $form_element['#prop_parents'] = $prop_parents;
    if (!empty($json_schema->const)) {
      unset($form_element['#type']);
      $form_element['#markup'] = $json_schema->const;
      // Not much more to do with constants.
      return $form_element;
    }
    $type = $this->guessSchemaType($json_schema, $ui_schema_data);
    if ($type === 'string' && !empty($json_schema->format) && $json_schema->format === 'email') {
      $form_element = $this->transformEmail($form_element);
    }
    $label_mappings = $ui_schema_data['ui:enum']['labels']['mappings'] ?? [];
    $label_mappings = (array) $label_mappings;
    if (!empty($json_schema->enum)) {
      $form_element = $this->transformRadios($ui_schema_data['ui:widget'] ?? NULL, $form_element, $json_schema, $label_mappings);
    }
    if ($type === 'array') {
      $form_element = empty($json_schema->items->enum)
        ? $this->transformMultivalue($prop_parents, $machine_name, $form_state, $current_input, $form_element, $json_schema, $ui_schema_data)
        : $this->transformCheckboxes($ui_schema_data['ui:widget'] ?? NULL, $form_element, $json_schema, $label_mappings);
    }
    if ($type === 'object') {
      $form_element = $this->transformNested($json_schema, $form_element, $prop_parents, $ui_schema_data, $form_state, $current_input);
    }

    $enabled = (bool) ($ui_schema_data['ui:enabled'] ?? TRUE);
    $form_element['#disabled'] = !$enabled;
    $visible = (bool) ($ui_schema_data['ui:visible'] ?? TRUE);
    $form_element['#visible'] = $visible;
    // A 'ui:widget' names a Form API element the caller picked deliberately, so
    // it is taken at face value. Everything else has to be an element this
    // generator built on purpose; see SUPPORTED_ELEMENT_TYPES.
    $has_widget = !empty($ui_schema_data['ui:widget']);
    if (!$has_widget && !in_array($form_element['#type'] ?? '', self::SUPPORTED_ELEMENT_TYPES, TRUE)) {
      $form_element = $this->transformUnsupported($form_element, $type);
    }
    return $form_element;
  }

  /**
   * After-build handler for field elements in a form.
   *
   * This stores the final location of the field within the form structure so
   * that flagErrors() can assign validation errors to the right form element.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public static function multiValueAfterBuild(array $element, FormStateInterface $form_state) {
    if ($form_state->isProcessingInput()) {
      $element_parents = $element['#parents'];
      $data = $form_state->getValue($element_parents);
      $clean_data = UserInputCleaner::cleanUserInput($data);
      $form_state->setValue($element_parents, $clean_data);
    }

    $prop_parents = $element['#prop_parents'];
    $prop_name = $element['#prop_name'];

    $prop_state = static::getPropFormState($prop_parents, $prop_name, $form_state);
    $prop_state['array_parents'] = $element['#array_parents'];
    static::setPropFormState($prop_parents, $prop_name, $form_state, $prop_state);

    return $element;
  }

  /**
   * Submission handler for the "Add another item" button.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function removeOneSubmit(array $form, FormStateInterface $form_state): void {
    $button = $form_state->getTriggeringElement();
    $delta = $button['#delta'] ?? NULL;
    if (is_null($delta)) {
      return;
    }

    // Go two levels up in the form, to the container.
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -2));
    $prop_name = $element['#prop_name'];
    $form_parents = $button['#parents'];
    // Pop the last element 'remove_one' to address the element container.
    array_pop($form_parents);

    // Decrement the items count.
    $prop_state = static::getPropFormState($element['#prop_parents'] ?? [], $prop_name, $form_state);
    // If the index is set, then remove it.
    if (isset($prop_state['items_indices'][$delta])) {
      unset($prop_state['items_indices'][$delta]);
      static::setPropFormState(
        $element['#prop_parents'] ?? [],
        $prop_name,
        $form_state,
        $prop_state
      );
    }

    $form_state->setRebuild();
  }

  /**
   * Ajax callback for the "Add another item" button.
   *
   * This returns the new page content to replace the page content made obsolete
   * by the form submission.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array|mixed|void
   *   The element.
   */
  public static function removeOneAjax(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $delta = $button['#delta'] ?? NULL;
    if (is_null($delta)) {
      return NULL;
    }
    return [
      '#prefix' => '<div class="ajax-new-content recently-deleted-element"><em>',
      '#markup' => new translatableMarkup('- Deleted -'),
      '#suffix' => '</em></div>',
    ];
  }

  /**
   * Submission handler for the "Add another item" button.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function addMoreSubmit(array $form, FormStateInterface $form_state): void {
    $button = $form_state->getTriggeringElement();

    // Go one level up in the form, to the widgets container.
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $prop_name = $element['#prop_name'];
    $prop_parents = $element['#prop_parents'];

    // Increment the items count.
    $prop_state = static::getPropFormState($prop_parents, $prop_name, $form_state);
    $next_index = empty($prop_state['items_indices'])
      ? 0
      : end($prop_state['items_indices']) + 1;
    $prop_state['items_indices'][] = $next_index;
    static::setPropFormState($prop_parents, $prop_name, $form_state, $prop_state);

    $form_state->setRebuild();
  }

  /**
   * Ajax callback for the "Add another item" button.
   *
   * This returns the new page content to replace the page content made obsolete
   * by the form submission.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array|mixed|void
   *   The element.
   */
  public static function addMoreAjax(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();

    // Go one level up in the form, to the widgets container.
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));

    // Ensure the widget allows adding additional items.
    if ($element['#cardinality'] != FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
      return NULL;
    }

    // Add a DIV around the delta receiving the Ajax effect.
    $delta = $element['#max_delta'];
    $element[$delta]['#prefix'] = '<div class="ajax-new-content">' . ($element[$delta]['#prefix'] ?? '');
    $element[$delta]['#suffix'] = ($element[$delta]['#suffix'] ?? '') . '</div>';

    return $element;
  }

  /**
   * Get the prop form state.
   *
   * @param array $parents
   *   The parents.
   * @param string $prop_name
   *   The prop name.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array|mixed|null
   *   The state.
   */
  public static function getPropFormState(array $parents, string $prop_name, FormStateInterface $form_state) {
    return NestedArray::getValue($form_state->getStorage(), static::getWidgetStateParents($parents, $prop_name));
  }

  /**
   * Sets the prop form state.
   *
   * @param array $parents
   *   The parents.
   * @param string $prop_name
   *   The prop name.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $prop_state
   *   The state to set.
   */
  public static function setPropFormState(array $parents, string $prop_name, FormStateInterface $form_state, array $prop_state): void {
    $parents_state = static::getWidgetStateParents($parents, $prop_name);
    NestedArray::setValue($form_state->getStorage(), $parents_state, $prop_state);
  }

  /**
   * Returns the location of processing information within $form_state.
   *
   * @param array $parents
   *   The array of #parents where the widget lives in the form.
   * @param string $prop_name
   *   The field name.
   *
   * @return array
   *   The location of processing information within $form_state.
   */
  protected static function getWidgetStateParents(array $parents, string $prop_name) {
    // Prop processing data is placed at
    // $form_state->get(['prop_storage', '#parents', ...$parents..., '#props',
    // $prop_name]), to avoid clashes between prop names and $parents parts.
    return array_merge(['prop_storage', '#parents'], $parents, [
      '#props',
      $prop_name,
    ]);
  }

  /**
   * Creates a scaffold for the form element.
   *
   * @param mixed $element
   *   The parsed element from the schema.
   * @param string $machine_name
   *   The machine name for the form element. Used for fallback metadata.
   * @param array $ui_schema_data
   *   The schema for the UI refinements.
   * @param mixed $current_input
   *   The current input.
   *
   * @return array
   *   The scaffolded form element.
   */
  private function scaffoldFormElement($element, string $machine_name, array $ui_schema_data, $current_input): array {
    $type = $this->guessSchemaType($element, $ui_schema_data);
    $title = $ui_schema_data['ui:title'] ?? $element->title ?? $this->machineNameToHumanName($machine_name);
    $form_element = [
      '#title' => $title,
      '#type' => $type,
      '#prop_name' => $machine_name,
    ];
    if (!is_null($element->default ?? NULL)) {
      $form_element['#default_value'] = $element->default;
    }
    if (!is_null($current_input)) {
      $form_element['#default_value'] = $current_input;
    }
    $description = $ui_schema_data['ui:help'] ?? $element->description ?? NULL;
    if (!empty($description)) {
      $form_element['#description'] = $description;
    }
    $placeholder = $ui_schema_data['ui:placeholder'] ?? NULL;
    if (!empty($placeholder)) {
      $form_element['#placeholder'] = $placeholder;
    }
    // Basic transformations based on type.
    if ($type === 'boolean') {
      $form_element['#type'] = 'checkbox';
    }
    if ($type === 'string') {
      $form_element['#type'] = 'textfield';
    }
    if ($type === 'integer') {
      // Drupal has no 'integer' element. A number input whose step is one is
      // the native spelling of the constraint: the browser rejects a fractional
      // value before the form is submitted, and the value still arrives as an
      // integer because RecursiveTypeCaster::tryCastingNumber() casts a numeric
      // string with no decimal separator to int.
      $form_element['#type'] = 'number';
      $form_element['#step'] = 1;
    }
    return $form_element;
  }

  /**
   * Guesses the JSON property type based on the schema element.
   *
   * @param mixed $element
   *   The parsed element from the schema.
   * @param array $ui_schema_data
   *   The schema for the UI refinements.
   *
   * @return string
   *   The JSON property type.
   */
  private function guessSchemaType($element, array $ui_schema_data): string {
    if (!empty($ui_schema_data['ui:widget'])) {
      return $ui_schema_data['ui:widget'];
    }
    $type = $element->type;
    if (is_array($type)) {
      // Guess the first non null type.
      $type = current(array_filter($type, function ($item) {
        return $item !== NULL;
      }));
    }
    return $type;
  }

  /**
   * Turns a machine name into a human readable name.
   *
   * @param string $machine_name
   *   The machine name.
   *
   * @return string
   *   The human readable name.
   */
  private function machineNameToHumanName($machine_name) {
    return ucwords(strtr($machine_name, ['_' => ' ', '-' => ' ']));
  }

  /**
   * Adds the necessary validation rules to the form.
   *
   * @param array $element
   *   The form element to alter.
   *
   * @return array
   *   The modified form.
   */
  private function addValidationRules(array $element): array {
    $required_prop_names = $element['#json_schema']->required ?? [];
    // Add the required fields.
    foreach (array_keys($element) as $key) {
      if (($key[0] ?? '') === '#') {
        continue;
      }
      $element[$key]['#required'] = in_array($key, $required_prop_names);
    }
    $element['#element_validate'] = [[$this, 'validateWithSchema']];
    return $element;
  }

  /**
   * Get the dereferenced schema.
   *
   * @param mixed $data
   *   The schema containing references.
   *
   * @return mixed|object
   *   The dereferenced schema.
   */
  private function getSchema($data) {
    $storage = (new Factory(NULL, NULL, Constraint::CHECK_MODE_TYPE_CAST))->getSchemaStorage();
    $storage->addSchema('internal:/schema-with-refs', $data);
    return $storage->getSchema('internal:/schema-with-refs');
  }

  /**
   * Builds the form element for the email case.
   *
   * @param array $form_element
   *   The form element.
   *
   * @return array
   *   The form element.
   */
  private function transformEmail(array $form_element): array {
    $form_element['#type'] = 'email';
    return $form_element;
  }

  /**
   * Builds the form element for a type with no Form API counterpart.
   *
   * Emitting nothing is not an option: a property with no control and no
   * message is indistinguishable from a property the schema never declared,
   * which is how an unsupported type stays unnoticed until somebody compares
   * the generated form against the schema by hand.
   *
   * @param array $form_element
   *   The form element.
   * @param string $type
   *   The type that could not be turned into a control.
   *
   * @return array
   *   The form element.
   */
  private function transformUnsupported(array $form_element, string $type): array {
    $form_element['#type'] = 'item';
    // 'item' collects a value by default, and the only value it could collect
    // here is the empty string. That string is then type-cast and validated
    // against the property's schema like any other submission, so a property
    // that cannot be rendered would make the whole form unsavable. It has to
    // stay out of the submitted values entirely.
    $form_element['#input'] = FALSE;
    $form_element['#markup'] = new TranslatableMarkup(
      'This property cannot be edited: there is no form element for the %type type.',
      ['%type' => $type]
    );
    $form_element['#disabled'] = TRUE;
    unset($form_element['#default_value'], $form_element['#placeholder']);
    return $form_element;
  }

  /**
   * Builds the form element for the radios case.
   *
   * @param string|null $uiwidget
   *   The type of widget to use.
   * @param array $form_element
   *   The form element.
   * @param mixed $json_schema
   *   The JSON Schema for the element.
   * @param array $label_mappings
   *   Mappings for the labels.
   *
   * @return array
   *   The form element.
   */
  private function transformRadios(?string $uiwidget, array $form_element, $json_schema, array $label_mappings): array {
    $form_element['#type'] = $uiwidget ?? 'radios';
    $form_element['#options'] = $this->enumOptions($json_schema->enum, $label_mappings);
    return $form_element;
  }

  /**
   * Builds the form element for the checkboxes case.
   *
   * @param string|null $uiwidget
   *   The type of widget to use.
   * @param array $form_element
   *   The form element.
   * @param mixed $json_schema
   *   The JSON Schema for the element.
   * @param array $label_mappings
   *   An associative array to map options to human-readable labels.
   *
   * @return array
   *   The form element.
   */
  private function transformCheckboxes(?string $uiwidget, array $form_element, $json_schema, array $label_mappings): array {
    $form_element['#type'] = $uiwidget ?? 'checkboxes';
    $form_element['#options'] = $this->enumOptions($json_schema->items->enum, $label_mappings);
    return $form_element;
  }

  /**
   * Builds an '#options' array out of the members of an enum.
   *
   * Shared by the radios and the checkboxes cases, which differ only in where
   * they read the enum from. Both used to inline an array_reduce() whose
   * closure declared `string $opt` and merged single-element arrays, and each
   * of those two details was a separate defect:
   *
   * 1. `null` is a legal enum member -- it is how a JSON Schema spells "no
   *    choice made", and it is what `type: ["string", "null"]` props use. The
   *    `string` declaration turned it into a TypeError that took down the
   *    whole form rather than one option.
   * 2. array_merge() RENUMBERS integer keys. PHP coerces the numeric-string
   *    key '10' to int 10 on the way into the array, array_merge() then
   *    reassigns it sequentially, and an enum of ["10", "12", "15"] ends up
   *    offering the keys 0, 1, 2 while still displaying the original labels.
   *    Every such value failed validation on submission, because the key is
   *    what the browser sends back and 0 is not in the enumeration. Assigning
   *    the key directly preserves it: int 10 renders as value="10".
   *
   * @param array $members
   *   The enum members, as decoded from the schema.
   * @param array $label_mappings
   *   An associative array to map options to human-readable labels.
   *
   * @return array
   *   The options, keyed by the value Form API will submit.
   */
  private function enumOptions(array $members, array $label_mappings): array {
    $options = [];
    foreach ($members as $member) {
      // PHP has no NULL array key: writing one coerces it to the empty
      // string. That is not a workaround here, it is the correct key -- Form
      // API submits '' for an unselected control, and Drupal validates props
      // with CHECK_MODE_TYPE_CAST, which casts an absent or NULL value to ''
      // before it checks the enumeration. A schema that lists BOTH null and
      // "" collapses to one option, which no keyed array can avoid.
      $key = $member ?? '';
      $options[$key] = $label_mappings[$key] ?? $this->machineNameToHumanName((string) $member);
    }
    return $options;
  }

  /**
   * Builds the form element for the multi-value case.
   *
   * @param array $prop_parents
   *   The prop parents array.
   * @param string $machine_name
   *   The machine name.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array|null $current_input
   *   The current input.
   * @param array $form_element
   *   The form element.
   * @param mixed $json_schema
   *   The JSON Schema for the element.
   * @param array $ui_schema_data
   *   The schema for the UI refinements.
   *
   * @return array
   *   The form element.
   */
  private function transformMultivalue(array $prop_parents, string $machine_name, FormStateInterface $form_state, ?array $current_input, array $form_element, $json_schema, array $ui_schema_data): array {
    // Store field information in $form_state.
    if (!static::getPropFormState($prop_parents, $machine_name, $form_state)) {
      $count = count($current_input ?: []);
      $prop_state = [
        'items_indices' => $count ? range(0, $count - 1) : [],
        'array_parents' => [],
      ];
      static::setPropFormState($prop_parents, $machine_name, $form_state, $prop_state);
    }

    // @todo If we ever support non-infinite multivalues, change this.
    $cardinality = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
    // Determine the number of widgets to display.
    $indices = $cardinality === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
      ? static::getPropFormState($prop_parents, $machine_name, $form_state)['items_indices'] ?? [0]
      : range(0, $cardinality);
    $max = $cardinality === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
      ? count($indices)
      : $cardinality - 1;
    $is_multiple = $cardinality !== 1;
    $id_prefix = implode('-', $prop_parents);
    $wrapper_id = Html::getUniqueId($id_prefix . '-add-more-wrapper');
    $form_element['#type'] = 'details';
    $form_element['#open'] = TRUE;
    $form_element['#after_build'][] = [static::class, 'multiValueAfterBuild'];
    $form_element['#cardinality'] = $cardinality;
    $form_element['#max_delta'] = $max;
    $form_element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form_element['#suffix'] = '</div>';
    $title = $form_element['#title'] ?? '';
    $description = $form_element['#description'] ?? '';
    foreach ($indices as $delta) {
      // For multiple fields, title and description are handled by the wrapping
      // table.
      $element = $is_multiple
        ? [
          '#title' => new TranslatableMarkup('@title (value @number)', [
            '@title' => $title,
            '@number' => $delta + 1,
          ]),
          '#title_display' => 'invisible',
          '#description' => '',
        ]
        : [
          '#title' => $title,
          '#title_display' => 'before',
          '#description' => $description,
        ];
      // Add a new empty item if it doesn't exist yet at this delta.
      $new_prop_parents = [...$prop_parents, $delta];
      $element += $this->doTransformOneField(
        $json_schema->items,
        (string) $delta,
        $new_prop_parents,
        $ui_schema_data,
        $form_state,
        $current_input[$delta] ?? NULL
      );

      $container_id = sprintf('%s-%d-container', $id_prefix, $delta);
      $remove_name = sprintf('%s-%d-remove', $id_prefix, $delta);
      $form_element[$delta] = [
        '#type' => 'container',
        '#prefix' => '<div id="' . $container_id . '">',
        '#wrapper_id' => $container_id,
        '#suffix' => '</div>',
        '#attributes' => [
          'style' => 'position: relative;',
        ],
        'removable_element' => $element,
        'remove_one' => [
          '#delta' => $delta,
          '#type' => 'submit',
          '#name' => $remove_name,
          '#value' => new TranslatableMarkup('⨯'),
          '#validate' => [],
          '#prop_parents' => $new_prop_parents,
          '#attributes' => [
            'class' => ['field-remove-one-submit'],
            'style' => 'opacity: 0.9;display: block;position: absolute;top: -10px;left: -10px;margin: 0;overflow: hidden;width: 20px;height: 20px;padding: 0;border-radius: 15px;',
            'title' => new TranslatableMarkup('Remove item'),
          ],
          '#limit_validation_errors' => [],
          '#submit' => [[static::class, 'removeOneSubmit']],
          '#ajax' => [
            'callback' => [static::class, 'removeOneAjax'],
            'wrapper' => $container_id,
            'effect' => 'fade',
          ],
          '#weight' => 101,
        ],
      ];
    }

    $form_element['add_more'] = [
      '#type' => 'submit',
      '#name' => strtr($id_prefix, '-', '_') . '_add_more',
      '#value' => new TranslatableMarkup('Append an item'),
      '#attributes' => ['class' => ['field-add-more-submit']],
      '#limit_validation_errors' => [array_merge($prop_parents, [$machine_name])],
      '#submit' => [[static::class, 'addMoreSubmit']],
      '#ajax' => [
        'callback' => [static::class, 'addMoreAjax'],
        'wrapper' => $wrapper_id,
        'effect' => 'fade',
      ],
    ];
    return $form_element;
  }

  /**
   * Builds the form element for the nested case.
   *
   * @param mixed $json_schema
   *   The JSON Schema for the element.
   * @param array $form_element
   *   The form element.
   * @param array $parents
   *   The parents.
   * @param array $ui_schema_data
   *   The schema for the UI refinements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array|null $current_input
   *   The current input.
   *
   * @return array
   *   The form element.
   */
  private function transformNested($json_schema, array $form_element, array $parents, array $ui_schema_data, FormStateInterface $form_state, ?array $current_input): array {
    $properties = $json_schema->properties ?? [];
    if (!empty($properties)) {
      $form_element['#type'] = 'details';
      $form_element['#open'] = TRUE;
      foreach ($properties as $sub_machine_name => $sub_json_schema) {
        $form_element[$sub_machine_name] = $this->doTransformOneField(
          $sub_json_schema,
          $sub_machine_name,
          [...$parents, $sub_machine_name],
          $ui_schema_data[$sub_machine_name] ?? [],
          $form_state,
          $current_input[$sub_machine_name] ?? NULL
        );
      }
    }
    return $form_element;
  }

}
