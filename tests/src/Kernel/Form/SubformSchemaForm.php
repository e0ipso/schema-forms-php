<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\FormOptions;
use Shaper\Util\Context;

/**
 * A form that embeds a generated container in a plugin-style subform.
 *
 * Plugin forms, such as block or condition settings, receive a SubformState
 * whose values are relative to the subform, while element #parents stay
 * absolute. The form validates the generated container through that state,
 * the way a plugin's validateConfigurationForm() would.
 */
final class SubformSchemaForm implements FormInterface {

  /**
   * The form state storage key that validateForm() writes the values to.
   */
  public const VALIDATE_FORM_VALUES = 'schema_forms_test_subform_values';

  /**
   * The generator, kept so validation uses the instance that built the form.
   */
  private FormGeneratorDrupal $generator;

  /**
   * Creates the form.
   *
   * @param object $schema
   *   The decoded JSON Schema.
   */
  public function __construct(private readonly object $schema) {
    $this->generator = new FormGeneratorDrupal();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'schema_forms_subform_schema_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#tree'] = TRUE;
    $form['settings'] = ['#type' => 'container'];
    $form['settings']['schema'] = $this->generator->transform($this->schema, new Context(['options' => new FormOptions(formState: $form_state)]));
    $form['actions']['save'] = ['#type' => 'submit', '#value' => 'Save'];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $this->generator->validateWithSchema($form['settings']['schema'], $subform_state);
    $form_state->set(self::VALIDATE_FORM_VALUES, $form_state->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
