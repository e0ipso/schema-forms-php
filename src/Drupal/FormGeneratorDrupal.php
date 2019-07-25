<?php

namespace SchemaForms\Drupal;

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
    // TODO: Add support for the UIContext overrides.
    $storage = (new Factory(NULL, NULL, Constraint::CHECK_MODE_TYPE_CAST))->getSchemaStorage();
    $storage->addSchema('internal:/schema-with-refs', $data);
    $derefed_schema = $storage->getSchema('internal:/schema-with-refs');
    $required_field_names = $derefed_schema->required ?? [];
    $props = $derefed_schema->properties;
    $form = [];
    foreach ((array) $props as $key => $prop) {
      $form[$key] = $this->doTransformOneField($prop, $key);
    }
    foreach ($form as $key => $element) {
      $form[$key]['#required'] = in_array($key, $required_field_names);
    }
    return $form;
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
   * Creates a Drupal form element from a property definition.
   *
   * @param mixed $element
   *   The parsed element from the schema.
   * @param string $machine_name
   *   The machine name for the form element. Used for fallback metadata.
   *
   * @return array
   *   The form element.
   */
  private function doTransformOneField($element, string $machine_name): array {
    // TODO: A naive initial implementation that will only check on data type.
    $form_element = $this->scaffoldFormElement($element, $machine_name);
    if (!empty($element->const)) {
      unset($form_element['#type']);
      $form_element['#markup'] = $element->const;
      // Not much more to do with constants.
      return $form_element;
    }
    $type = $this->guessSchemaType($element);
    if ($type === 'string' && !empty($element->format) && $element->format === 'email') {
      $form_element['#type'] = 'email';
    }
    if (!empty($element->enum)) {
      $form_element['#type'] = 'radios';
      $form_element['#options'] = array_reduce($element->enum, function (array $carry, string $opt) {
        return array_merge(
          $carry,
          [$opt => $this->machineNameToHumanName($opt)]
        );
      }, []);
    }
    if ($type === 'array') {
      if (empty($element->items->enum)) {
        throw new \InvalidArgumentException('Only arrays with enums are supported.');
      }
      $form_element['#type'] = 'checkboxes';
      $form_element['#options'] = array_reduce($element->items->enum, function (array $carry, string $opt) {
        return array_merge(
          $carry,
          [$opt => $this->machineNameToHumanName($opt)]
        );
      }, []);
    }
    if ($type === 'object') {
      throw new \InvalidArgumentException('Object types representing nested field sets are not supported yet.');
    }
    return $form_element;
  }

  /**
   * Creates a scaffold for the form element.
   *
   * @param mixed $element
   *   The parsed element from the schema.
   * @param string $machine_name
   *   The machine name for the form element. Used for fallback metadata.
   *
   * @return array
   *   The scaffolded form element.
   */
  private function scaffoldFormElement($element, string $machine_name): array {
    $type = $this->guessSchemaType($element);
    $form_element = [
      '#title' => $this->machineNameToHumanName($machine_name),
      '#type' => $type,
    ];
    if (!empty($element->title)) {
      $form_element['#title'] = $element->title;
    }
    if (!empty($element->description)) {
      $form_element['#description'] = $element->description;
    }
    // Basic transformations based on type.
    if ($type === 'boolean') {
      $form_element['#type'] = 'checkbox';
    }
    return $form_element;
  }

  /**
   * Guesses the JSON property type based on the schema element.
   *
   * @param mixed $element
   *   The parsed element from the schema.
   *
   * @return string
   *   The JSON property type.
   */
  private function guessSchemaType($element): string {
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

}
