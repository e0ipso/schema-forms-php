<?php

namespace SchemaForms;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Shaper\Validator\JsonSchemaValidator;

/**
 * Validates that the input provided conforms to the expectations.
 *
 * @internal
 */
final class JsonSchemaFormValidator extends JsonSchemaValidator {

  /**
   * {@inheritdoc}
   */
  public function __construct(Validator $validator, $mode = NULL) {
    $new_schema = ['$ref' => sprintf('file://%s/meta-schema-draft4.json', dirname(__DIR__))];
    parent::__construct($new_schema, $validator, $mode);
  }

  /**
   * {@inheritdoc}
   */
  public function isValid($data) {
    return parent::isValid($data) && $this->isFormRoot($data);
  }

  /**
   * Keeps the check mode, so an unserialized validator validates the same way.
   *
   * @return string[]
   *   The names of the properties to serialize.
   */
  #[\Override]
  public function __sleep(): array {
    return [...parent::__sleep(), 'checkMode'];
  }

  /**
   * Restores the restricted reference resolver after unserialization.
   *
   * The parent would install a validator that retrieves any URI.
   */
  #[\Override]
  public function __wakeup(): void {
    $this->setValidator(new Validator(SchemaReferenceResolver::createFactory()));
  }

  /**
   * Checks that the schema is a decoded object whose root type is object.
   *
   * Type casting lets an associative array pass the meta-schema, so the root
   * check runs in normal mode: an array is rejected instead of being read as
   * an object. Running it through the validator leaves errors in the shape of
   * the installed json-schema major.
   *
   * @param mixed $data
   *   The schema that passed the meta-schema validation.
   *
   * @return bool
   *   TRUE if the root is an object schema.
   */
  private function isFormRoot($data): bool {
    $root_schema = (object) [
      'type' => 'object',
      'required' => ['type'],
      'properties' => (object) [
        'type' => (object) ['enum' => ['object']],
      ],
    ];
    $num_errors = $this->validator->validate($data, $root_schema, Constraint::CHECK_MODE_NORMAL);
    $this->errors = $this->validator->getErrors();
    return !$num_errors;
  }

}
