<?php

namespace SchemaForms\Tests;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use SchemaForms\ArrayToStdClass;
use SchemaForms\JsonSchemaFormValidator;

/**
 * @coversDefaultClass \SchemaForms\JsonSchemaFormValidator
 */
class JsonSchemaFormValidatorTest extends TestCase {

  /**
   * @covers ::isValid
   * @dataProvider dataProviderIsValid
   */
  public function testIsValid(array $schema, bool $expected, string $message): void {
    $validator = new JsonSchemaFormValidator(
      new Validator(),
      Constraint::CHECK_MODE_TYPE_CAST
    );
    $data = (new ArrayToStdClass())->transform($schema);
    $this->assertSame($expected, $validator->isValid($data), $message);
  }

  /**
   * Data provider for testIsValid.
   */
  public static function dataProviderIsValid(): array {
    return [
      'a populated properties map is valid' => [
        ['type' => 'object', 'properties' => ['a' => ['type' => 'string']]],
        TRUE,
        'A schema declaring one property is the ordinary case.',
      ],
      'an empty properties map is valid' => [
        ['type' => 'object', 'properties' => []],
        TRUE,
        'A component declaring no props is a legal schema; YAML decodes '
        . '`properties: {}` to an empty PHP array, which must not be rejected.',
      ],
      'a non-object type is invalid' => [
        ['type' => 'array', 'properties' => []],
        FALSE,
        'The generator only builds a form from an object schema.',
      ],
    ];
  }

}
