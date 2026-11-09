<?php

declare(strict_types=1);

namespace SchemaForms\Tests;

use JsonSchema\Constraints\Constraint;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SchemaForms\FormTransformationInterface;
use SchemaForms\JsonSchemaFormValidator;
use SchemaForms\Testing\FormTransformationContractTestCase;
use Shaper\Util\Context;

/**
 * The shipped contract test case fails implementations that break it.
 */
#[CoversClass(FormTransformationContractTestCase::class)]
final class FormTransformationContractTestCaseTest extends TestCase {

  /**
   * The shipped cases agree with the input validator FormGeneratorDrupal uses.
   */
  public function testCasesMatchTheLibraryValidator(): void {
    $validator = new JsonSchemaFormValidator(Constraint::CHECK_MODE_TYPE_CAST);
    foreach (FormTransformationContractTestCase::supportedSchemaProvider() as $name => [$schema]) {
      $this->assertTrue($validator->isValid($schema), $name);
    }
    foreach (FormTransformationContractTestCase::invalidSchemaProvider() as $name => [$input]) {
      $this->assertFalse($validator->isValid($input), $name);
    }
  }

  /**
   * An implementation that builds a form from anything fails.
   */
  public function testAcceptingInvalidInputFails(): void {
    $this->expectException(AssertionFailedError::class);
    self::contractFor(static fn (mixed $data): array => [])->testRejectsInvalidSchema(NULL);
  }

  /**
   * A rejection without a message fails.
   */
  public function testSilentRejectionFails(): void {
    $this->expectException(AssertionFailedError::class);
    self::contractFor(static fn (mixed $data): never => throw new \TypeError())->testRejectsInvalidSchema(NULL);
  }

  /**
   * A rejection with another exception class is not mistaken for a pass.
   */
  public function testOtherExceptionsFail(): void {
    $this->expectException(\InvalidArgumentException::class);
    self::contractFor(static fn (mixed $data): never => throw new \InvalidArgumentException('No.'))->testRejectsInvalidSchema(NULL);
  }

  /**
   * An implementation that returns nothing for a supported schema fails.
   */
  public function testBuildingNothingFails(): void {
    $contract = self::contractFor(static fn (mixed $data): mixed => NULL);
    $this->expectException(AssertionFailedError::class);
    $contract->testBuildsWithoutContext((object) ['type' => 'object']);
  }

  /**
   * An implementation that ignores an empty context still has to build.
   */
  public function testBuildingNothingWithContextFails(): void {
    $contract = self::contractFor(static fn (mixed $data, ?Context $context): mixed => $context === NULL ? [] : NULL);
    $contract->testBuildsWithoutContext((object) ['type' => 'object']);
    $this->expectException(AssertionFailedError::class);
    $contract->testBuildsWithEmptyContext((object) ['type' => 'object']);
  }

  /**
   * An implementation that reads an empty context as an option fails.
   */
  public function testEmptyContextThatChangesTheFormFails(): void {
    $contract = self::contractFor(static fn (mixed $data, ?Context $context): array => ['context' => $context !== NULL]);
    $contract->testBuildsWithoutContext((object) ['type' => 'object']);
    $contract->testBuildsWithEmptyContext((object) ['type' => 'object']);
    $this->expectException(AssertionFailedError::class);
    $contract->testEmptyContextMeansNoOptions((object) ['type' => 'object']);
  }

  /**
   * A subclass checks the form type through assertForm().
   */
  public function testAssertFormHook(): void {
    $contract = new class('contract') extends FormTransformationContractTestCase {

      /**
       * {@inheritdoc}
       */
      #[\Override]
      protected function createTransformation(): FormTransformationInterface {
        return new PropertyListGenerator();
      }

      /**
       * {@inheritdoc}
       */
      #[\Override]
      protected function assertForm(mixed $form, object $schema): void {
        $this->assertIsString($form);
      }

    };
    $this->expectException(AssertionFailedError::class);
    $contract->testBuildsWithoutContext((object) ['type' => 'object']);
  }

  /**
   * Creates the contract test case for an implementation.
   *
   * @param \Closure $transform
   *   The body of the implementation's transform().
   *
   * @return \SchemaForms\Testing\FormTransformationContractTestCase
   *   The contract test case, whose test methods are called directly.
   */
  private static function contractFor(\Closure $transform): FormTransformationContractTestCase {
    $transformation = new class($transform) implements FormTransformationInterface {

      /**
       * Remembers the body of transform().
       */
      public function __construct(private readonly \Closure $transform) {}

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function transform($data, ?Context $context = NULL) {
        return ($this->transform)($data, $context);
      }

    };
    return new class('contract', $transformation) extends FormTransformationContractTestCase {

      /**
       * The implementation under test.
       */
      private FormTransformationInterface $transformation;

      /**
       * {@inheritdoc}
       */
      public function __construct(string $name, FormTransformationInterface $transformation) {
        parent::__construct($name);
        $this->transformation = $transformation;
      }

      /**
       * {@inheritdoc}
       */
      #[\Override]
      protected function createTransformation(): FormTransformationInterface {
        return $this->transformation;
      }

    };
  }

}
