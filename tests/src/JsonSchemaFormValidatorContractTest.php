<?php

declare(strict_types=1);

namespace SchemaForms\Tests;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use SchemaForms\JsonSchemaFormValidator;
use SchemaForms\SchemaReferenceResolver;
use Shaper\Validator\ValidateableInterface;

/**
 * JsonSchemaFormValidator keeps the Shaper validator contract.
 */
#[CoversClass(JsonSchemaFormValidator::class)]
final class JsonSchemaFormValidatorContractTest extends ValidatorContractTestCase {

  /**
   * {@inheritdoc}
   */
  protected function createValidator(): ValidateableInterface {
    return new JsonSchemaFormValidator(new Validator(SchemaReferenceResolver::createFactory()), Constraint::CHECK_MODE_TYPE_CAST);
  }

  /**
   * {@inheritdoc}
   */
  public static function acceptedInputProvider(): array {
    return [
      'object root' => [json_decode('{"type":"object"}')],
      'object root with properties' => [json_decode('{"type":"object","properties":{"a":{"type":"string"}}}')],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function rejectedInputProvider(): array {
    return [
      'string root' => [json_decode('{"type":"string"}')],
      'invalid draft-04' => [json_decode('{"type":"object","required":"a"}')],
      'associative array' => [['type' => 'object']],
    ];
  }

}
