<?php

declare(strict_types=1);

namespace SchemaForms\Tests;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Exception\ExceptionInterface;
use SchemaForms\Exception\ValidatorReplacementException;
use SchemaForms\JsonSchemaFormValidator;
use SchemaForms\SchemaReferenceResolver;

/**
 * Unit tests for the \SchemaForms\JsonSchemaFormValidator class.
 */
#[CoversClass(JsonSchemaFormValidator::class)]
class JsonSchemaFormValidatorTest extends TestCase {

  /**
   * Tests which schemas are valid form schemas.
   */
  #[DataProvider('dataProviderIsValid')]
  public function testIsValid(array $schema, bool $expected, string $message): void {
    $validator = new JsonSchemaFormValidator(Constraint::CHECK_MODE_TYPE_CAST);
    // Decode the way json_decode() delivers schemas: maps become objects.
    $data = json_decode(json_encode($schema, JSON_THROW_ON_ERROR));
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
      'a root without properties is valid' => [
        ['type' => 'object'],
        TRUE,
        'Regression: isValid() read $data->properties unguarded, so a root '
        . 'with no properties raised a warning and was rejected.',
      ],
      'a non-object type is invalid' => [
        ['type' => 'array', 'properties' => []],
        FALSE,
        'The generator only builds a form from an object schema.',
      ],
      'a schema invalid against draft-04 is invalid' => [
        ['type' => 'object', 'required' => 'foo'],
        FALSE,
        'Draft-04 requires "required" to be an array of strings.',
      ],
    ];
  }

  /**
   * The validator checks schemas against the bundled meta-schema.
   */
  public function testMetaSchemaReference(): void {
    $validator = new JsonSchemaFormValidator();
    $uri = 'file://' . dirname(__DIR__, 2) . '/meta-schema-draft4.json';
    $this->assertSame($uri, SchemaReferenceResolver::metaSchemaUri());
    $this->assertSame(json_encode(['$ref' => $uri]), $validator->toJSON());
  }

  /**
   * An unserialized validator keeps the restricted resolver and check mode.
   */
  public function testUnserializedValidatorStaysRestricted(): void {
    $validator = new JsonSchemaFormValidator(Constraint::CHECK_MODE_TYPE_CAST);
    $restored = unserialize(serialize($validator), ['allowed_classes' => [JsonSchemaFormValidator::class]]);
    $this->assertInstanceOf(JsonSchemaFormValidator::class, $restored);
    $inner = (fn () => $this->validator)->call($restored);
    $retriever = (fn () => $this->factory->getUriRetriever())->call($inner);
    $this->assertInstanceOf(SchemaReferenceResolver::class, $retriever);
    // Type casting accepts the empty list that encodes an empty properties map.
    $schema = json_decode('{"type":"object","properties":[]}');
    $this->assertTrue($restored->isValid($schema));
    $this->assertFalse($restored->isValid(json_decode('{"type":"string"}')));
  }

  /**
   * The constructor installs a restricted validator in the given check mode.
   *
   * Type casting accepts the empty list that encodes an empty properties
   * map, and normal mode rejects it.
   */
  #[DataProvider('dataProviderCheckMode')]
  public function testConstructorRestrictsTheValidator(int $mode, bool $expected): void {
    $validator = new JsonSchemaFormValidator($mode);
    $inner = (fn () => $this->validator)->call($validator);
    $retriever = (fn () => $this->factory->getUriRetriever())->call($inner);
    $this->assertInstanceOf(SchemaReferenceResolver::class, $retriever);
    $storage_retriever = (fn () => $this->factory->getSchemaStorage()->getUriRetriever())->call($inner);
    $this->assertInstanceOf(SchemaReferenceResolver::class, $storage_retriever);
    $schema = json_decode('{"type":"object","properties":[]}');
    $this->assertSame($expected, $validator->isValid($schema));
  }

  /**
   * Data provider for testConstructorRestrictsTheValidator.
   */
  public static function dataProviderCheckMode(): array {
    return [
      'type cast' => [Constraint::CHECK_MODE_TYPE_CAST, TRUE],
      'normal' => [Constraint::CHECK_MODE_NORMAL, FALSE],
    ];
  }

  /**
   * The default check mode is the normal one.
   */
  public function testDefaultCheckMode(): void {
    $this->assertFalse((new JsonSchemaFormValidator())->isValid(json_decode('{"type":"object","properties":[]}')));
    $this->assertTrue((new JsonSchemaFormValidator())->isValid(json_decode('{"type":"object","properties":{}}')));
  }

  /**
   * Another validator is refused instead of being swapped silently.
   */
  public function testSetValidatorIsRefused(): void {
    $validator = new JsonSchemaFormValidator();
    $inner = (fn () => $this->validator)->call($validator);
    try {
      $validator->setValidator(new Validator());
      $this->fail('setValidator() must refuse another validator.');
    }
    catch (ValidatorReplacementException $e) {
      $this->assertInstanceOf(\LogicException::class, $e);
      $this->assertInstanceOf(ExceptionInterface::class, $e);
      $this->assertStringContainsString('Pass the check mode to the constructor', $e->getMessage());
    }
    $this->assertSame($inner, (fn () => $this->validator)->call($validator));
  }

  /**
   * Tests raw inputs, built the way FormGeneratorDrupal builds the validator.
   *
   * Regression: an associative array passed the type-cast meta-schema check,
   * then reading $data->type raised a warning and the schema was rejected
   * with no error. A decoded root of another type also had no error.
   */
  #[DataProvider('dataProviderRawInput')]
  public function testRawInput(mixed $data, bool $expected, string $message): void {
    $validator = new JsonSchemaFormValidator(Constraint::CHECK_MODE_TYPE_CAST);
    set_error_handler(static function (int $severity, string $error): never {
      throw new \ErrorException($error, 0, $severity);
    });
    try {
      $actual = $validator->isValid($data);
    }
    finally {
      restore_error_handler();
    }
    $this->assertSame($expected, $actual);
    $errors = $validator->getErrors();
    if ($expected) {
      $this->assertSame([], $errors);
      return;
    }
    $this->assertCount(1, $errors);
    $this->assertSame($message, $errors[0]['message']);
  }

  /**
   * Data provider for testRawInput.
   */
  public static function dataProviderRawInput(): array {
    $array_message = 'Array value found, but an object is required';
    return [
      'an array with an empty properties map' => [
        ['type' => 'object', 'properties' => []],
        FALSE,
        $array_message,
      ],
      'an array with a populated properties map' => [
        ['type' => 'object', 'properties' => ['a' => ['type' => 'string']]],
        FALSE,
        $array_message,
      ],
      'an array without properties' => [
        ['type' => 'object'],
        FALSE,
        $array_message,
      ],
      'an empty array' => [[], FALSE, $array_message],
      'a decoded object with an empty properties map' => [
        json_decode('{"type":"object","properties":{}}'),
        TRUE,
        '',
      ],
      'a decoded object with a populated properties map' => [
        json_decode('{"type":"object","properties":{"a":{"type":"string"}}}'),
        TRUE,
        '',
      ],
      'a decoded string root' => [
        json_decode('{"type":"string"}'),
        FALSE,
        'Does not have a value in the enumeration ["object"]',
      ],
      'a decoded root without a type' => [
        json_decode('{}'),
        FALSE,
        'The property type is required',
      ],
      'NULL' => [NULL, FALSE, 'NULL value found, but an object is required'],
    ];
  }

}
