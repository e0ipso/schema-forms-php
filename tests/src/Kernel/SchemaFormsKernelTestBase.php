<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use Composer\Autoload\ClassLoader;
use Drupal\Core\Form\FormState;
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
