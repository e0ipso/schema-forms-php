<?php

namespace SchemaForms;

use JsonSchema\Validator;
use Shaper\Validator\JsonSchemaValidator;

/**
 * Validates that the input provided conforms to the expectations.
 */
class JsonSchemaFormValidator extends JsonSchemaValidator {

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
    return parent::isValid($data)
      && $data->type === 'object';
  }

}
