<?php

declare(strict_types=1);

namespace SchemaForms\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SchemaForms\FormTransformationInterface;
use SchemaForms\Testing\FormTransformationContractTestCase;
use Shaper\Util\Context;

/**
 * A decorator wraps any generator without changing its contract.
 */
#[CoversClass(FormTransformationContractTestCase::class)]
final class ContextDefaultsTest extends FormTransformationContractTestCase {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function createTransformation(): FormTransformationInterface {
    return new ContextDefaults(new PropertyListGenerator(), ['namespace' => 'settings', 'label' => 'Settings']);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function assertForm(mixed $form, object $schema): void {
    $this->assertIsArray($form);
    $this->assertSame(array_keys((array) ($schema->properties ?? [])), $form['properties']);
    $this->assertSame(['namespace' => 'settings', 'label' => 'Settings'], $form['context']);
  }

  /**
   * The defaults reach the wrapped generator when the caller passes none.
   */
  public function testDefaultsFillTheContext(): void {
    $form = $this->createTransformation()->transform((object) ['type' => 'object']);
    $this->assertSame(['namespace' => 'settings', 'label' => 'Settings'], $form['context']);
  }

  /**
   * Values the caller passes win, and the caller's context is not changed.
   */
  public function testCallerValuesWin(): void {
    $context = new Context(['label' => 'Mine']);
    $form = $this->createTransformation()->transform((object) ['type' => 'object'], $context);
    $this->assertSame(['label' => 'Mine', 'namespace' => 'settings'], $form['context']);
    $this->assertSame(['label' => 'Mine'], $context->getArrayCopy());
  }

  /**
   * The wrapped generator's exceptions reach the caller unchanged.
   */
  #[DataProvider('exceptionProvider')]
  public function testExceptionsPassThrough(\Throwable $thrown): void {
    $inner = new class($thrown) implements FormTransformationInterface {

      /**
       * Remembers the exception to throw.
       */
      public function __construct(private readonly \Throwable $thrown) {}

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function transform($data, ?Context $context = NULL) {
        throw $this->thrown;
      }

    };
    try {
      (new ContextDefaults($inner, ['label' => 'Settings']))->transform((object) ['type' => 'object']);
    }
    catch (\Throwable $caught) {
      $this->assertSame($thrown, $caught);
      return;
    }
    $this->fail('The exception did not reach the caller.');
  }

  /**
   * Data provider for testExceptionsPassThrough().
   */
  public static function exceptionProvider(): array {
    return [
      'rejected schema' => [new \TypeError('Invalid input.')],
      'unrepresentable schema' => [new \InvalidArgumentException('Unsupported property name.')],
    ];
  }

  /**
   * A schema the wrapped generator cannot represent fails the same way.
   */
  public function testRefusedPropertyName(): void {
    $schema = (object) ['type' => 'object', 'properties' => (object) ['#markup' => (object) ['type' => 'string']]];
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The property name "#markup" starts with "#".');
    $this->createTransformation()->transform($schema);
  }

}
