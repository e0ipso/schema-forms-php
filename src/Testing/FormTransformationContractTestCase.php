<?php

declare(strict_types=1);

namespace SchemaForms\Testing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\FormTransformationInterface;
use Shaper\Util\Context;

/**
 * Checks an implementation of FormTransformationInterface against its contract.
 *
 * Extend it in a PHPUnit suite and return the implementation, or a decorator
 * around one, from createTransformation(). The tests check what the interface
 * documents: a decoded draft-04 object schema builds a form, an empty context
 * builds the same form as no context, and any other input throws a
 * \TypeError with a message. Override assertForm() to check the type and shape
 * of the form your implementation returns, and override the providers to add
 * schemas the implementation builds or rejects.
 *
 * It needs phpunit/phpunit, which this package suggests but does not require.
 *
 * @api
 */
abstract class FormTransformationContractTestCase extends TestCase {

  /**
   * Creates the implementation under test.
   *
   * @return \SchemaForms\FormTransformationInterface
   *   A new instance for each test.
   */
  abstract protected function createTransformation(): FormTransformationInterface;

  /**
   * Schemas every implementation builds a form for.
   *
   * @return array<string, array{object}>
   *   Test cases, each holding one decoded schema.
   */
  public static function supportedSchemaProvider(): array {
    return [
      'object root' => [(object) ['type' => 'object']],
      'object root with a string property' => [
        (object) [
          'type' => 'object',
          'properties' => (object) ['name' => (object) ['type' => 'string']],
        ],
      ],
    ];
  }

  /**
   * Inputs that are not a decoded draft-04 schema with an object root.
   *
   * @return array<string, array{mixed}>
   *   Test cases, each holding one input.
   */
  public static function invalidSchemaProvider(): array {
    return [
      'string root' => [(object) ['type' => 'string']],
      'root without a type' => [new \stdClass()],
      'invalid draft-04 keyword' => [(object) ['type' => 'object', 'required' => 'name']],
      'associative array' => [['type' => 'object']],
      'JSON text' => ['{"type":"object"}'],
      'NULL' => [NULL],
    ];
  }

  /**
   * Checks a form the implementation built.
   *
   * The default only requires a result. Override it to assert the type and
   * shape your implementation documents, such as a render array.
   *
   * @param mixed $form
   *   What transform() returned.
   * @param object $schema
   *   The schema the form was built from.
   */
  protected function assertForm(mixed $form, object $schema): void {
    $this->assertNotNull($form, 'transform() returned no form.');
  }

  /**
   * A supported schema builds a form when the caller passes no context.
   *
   * @param object $schema
   *   The decoded schema.
   */
  #[DataProvider('supportedSchemaProvider')]
  public function testBuildsWithoutContext(object $schema): void {
    $this->assertForm($this->createTransformation()->transform($schema), $schema);
  }

  /**
   * A supported schema builds a form with an empty context.
   *
   * @param object $schema
   *   The decoded schema.
   */
  #[DataProvider('supportedSchemaProvider')]
  public function testBuildsWithEmptyContext(object $schema): void {
    $this->assertForm($this->createTransformation()->transform($schema, new Context()), $schema);
  }

  /**
   * An empty context builds the same form as no context.
   *
   * Both mean that the caller sets no option.
   *
   * @param object $schema
   *   The decoded schema.
   */
  #[DataProvider('supportedSchemaProvider')]
  public function testEmptyContextMeansNoOptions(object $schema): void {
    $transformation = $this->createTransformation();
    $this->assertEquals($transformation->transform($schema), $transformation->transform($schema, new Context()));
  }

  /**
   * Any other input throws a \TypeError that says what is wrong.
   *
   * The exception is caught here rather than expected, so the check also
   * holds when this method is called outside the PHPUnit runner.
   *
   * @param mixed $schema
   *   The input.
   */
  #[DataProvider('invalidSchemaProvider')]
  public function testRejectsInvalidSchema(mixed $schema): void {
    $transformation = $this->createTransformation();
    try {
      $transformation->transform($schema);
    }
    catch (\TypeError $e) {
      $this->assertNotSame('', $e->getMessage(), 'The TypeError has no message.');
      return;
    }
    $this->fail('transform() accepted input that is not a decoded draft-04 object schema.');
  }

}
