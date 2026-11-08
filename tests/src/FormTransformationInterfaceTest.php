<?php

declare(strict_types=1);

namespace SchemaForms\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SchemaForms\FormGeneratorInterface;
use SchemaForms\FormTransformationInterface;
use Shaper\Transformation\TransformationInterface;
use Shaper\Transformation\TransformationValidationInterface;
use Shaper\Util\Context;
use Shaper\Validator\AcceptValidator;

/**
 * Callers depend on transform() alone; existing generators keep working.
 */
#[CoversNothing]
final class FormTransformationInterfaceTest extends TestCase {

  /**
   * The narrow interface declares transform() and nothing else.
   */
  public function testDeclaresOnlyTransform(): void {
    $methods = array_map(
      static fn (\ReflectionMethod $method): string => $method->getName(),
      (new \ReflectionClass(FormTransformationInterface::class))->getMethods(),
    );
    $this->assertSame(['transform'], $methods);
    $this->assertTrue(is_subclass_of(FormTransformationInterface::class, TransformationInterface::class));
  }

  /**
   * Its transform() has the signature of Shaper's transform().
   */
  public function testTransformKeepsTheShaperSignature(): void {
    $this->assertSame(
      self::signature(new \ReflectionMethod(TransformationInterface::class, 'transform')),
      self::signature(new \ReflectionMethod(FormTransformationInterface::class, 'transform')),
    );
    $this->assertSame(
      self::signature(new \ReflectionMethod(TransformationInterface::class, 'transform')),
      self::signature(new \ReflectionMethod(FormGeneratorInterface::class, 'transform')),
    );
  }

  /**
   * A generator written against the full interface is a narrow one, too.
   */
  public function testExistingGeneratorsSatisfyTheNarrowInterface(): void {
    $generator = new class() implements FormGeneratorInterface {

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function transform($data, ?Context $context = NULL) {
        return ['#type' => 'container', '#schema_type' => $data->type];
      }

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function conformsToExpectedInputShape($data, ?Context $context = NULL) {
        return TRUE;
      }

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function conformsToOutputShape($data, ?Context $context = NULL) {
        return TRUE;
      }

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function getInputValidator() {
        return new AcceptValidator();
      }

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function getOutputValidator() {
        return new AcceptValidator();
      }

    };
    $this->assertInstanceOf(TransformationInterface::class, $generator);
    $this->assertInstanceOf(TransformationValidationInterface::class, $generator);
    $this->assertSame(['#type' => 'container', '#schema_type' => 'object'], self::build($generator));
  }

  /**
   * A replacement generator implements transform() and nothing else.
   */
  public function testOneMethodReplacesTheGenerator(): void {
    $generator = new class() implements FormTransformationInterface {

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function transform($data, ?Context $context = NULL) {
        return ['#markup' => $context?->offsetGet('label')];
      }

    };
    $this->assertNotInstanceOf(FormGeneratorInterface::class, $generator);
    $this->assertSame(['#markup' => 'Form'], self::build($generator));
  }

  /**
   * A caller that needs only transform().
   *
   * @param \SchemaForms\FormTransformationInterface $generator
   *   The generator.
   *
   * @return mixed
   *   The form.
   */
  private static function build(FormTransformationInterface $generator): mixed {
    return $generator->transform(json_decode('{"type":"object"}'), new Context(['label' => 'Form']));
  }

  /**
   * Describes a method's parameters and return type.
   *
   * @param \ReflectionMethod $method
   *   The method.
   *
   * @return array<int, string>
   *   One line per parameter, then the return type.
   */
  private static function signature(\ReflectionMethod $method): array {
    $parameters = array_map(
      static fn (\ReflectionParameter $parameter): string => sprintf(
        '%s $%s%s',
        $parameter->getType() ?? 'untyped',
        $parameter->getName(),
        $parameter->isDefaultValueAvailable() ? ' = ' . var_export($parameter->getDefaultValue(), TRUE) : '',
      ),
      $method->getParameters(),
    );
    return [...$parameters, 'returns ' . ($method->getReturnType() ?? 'untyped')];
  }

}
