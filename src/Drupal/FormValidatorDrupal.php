<?php

namespace SchemaForms\Drupal;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Exception\ExceptionInterface;
use JsonSchema\Validator;
use SchemaForms\ArrayToStdClass;
use SchemaForms\RecursiveTypeCaster;
use SchemaForms\SchemaReferenceResolver;

/**
 * Validates Drupal Form API submissions against JSON-Schema documents.
 *
 * @internal
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
    try {
      json_encode($submitted, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      $form_state->setError(
        $element,
        new TranslatableMarkup('The submitted data could not be encoded as JSON. Please check the submitted values.')
      );
      return;
    }
    $data = (new ArrayToStdClass())->transform($submitted);
    if ($data === [] || $data === NULL) {
      // If the data is empty we may need to cast it to an empty object.
      $types = is_array($schema->type) ? $schema->type : [$schema->type];
      $data = in_array('array', $types, TRUE) ? [] : new \stdClass();
    }
    try {
      $validator = new Validator(SchemaReferenceResolver::createFactory($schema));
      // Validate the massaged data against the schema.
      $num_errors = $validator->validate($data, $schema, Constraint::CHECK_MODE_TYPE_CAST);
    }
    catch (ExceptionInterface $e) {
      $form_state->setError($element, new TranslatableMarkup('The schema contains an unsupported reference. External schema retrieval is disabled.'));
      return;
    }
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
      $full_message = implode(', ', $generic_errors);
      $form_state->setError(
        $element,
        new TranslatableMarkup(
          '<p>Invalid data, please make sure all data is valid according to the schema. Schema validation returned the following errors: @full_message</p>',
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
    $message = $error['message'] . static::constraintSuffix($error);
    $form_error_parents = $mappings[$error['pointer'] ?? ''] ?? NULL;
    if ($form_error_parents === NULL) {
      return $message;
    }
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
   * Names the constraint a validation error violated.
   *
   * `justinrainbow/json-schema` changed the shape of this value in 6.0: it used
   * to be the constraint's name as a plain string, and it is now
   * `['name' => <name>, 'params' => [...]]`. This library supports
   * `^5.2 || ^6.3`, so both shapes reach here and neither may be assumed.
   *
   * @param array $error
   *   The error data from the JSON-Schema validation.
   *
   * @return string
   *   The suffix to append to the error message, or the empty string when the
   *   constraint cannot be named.
   */
  private static function constraintSuffix(array $error): string {
    $constraint = $error['constraint'] ?? NULL;
    $name = is_array($constraint) ? ($constraint['name'] ?? NULL) : $constraint;
    if (!is_string($name) || $name === '') {
      return '';
    }
    return ' [JSON Schema violation of "' . $name . '"]';
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
    static::markUntouchedSavedNulls($element, count($element['#parents']), $data);
    $data = UserInputCleaner::cleanUserInput($data);
    $saved_null_paths = static::replacePlaceholders($data);
    try {
      $data = RecursiveTypeCaster::recursiveTypeRefinements($data, $schema);
    }
    catch (ExceptionInterface $e) {
      $form_state->setError($element, new TranslatableMarkup('The schema contains an unsupported reference. External schema retrieval is disabled.'));
      return;
    }
    static::supplyFixedValues($element, $data);
    // A fixed sub-property must not turn a restored NULL back into an object.
    foreach ($saved_null_paths as $path) {
      NestedArray::setValue($data, $path, NULL, TRUE);
    }
    $form_state->setValueForElement($element, $data);
  }

  /**
   * Puts a placeholder where an untouched control holds a saved NULL.
   *
   * Multivalue widgets clean their own value while the form is built, which
   * compacts item deltas, so their items are marked then. The walk therefore
   * stops at each multivalue widget, after checking the widget itself.
   *
   * @param array $element
   *   The element whose children are walked.
   * @param int $root_depth
   *   The number of #parents of the element that holds $data.
   * @param mixed $data
   *   The submitted value of that element, updated in place.
   *
   * @see \SchemaForms\Drupal\FormGeneratorDrupal::multiValueAfterBuild()
   */
  public static function markUntouchedSavedNulls(array $element, int $root_depth, &$data): void {
    foreach (Element::children($element) as $key) {
      $child = $element[$key];
      if (!empty($child['#schema_saved_null']) && static::isUntouched($child)) {
        $data = is_array($data) ? $data : [];
        NestedArray::setValue($data, array_slice($child['#parents'], $root_depth), new SavedNull(), TRUE);
        continue;
      }
      if (!array_key_exists('#max_delta', $child)) {
        static::markUntouchedSavedNulls($child, $root_depth, $data);
      }
    }
  }

  /**
   * Decides whether a control still submits what it showed for a saved NULL.
   *
   * A saved NULL renders as an unchecked checkbox, an empty selection, an empty
   * text input or a list with no items. Buttons and fixed values do not count.
   *
   * @param array $element
   *   The element.
   *
   * @return bool
   *   TRUE when no control under the element holds a value.
   */
  private static function isUntouched(array $element): bool {
    if (in_array($element['#type'] ?? '', ['submit', 'button'], TRUE) || array_key_exists('#schema_fixed_value', $element)) {
      return TRUE;
    }
    if (!empty($element['#input'])) {
      $value = $element['#value'] ?? NULL;
      // An unchecked checkbox has integer 0; a checked one its return value.
      return ($element['#type'] ?? '') === 'checkbox'
        ? $value === 0
        : in_array($value, ['', NULL, []], TRUE);
    }
    foreach (Element::children($element) as $key) {
      if (!static::isUntouched($element[$key])) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Replaces saved NULL placeholders with NULL.
   *
   * @param mixed $data
   *   The cleaned submission, updated in place.
   * @param array $path
   *   The path of $data. This is an internal variable used for tracking.
   *
   * @return array
   *   The paths of the restored NULL values.
   */
  private static function replacePlaceholders(&$data, array $path = []): array {
    if ($data instanceof SavedNull) {
      $data = NULL;
      return [$path];
    }
    if (!is_array($data)) {
      return [];
    }
    $paths = [];
    foreach ($data as $key => &$value) {
      $paths = [...$paths, ...static::replacePlaceholders($value, [...$path, $key])];
    }
    unset($value);
    return $paths;
  }

  /**
   * Supplies values for generated elements that collect no browser input.
   *
   * Fixed values are already JSON values. Insert them after cleaning and
   * casting, so NULL is not dropped and constants keep their exact type.
   *
   * @param array $element
   *   The generated element tree.
   * @param mixed $data
   *   The prepared submission, updated in place.
   */
  private static function supplyFixedValues(array $element, &$data): void {
    if (array_key_exists('#schema_fixed_value', $element)) {
      $data = $data ?? [];
      NestedArray::setValue($data, $element['#prop_parents'], $element['#schema_fixed_value'], TRUE);
      return;
    }
    foreach (Element::children($element) as $key) {
      static::supplyFixedValues($element[$key], $data);
    }
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
   * @param array $_json_pointer_parents
   *   The data path, with multivalue deltas replaced by item ordinals.
   *
   * @return array
   *   The mappings of paths from the JSON Pointer to the form structure.
   */
  private static function buildMappingsElementPaths(array $element, array $root_parents, array $_current_mappings = [], array $_json_pointer_parents = []): array {
    $parents = $element['#array_parents'] ?? [];
    // Now drop some parents to make everything relative to `/`.
    $parents = array_slice($parents, count($root_parents));
    // Both supported json-schema majors escape these characters per segment.
    $escaped_pointer_parents = array_map(
      static fn ($segment) => strtr((string) $segment, ['/' => '~1', '~' => '~0', '%' => '%25']),
      $_json_pointer_parents
    );
    $json_pointer = '/' . implode('/', $escaped_pointer_parents);
    $_current_mappings[$json_pointer] = $parents;
    $keys = Element::children($element);
    if (empty($keys)) {
      return $_current_mappings;
    }
    $item_ordinal = 0;
    foreach ($keys as $key) {
      if (in_array($key, ['remove_one', 'add_more'], TRUE)) {
        continue;
      }
      $child_pointer_parents = $_json_pointer_parents;
      if ($key !== 'removable_element') {
        // Cleaning compacts item values, while form deltas retain removal gaps.
        $child_pointer_parents[] = isset($element[$key]['removable_element'])
          ? $item_ordinal++
          : $key;
      }
      $_current_mappings = static::buildMappingsElementPaths(
        $element[$key],
        $root_parents,
        $_current_mappings,
        $child_pointer_parents
      );
    }
    return $_current_mappings;
  }

}
