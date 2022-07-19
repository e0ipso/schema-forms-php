<?php

namespace SchemaForms\Drupal;

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
   * The schema.
   *
   * @var object
   */
  private object $schema;

  /**
   * Creates the Form API element based on the JSON-Schema input.
   *
   * {@inheritdoc}
   */
  public function doTransform($data, Context $context = NULL) {
    $context = $context ?: new Context();
    $storage = (new Factory(NULL, NULL, Constraint::CHECK_MODE_TYPE_CAST))->getSchemaStorage();
    $storage->addSchema('internal:/schema-with-refs', $data);
    $schema = $storage->getSchema('internal:/schema-with-refs');
    // Store the schema for the validation callback to access.
    $context['derefed_schema'] = $this->schema = $schema;
    $props = $schema->properties;
    $element = ['#type' => 'container'];
    foreach ((array) $props as $key => $prop) {
      // If there is UI context, grab it and pass it along.
      $ui_schema_data = $context->offsetExists($key) ? $context->offsetGet($key) : [];
      $element[$key] = $this->doTransformOneField($prop, $key, $ui_schema_data);
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
    if (!isset($this->schema)) {
      $form_state->setError($element, new TranslatableMarkup('Unable to find stored schema.'));
    }
    FormValidatorDrupal::validateWithSchema($element, $form_state, $this->schema);
  }

  /**
   * Creates a Drupal form element from a property definition.
   *
   * @param mixed $element
   *   The parsed element from the schema.
   * @param string $machine_name
   *   The machine name for the form element. Used for fallback metadata.
   * @param array $ui_schema_data
   *   The UI context on how to build the form element.
   *
   * @return array
   *   The form element.
   */
  private function doTransformOneField($element, string $machine_name, array $ui_schema_data): array {
    // @todo A naive initial implementation that will only check on data type.
    $form_element = $this->scaffoldFormElement($element, $machine_name, $ui_schema_data);
    if (!empty($element->const)) {
      unset($form_element['#type']);
      $form_element['#markup'] = $element->const;
      // Not much more to do with constants.
      return $form_element;
    }
    $type = $this->guessSchemaType($element, $ui_schema_data);
    if ($type === 'string' && !empty($element->format) && $element->format === 'email') {
      $form_element['#type'] = 'email';
    }
    $label_mappings = $ui_schema_data['ui:enum']['labels']['mappings'] ?? [];
    $label_mappings = (array) $label_mappings;
    if (!empty($element->enum)) {
      $form_element['#type'] = $ui_schema_data['ui:widget'] ?? 'radios';
      $form_element['#options'] = array_reduce($element->enum, function (array $carry, string $opt) use ($label_mappings) {
        return array_merge(
          $carry,
          [$opt => $label_mappings[$opt] ?? $this->machineNameToHumanName($opt)]
        );
      }, []);
    }
    if ($type === 'array') {
      if (empty($element->items->enum)) {
        throw new \InvalidArgumentException('Only arrays with enums are supported.');
      }
      $form_element['#type'] = $ui_schema_data['ui:widget'] ?? 'checkboxes';
      $form_element['#options'] = array_reduce($element->items->enum, function (array $carry, string $opt) use ($label_mappings) {
        return array_merge(
          $carry,
          [$opt => $label_mappings[$opt] ?? $this->machineNameToHumanName($opt)]
        );
      }, []);
    }
    if ($type === 'object') {
      throw new \InvalidArgumentException('Object types representing nested field sets are not supported yet.');
    }
    $enabled = (bool) ($ui_schema_data['ui:enabled'] ?? TRUE);
    $form_element['#disabled'] = !$enabled;
    $visible = (bool) ($ui_schema_data['ui:visible'] ?? TRUE);
    $form_element['#visible'] = $visible;
    return $form_element;
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
  private function scaffoldFormElement($element, string $machine_name, array $ui_schema_data): array {
    $type = $this->guessSchemaType($element, $ui_schema_data);
    $title = $ui_schema_data['ui:title'] ?? $element->title ?? $this->machineNameToHumanName($machine_name);
    $form_element = [
      '#title' => $title,
      '#type' => $type,
    ];
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
   * @param \Shaper\Util\Context $context
   *   The context.
   *
   * @return array
   *   The modified form.
   */
  private function addValidationRules(array $element, Context $context): array {
    $required_field_names = $context['derefed_schema']->required ?? [];
    // Add the required fields.
    foreach (array_keys($element) as $key) {
      if (($key[0] ?? '') === '#') {
        continue;
      }
      $element[$key]['#required'] = in_array($key, $required_field_names);
    }
    $element['#element_validate'] = [[$this, 'validateWithSchema']];
    return $element;
  }

}
