<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\FormValidatorDrupal;

/**
 * Submits the README example schema through Drupal form processing.
 */
#[CoversClass(FormGeneratorDrupal::class)]
#[CoversClass(FormValidatorDrupal::class)]
#[RunTestsInSeparateProcesses]
final class ReadmeExampleSubmissionTest extends SchemaFormsKernelTestBase {

  private const SCHEMA = <<<'JSON'
{
  "title": "A registration form",
  "type": "object",
  "required": ["firstName", "lastName"],
  "properties": {
    "firstName": {"type": "string", "title": "First name", "default": "Chuck"},
    "lastName": {"type": "string", "title": "Last name"},
    "age": {"type": "integer", "title": "Age", "description": "(earthian year)"},
    "email": {"type": "string", "format": "email"},
    "bio": {"type": "string", "title": "Bio"},
    "password": {"type": "string", "title": "Password", "minLength": 3},
    "plan": {"type": "string", "enum": ["free", "pro"]},
    "newsletter": {"type": "boolean", "title": "Send me the newsletter"}
  }
}
JSON;

  private const UI_SCHEMA = <<<'JSON'
{
  "age": {"ui:title": "Age of person"},
  "email": {"ui:placeholder": "you@example.com"},
  "bio": {"ui:widget": "textarea"},
  "password": {"ui:widget": "password", "ui:help": "Hint: Make it strong!"},
  "plan": {
    "ui:widget": "select",
    "ui:enum": {"labels": {"mappings": {"pro": "Pro (paid)"}}}
  }
}
JSON;

  private const VALID_VALUES = [
    'firstName' => 'Chuck',
    'lastName' => 'Norris',
    'age' => '42',
    'email' => 'chuck@example.com',
    'bio' => 'Counted to infinity. Twice.',
    'password' => 'roundhouse',
    'plan' => 'pro',
    'newsletter' => '1',
  ];

  /**
   * Valid values pass schema validation and come back type-cast.
   */
  public function testValidValuesValidate(): void {
    $submission = $this->submitSchemaForm($this->schema(), ['schema' => self::VALID_VALUES], $this->uiSchema(), 'schema', TRUE);

    $this->assertSame([], $submission->errors);
    $values = $submission->values['schema'];
    $this->assertSame(42, $values['age']);
    $this->assertTrue($values['newsletter']);
    $this->assertSame('pro', $values['plan']);
  }

  /**
   * A schema violation is set on the element that holds the invalid value.
   */
  public function testInvalidValueErrorsOnItsElement(): void {
    $values = ['password' => 'ab'] + self::VALID_VALUES;
    $submission = $this->submitSchemaForm($this->schema(), ['schema' => $values], $this->uiSchema(), 'schema', TRUE);

    $this->assertSame(['schema][password'], array_keys($submission->errors));
    // The '@message' placeholder HTML-escapes the quotes around the name.
    $this->assertStringContainsString('[JSON Schema violation of &quot;minLength&quot;]', $submission->errors['schema][password']);
  }

  /**
   * The helper places the container under a key in a form without #tree.
   *
   * This pins the harness only. How values reach the validator in this
   * layout is up to the generator, so nothing here asserts on errors.
   */
  public function testContainerUnderKeyInFormWithoutTree(): void {
    $submission = $this->submitSchemaForm($this->schema(), self::VALID_VALUES, $this->uiSchema(), 'schema');

    $this->assertFalse($submission->form['#tree']);
    $container = $submission->form['schema'];
    $this->assertSame('container', $container['#type']);
    $this->assertSame(['schema'], $container['#parents']);
    $this->assertSame(['schema'], $container['#array_parents']);
    $this->assertNotEmpty($container['#element_validate']);
  }

  /**
   * Decodes the README example schema.
   */
  private function schema(): object {
    return json_decode(self::SCHEMA, flags: JSON_THROW_ON_ERROR);
  }

  /**
   * Decodes the README example UI schema.
   */
  private function uiSchema(): array {
    return json_decode(self::UI_SCHEMA, TRUE, flags: JSON_THROW_ON_ERROR);
  }

  /**
   * Untouched optional controls must not reject the README form.
   */
  public function testMinimumSubmissionOmitsRejectedBlanks(): void {
    $submission = $this->submitSchemaForm($this->schema(), ['schema' => ['firstName' => 'Chuck', 'lastName' => 'Norris', 'password' => '', 'age' => '', 'bio' => '']], $this->uiSchema(), 'schema');
    $this->assertSame([], $submission->errors);
    $values = $submission->values['schema'];
    $this->assertArrayNotHasKey('password', $values);
    $this->assertArrayNotHasKey('age', $values);
    $this->assertSame('', $values['bio']);
  }

  /**
   * The README needs only its two required names.
   */
  public function testOnlyRequiredNames(): void {
    $submission = $this->submitSchemaForm($this->schema(), ['schema' => ['firstName' => 'Chuck', 'lastName' => 'Norris']], $this->uiSchema(), 'schema');
    $this->assertSame([], $submission->errors);
    $this->assertArrayNotHasKey('age', $submission->values['schema']);
    $this->assertArrayNotHasKey('password', $submission->values['schema']);
  }

  /**
   * Required blank input stays present and errors on the input itself.
   */
  public function testBlankRequiredLastName(): void {
    $submission = $this->submitSchemaForm($this->schema(), ['schema' => ['firstName' => 'Chuck', 'lastName' => '']], $this->uiSchema(), 'schema');
    $this->assertSame(['schema][lastName'], array_keys($submission->errors));
    $this->assertSame('', $submission->values['schema']['lastName']);
  }

}
