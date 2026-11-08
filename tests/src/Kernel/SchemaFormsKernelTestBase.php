<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use Composer\Autoload\ClassLoader;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element;
use Drupal\KernelTests\KernelTestBase;
use SchemaForms\Tests\Kernel\Form\GeneratedSchemaForm;

/**
 * Builds and submits generated forms through Drupal's FormBuilder.
 *
 * Submitting runs the generated schema validation bridge, so a test sees
 * the errors and type-cast values Drupal form processing produces. Drupal
 * runs the bridge as #element_validate when the container sits under a key,
 * and as a form-level #validate handler when the container is the form.
 *
 * Kernel tests run on SQLite, configured by SIMPLETEST_DB in phpunit.xml.
 * Every concrete subclass must carry the #[RunTestsInSeparateProcesses]
 * attribute, as Drupal 11.3 requires; PHPUnit does not inherit it.
 */
abstract class SchemaFormsKernelTestBase extends KernelTestBase {

  /**
   * Submits values to a generated form and returns the outcome.
   *
   * @param object $schema
   *   The decoded JSON Schema.
   * @param array $values
   *   The submitted values, shaped as Drupal would receive them.
   * @param array $ui_hints
   *   The UI schema, keyed by property name.
   * @param string|null $parent_key
   *   The form key that holds the generated container, or NULL to submit the
   *   container as the form itself.
   * @param bool $tree
   *   Whether the form sets #tree. Only applies with a parent key.
   * @param \Drupal\Core\Form\FormState|null $form_state
   *   The form state to submit with, or NULL for a new one.
   * @param array $current_input
   *   Previously saved values supplied to the generator.
   *
   * @return \SchemaForms\Tests\Kernel\SchemaFormSubmission
   *   The processed form, its state, its errors and its values.
   */
  protected function submitSchemaForm(object $schema, array $values, array $ui_hints = [], ?string $parent_key = NULL, bool $tree = FALSE, ?FormState $form_state = NULL, array $current_input = []): SchemaFormSubmission {
    $form_state = ($form_state ?? new FormState())->setValues($values);
    // AJAX buttons need a current route even in the disposable kernel fixture.
    $request = $this->container->get('request_stack')->getCurrentRequest();
    $request->attributes->set('_route', 'schema_forms_test');
    $request->attributes->set('_route_object', new \Symfony\Component\Routing\Route('/schema-form'));
    $form_object = new GeneratedSchemaForm($schema, $ui_hints, $parent_key, $tree, $current_input);
    $this->container->get('form_builder')->submitForm($form_object, $form_state);
    $errors = array_map('strval', $form_state->getErrors());
    return new SchemaFormSubmission(
      $form_state->getCompleteForm(),
      $form_state,
      $errors,
      $form_state->getValues(),
    );
  }

  /**
   * Builds a form once, then submits the controls a browser would send.
   *
   * Unlike ::submitSchemaForm(), the submitted values come from the built
   * form, so untouched controls send exactly what they showed.
   *
   * @param \Drupal\Core\Form\FormInterface $form_object
   *   The form, which needs a Save button when $button is 'op'.
   * @param array<string, mixed> $edits
   *   Values to change, keyed by their dot-separated path below the form or,
   *   with a parent key, below that key.
   * @param string|null $parent
   *   The form key that holds the generated container, or NULL.
   * @param string $button
   *   The name of the button that submits the form.
   * @param string $button_value
   *   The value the button submits.
   * @param array $storage
   *   The form state storage of an earlier request, such as the item list of
   *   a multivalue widget after a removal.
   *
   * @return \SchemaForms\Tests\Kernel\SchemaFormSubmission
   *   The processed form, its state, its errors and its values.
   */
  protected function browserSubmit(FormInterface $form_object, array $edits = [], ?string $parent = NULL, string $button = 'op', string $button_value = 'Save', array $storage = []): SchemaFormSubmission {
    $request = $this->container->get('request_stack')->getCurrentRequest();
    $request->setMethod('POST');
    $request->attributes->set('_route', 'schema_forms_test');
    $request->attributes->set('_route_object', new \Symfony\Component\Routing\Route('/schema-form'));
    $builder = $this->container->get('form_builder');
    $build_state = new FormState();
    $build_state->setStorage($storage);
    $built = $builder->buildForm($form_object, $build_state);
    $values = [];
    $this->browserValues($built, $values);
    foreach ($edits as $path => $value) {
      NestedArray::setValue($values, [...($parent === NULL ? [] : [$parent]), ...explode('.', $path)], $value);
    }
    $values[$button] = $button_value;
    $state = new FormState();
    $state->setStorage($storage);
    $state->setValues($values);
    // A browser cannot submit input for an inaccessible element, so Drupal
    // ignores it; programmed submissions would bypass that check.
    $state->setProgrammedBypassAccessCheck(FALSE);
    $builder->submitForm($form_object, $state);
    return new SchemaFormSubmission($state->getCompleteForm(), $state, array_map('strval', $state->getErrors()), $state->getValues());
  }

  /**
   * Collects successful browser controls using Drupal's final input paths.
   *
   * @param array $element
   *   The built element whose controls are collected.
   * @param array $values
   *   The collected values, keyed like the browser would post them.
   */
  private function browserValues(array $element, array &$values): void {
    foreach (Element::children($element) as $key) {
      $child = $element[$key];
      if (($child['#access'] ?? TRUE) === FALSE || !empty($child['#disabled'])) {
        continue;
      }
      $type = $child['#type'] ?? '';
      if (in_array($type, ['submit', 'button', 'radio'], TRUE)) {
        continue;
      }
      if (empty($child['#input']) || $type === 'checkboxes') {
        $this->browserValues($child, $values);
        continue;
      }
      if ($type === 'checkbox') {
        if (!empty($child['#value'])) {
          NestedArray::setValue($values, $child['#parents'], $child['#return_value']);
        }
        continue;
      }
      if ($type === 'radios' && ($child['#value'] ?? '') === '') {
        continue;
      }
      NestedArray::setValue($values, $child['#parents'], $child['#value'] ?? '');
    }
  }

  /**
   * {@inheritdoc}
   *
   * Drupal core is installed in vendor/drupal/core, so the app root is
   * vendor/drupal and has no autoload.php. The parent only reads the root to
   * require that file, so point it at the Composer vendor directory for the
   * duration of the call.
   */
  protected function bootEnvironment() {
    $app_root = $this->root;
    $this->root = dirname((new \ReflectionClass(ClassLoader::class))->getFileName(), 2);
    try {
      parent::bootEnvironment();
    }
    finally {
      $this->root = $app_root;
    }
  }

}
