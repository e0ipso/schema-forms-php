<?php

namespace SchemaForms\Drupal;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use SchemaForms\ArrayToStdClass;
use SchemaForms\RecursiveTypeCaster;

/**
 * Generates Drupal Form API forms from JSON-Schema documents.
 */
final class FormValidatorDrupal {

  /**
   * Validation callback against the schema.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param object $schema
   *   The form schema.
   */
  public static function validateWithSchema(array &$element, FormStateInterface $form_state, object $schema): void {
    $parents = $element['#parents'];
    $raw_input = $form_state->getUserInput();
    $submitted = $form_state->getValue($parents) ?? NestedArray::getValue($raw_input, $parents) ?? NULL;
    $data = (new ArrayToStdClass())->transform($submitted);
    if ($data === []) {
      // If the data is an empty array we may need to cast it to empty object.
      $types = is_array($schema->type) ? $schema->type : [$schema->type];
      $data = in_array('array', $types, TRUE) ? [] : new \stdClass();
    }
    $validator = new Validator();
    // Validate the massaged data against the schema.
      if ($data === null) {
          $data = [];
      }
      $num_errors = $validator->validate($data, $schema, Constraint::CHECK_MODE_TYPE_CAST);
    if ($num_errors) {
      // Build the mappings of paths to form paths.
      $mappings = static::buildMappingsElementPaths($element, $element['#array_parents']);
      $errors = $validator->getErrors();
      // Now check if we can find a specific form element to trigger the error.
      $generic_errors = array_filter(
        array_map(
          static fn(array $error) => self::errorForProp($element, $form_state, $error, $mappings),
          $errors
        )
      );
      if (empty($generic_errors)) {
        return;
      }
      $full_message = implode(
        ', ',
        array_map([Html::class, 'escape'], $generic_errors)
      );
      $form_state->setError(
        $element,
        new TranslatableMarkup(
          '<p>Invalid data, please make sure all data is valid according to the schema. Schema validation returned the following errors errors: @full_message</p>',
          ['@full_message' => $full_message]
        )
      );
    }
  }

  /**
   * Sets or returns the error for a prop.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $error
   *   The error data from the JSON-Schema validation.
   * @param array $mappings
   *   Mappings to find the form element.
   *
   * @return string|null
   *   String if we could not find the particular prop this error is for. NULL
   *   if the error could be set.
   */
  private static function errorForProp(array $element, FormStateInterface $form_state, array $error, array $mappings): ?string {
    $message = $error['message'] . ' [JSON Schema violation of "' . $error['constraint'] . '"]';
    $form_error_parents = $mappings[$error['pointer'] ?? ''] ?? [];
    $key_exists = FALSE;
    $error_element = NestedArray::getValue(
      $element,
      $form_error_parents,
      $key_exists
    );
    if ($key_exists) {
      $form_state->setError(
        $error_element,
        new TranslatableMarkup('%element: @message', [
          '%element' => $error_element['#title'] ?? '',
          '@message' => $message,
        ])
      );
      return NULL;
    }
    return $message;
  }

  /**
   * Casts form submissions to their corresponding JSON types.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param object $schema
   *   The data schema.
   */
  public static function typeCastRecursive(array $element, FormStateInterface $form_state, object $schema): void {
    $data = $form_state->getValue($element['#parents']);
    $data = UserInputCleaner::cleanUserInput($data);
    $data = RecursiveTypeCaster::recursiveTypeRefinements($data, $schema);
    $form_state->setValueForElement($element, $data);
  }

  /**
   * Maps the JSON pointer paths to form element parents.
   *
   * It returns ['root', 0, 'removable_element', 'foo', 2, 'removable_element']
   * from '/root/0/foo/2'.
   *
   * @param array $element
   *   The form element.
   * @param array $root_parents
   *   The parents of the JSON Schema form.
   * @param array $_current_mappings
   *   The current mappings. This is an internal variable used for tracking.
   *
   * @return array
   *   The mappings of paths from the JSON Pointer to the form structure.
   */
  private static function buildMappingsElementPaths(array $element, array $root_parents, array $_current_mappings = []): array {
    $parents = $element['#array_parents'] ?? [];
    // Now drop some parents to make everything relative to `/`.
    $parents = array_slice($parents, count($root_parents));
    $json_pointer_parents = array_filter(
      $parents,
      static fn (string $parent) => $parent !== 'removable_element'
    );
    $json_pointer = '/' . implode('/', $json_pointer_parents);
    $_current_mappings[$json_pointer] = $parents;
    $keys = Element::children($element);
    if (empty($keys)) {
      return $_current_mappings;
    }
    foreach ($keys as $key) {
      if (in_array($key, ['remove_one', 'add_more'], TRUE)) {
        continue;
      }
      $_current_mappings = static::buildMappingsElementPaths(
        $element[$key],
        $root_parents,
        $_current_mappings
      );
    }
    return $_current_mappings;
  }

}
