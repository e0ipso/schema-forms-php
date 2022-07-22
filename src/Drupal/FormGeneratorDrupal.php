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
   * Creates the Form API element based on the JSON-Schema input.
   *
   * {@inheritdoc}
   */
  public function doTransform($data, Context $context = NULL) {
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
      $element[$key] = $this->doTransformOneField($prop, $key, [], $ui_schema_data, $form_state, $current_input[$key] ?? NULL);
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
   *   The parsed element from the schema.
   * @param string $machine_name
   *   The machine name for the form element. Used for fallback metadata.
   * @param array $ui_schema_data
   *   The UI context on how to build the form element.
   *
   * @return array
   *   The form element.
   */
  private function doTransformOneField($json_schema, string $machine_name, array $parents, array $ui_schema_data, FormStateInterface $form_state, $current_input): array {
    $form_element = $this->scaffoldFormElement($json_schema, $machine_name, $ui_schema_data, $current_input);
    $form_element['#prop_parents'] = $parents;
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
        ? $this->transformMultivalue($parents, $machine_name, $form_state, $current_input, $form_element, $json_schema, $ui_schema_data)
        : $this->transformCheckboxes($ui_schema_data['ui:widget'] ?? NULL, $form_element, $json_schema, $label_mappings);
    }
    if ($type === 'object') {
      $form_element = $this->transformNested($json_schema, $form_element, $parents, $ui_schema_data, $form_state, $current_input);
    }

    $enabled = (bool) ($ui_schema_data['ui:enabled'] ?? TRUE);
    $form_element['#disabled'] = !$enabled;
    $visible = (bool) ($ui_schema_data['ui:visible'] ?? TRUE);
    $form_element['#visible'] = $visible;
    return $form_element;
  }

  /**
   * After-build handler for field elements in a form.
   *
   * This stores the final location of the field within the form structure so
   * that flagErrors() can assign validation errors to the right form element.
   */
  public static function afterBuild(array $element, FormStateInterface $form_state) {
    $parents = $element['#prop_parents'];
    $prop_name = $element['#prop_name'];

    $prop_state = static::getWidgetState($parents, $prop_name, $form_state);
    $prop_state['array_parents'] = $element['#array_parents'];
    static::setWidgetState($parents, $prop_name, $form_state, $prop_state);

    return $element;
  }


  /**
   * Submission handler for the "Add another item" button.
   */
  public static function addMoreSubmit(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();

    // Go one level up in the form, to the widgets container.
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $prop_name = $element['#prop_name'];
    $parents = $element['#prop_parents'];

    // Increment the items count.
    $prop_state = static::getWidgetState($parents, $prop_name, $form_state);
    $prop_state['items_count']++;
    static::setWidgetState($parents, $prop_name, $form_state, $prop_state);

    $form_state->setRebuild();
  }

  /**
   * Ajax callback for the "Add another item" button.
   *
   * This returns the new page content to replace the page content made obsolete
   * by the form submission.
   */
  public static function addMoreAjax(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();

    // Go one level up in the form, to the widgets container.
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));

    // Ensure the widget allows adding additional items.
    if ($element['#cardinality'] != FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
      return;
    }

    // Add a DIV around the delta receiving the Ajax effect.
    $delta = $element['#max_delta'];
    $element[$delta]['#prefix'] = '<div class="ajax-new-content">' . ($element[$delta]['#prefix'] ?? '');
    $element[$delta]['#suffix'] = ($element[$delta]['#suffix'] ?? '') . '</div>';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function getWidgetState(array $parents, $prop_name, FormStateInterface $form_state) {
    return NestedArray::getValue($form_state->getStorage(), static::getWidgetStateParents($parents, $prop_name));
  }

  /**
   * {@inheritdoc}
   */
  public static function setWidgetState(array $parents, $prop_name, FormStateInterface $form_state, array $prop_state) {
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
  protected static function getWidgetStateParents(array $parents, $prop_name) {
    // Field processing data is placed at
    // $form_state->get(['field_storage', '#parents', ...$parents..., '#fields', $prop_name]),
    // to avoid clashes between field names and $parents parts.
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
   *   The UI context on how to build the form element.
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
    return $form_element;
  }

  /**
   * Guesses the JSON property type based on the schema element.
   *
   * @param mixed $element
   *   The parsed element from the schema.
   * @param array $ui_schema_data
   *   The UI context on how to build the form element.
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
   * @param mixed $data
   *
   * @return mixed|object
   */
  private function getSchema(mixed $data): mixed {
    $storage = (new Factory(NULL, NULL, Constraint::CHECK_MODE_TYPE_CAST))->getSchemaStorage();
    $storage->addSchema('internal:/schema-with-refs', $data);
    return $storage->getSchema('internal:/schema-with-refs');
  }

  /**
   * @param array $form_element
   *
   * @return array
   */
  private function transformEmail(array $form_element): array {
    $form_element['#type'] = 'email';
    return $form_element;
  }

  /**
   * @param $uiwidget
   * @param array $form_element
   * @param mixed $json_schema
   * @param array $label_mappings
   *
   * @return array
   */
  private function transformRadios($uiwidget, array $form_element, mixed $json_schema, array $label_mappings): array {
    $form_element['#type'] = $uiwidget ?? 'radios';
    $form_element['#options'] = array_reduce($json_schema->enum, function (array $carry, string $opt) use ($label_mappings) {
      return array_merge(
        $carry,
        [$opt => $label_mappings[$opt] ?? $this->machineNameToHumanName($opt)]
      );
    }, []);
    return $form_element;
  }

  /**
   * @param $uiwidget
   * @param array $form_element
   * @param mixed $json_schema
   * @param array $label_mappings
   *
   * @return array
   */
  private function transformCheckboxes($uiwidget, array $form_element, mixed $json_schema, array $label_mappings): array {
    $form_element['#type'] = $uiwidget ?? 'checkboxes';
    $form_element['#options'] = array_reduce($json_schema->items->enum, function (array $carry, string $opt) use ($label_mappings) {
      return array_merge(
        $carry,
        [$opt => $label_mappings[$opt] ?? $this->machineNameToHumanName($opt)]
      );
    }, []);
    return $form_element;
  }

  /**
   * @param array $parents
   * @param string $machine_name
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param array $current_input
   * @param array $form_element
   * @param mixed $json_schema
   * @param array $ui_schema_data
   *
   * @return array
   */
  private function transformMultivalue(array $parents, string $machine_name, FormStateInterface $form_state, array $current_input, array $form_element, mixed $json_schema, array $ui_schema_data): array {
    // Store field information in $form_state.
    if (!static::getWidgetState($parents, $machine_name, $form_state)) {
      $prop_state = [
        'items_count' => count($current_input),
        'array_parents' => [],
      ];
      static::setWidgetState($parents, $machine_name, $form_state, $prop_state);
    }

    $id_prefix = implode('-', array_merge($parents, [$machine_name]));
    $wrapper_id = Html::getUniqueId($id_prefix . '-add-more-wrapper');
    // @todo If we ever support non-infinite multivalues, change this.
    $cardinality = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
    // Determine the number of widgets to display.
    switch ($cardinality) {
      case FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED:
        $prop_state = static::getWidgetState($parents, $machine_name, $form_state);
        $max = $prop_state['items_count'];
        $is_multiple = TRUE;
        break;

      default:
        $max = $cardinality - 1;
        $is_multiple = ($cardinality > 1);
        break;
    }
    $form_element['#type'] = 'details';
    $form_element['#open'] = TRUE;
    $form_element['#after_build'][] = [static::class, 'afterBuild'];
    $form_element['#cardinality'] = $cardinality;
    $form_element['#cardinality_multiple'] = TRUE;
    $form_element['#max_delta'] = $max;
    $form_element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form_element['#suffix'] = '</div>';
    $title = $form_element['#title'] ?? '';
    $description = $form_element['#description'] ?? '';
    for ($delta = 0; $delta < $max; $delta++) {
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
      $element += $this->doTransformOneField(
        $json_schema->items,
        '',
        [...$parents, $machine_name, $delta],
        $ui_schema_data,
        $form_state,
        $current_input[$delta] ?? NULL
      );
      $form_element[$delta] = $element;
    }

    $form_element['add_more'] = [
      '#type' => 'submit',
      '#name' => strtr($id_prefix, '-', '_') . '_add_more',
      '#value' => t('Append an item'),
      '#attributes' => ['class' => ['field-add-more-submit']],
      '#limit_validation_errors' => [array_merge($parents, [$machine_name])],
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
   * @param mixed $json_schema
   * @param array $form_element
   * @param array $parents
   * @param array $ui_schema_data
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param array $current_input
   *
   * @return array
   */
  private function transformNested(mixed $json_schema, array $form_element, array $parents, array $ui_schema_data, FormStateInterface $form_state, array $current_input): array {
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
