<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use SchemaForms\Drupal\FormGeneratorDrupal;
use Shaper\Util\Context;

/**
 * A form that embeds two generated containers for the same schema.
 *
 * Each container sits under its own key and may get its own namespace.
 */
final class TwoSchemaForm implements FormInterface {

  /**
   * Creates the form.
   *
   * @param object $schema
   *   The decoded JSON Schema of both containers.
   * @param array<string, string|null> $namespaces
   *   The namespace of each container, keyed by its form key. NULL leaves the
   *   container without one.
   */
  public function __construct(
    private readonly object $schema,
    private readonly array $namespaces = ['left' => NULL, 'right' => NULL],
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'schema_forms_two_schema_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#tree'] = TRUE;
    foreach ($this->namespaces as $key => $namespace) {
      $context = new Context(['form_state' => $form_state]);
      if ($namespace !== NULL) {
        $context['namespace'] = $namespace;
      }
      $form[$key] = (new FormGeneratorDrupal())->transform($this->schema, $context);
    }
    $form['actions']['save'] = ['#type' => 'submit', '#value' => 'Save'];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
