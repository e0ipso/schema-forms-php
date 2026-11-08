<?php

namespace SchemaForms\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shaper\Validator\ValidateableInterface;

/**
 * The contract every Shaper validator in the library keeps.
 *
 * Callers such as Shaper transformations read isValid() as a boolean and then
 * read getErrors() to explain a rejection. A subclass supplies the validator
 * and inputs it accepts and rejects; the tests here hold for every
 * implementation.
 */
abstract class ValidatorContractTestCase extends TestCase {

  /**
   * Creates the validator under test.
   */
  abstract protected function createValidator(): ValidateableInterface;

  /**
   * Inputs the validator accepts.
   *
   * @return array<string, array{mixed}>
   *   Test cases, each holding one input.
   */
  abstract public static function acceptedInputProvider(): array;

  /**
   * Inputs the validator rejects.
   *
   * @return array<string, array{mixed}>
   *   Test cases, each holding one input.
   */
  abstract public static function rejectedInputProvider(): array;

  /**
   * Values of every PHP type, which a caller may pass by mistake.
   *
   * @return array<string, array{mixed}>
   *   Test cases, each holding one input.
   */
  public static function unexpectedInputProvider(): array {
    // A closed resource still has the resource type and leaks nothing.
    $resource = fopen('php://memory', 'r');
    fclose($resource);
    return [
      'null' => [NULL],
      'true' => [TRUE],
      'integer' => [42],
      'float' => [1.5],
      'infinity' => [INF],
      'string' => ['#type'],
      'empty string' => [''],
      'list' => [[1, 2]],
      'empty array' => [[]],
      'object' => [new \stdClass()],
      'closure' => [static fn (): int => 1],
      'resource' => [$resource],
    ];
  }

  /**
   * An accepted input gives TRUE and no errors.
   */
  #[DataProvider('acceptedInputProvider')]
  public function testAcceptedInput(mixed $data): void {
    $validator = $this->createValidator();
    $this->assertTrue($validator->isValid($data));
    $this->assertSame([], $validator->getErrors());
  }

  /**
   * A rejected input gives FALSE and at least one error.
   */
  #[DataProvider('rejectedInputProvider')]
  public function testRejectedInput(mixed $data): void {
    $validator = $this->createValidator();
    $this->assertFalse($validator->isValid($data));
    $this->assertNotEmpty($validator->getErrors());
  }

  /**
   * Any input gives a boolean, and errors exactly when it is FALSE.
   */
  #[DataProvider('unexpectedInputProvider')]
  public function testUnexpectedInputDoesNotThrow(mixed $data): void {
    $validator = $this->createValidator();
    $valid = $validator->isValid($data);
    $this->assertIsBool($valid);
    $this->assertIsArray($validator->getErrors());
    $this->assertSame($valid, $validator->getErrors() === []);
  }

  /**
   * Errors from a rejection do not survive the next accepted input.
   */
  #[DataProvider('rejectedInputProvider')]
  public function testAcceptedInputClearsEarlierErrors(mixed $rejected): void {
    $validator = $this->createValidator();
    $accepted = array_values(static::acceptedInputProvider())[0][0];
    $this->assertFalse($validator->isValid($rejected));
    $this->assertTrue($validator->isValid($accepted));
    $this->assertSame([], $validator->getErrors());
  }

  /**
   * Resetting errors clears them without another validation.
   */
  #[DataProvider('rejectedInputProvider')]
  public function testResetErrors(mixed $rejected): void {
    $validator = $this->createValidator();
    $this->assertFalse($validator->isValid($rejected));
    $validator->resetErrors();
    $this->assertSame([], $validator->getErrors());
  }

}
