<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\AcceptedSchemas;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\FormTreeBuilder;
use SchemaForms\Drupal\SchemaPreparer;
use SchemaForms\Exception\ExceptionInterface;
use SchemaForms\Exception\InvalidOptionException;
use SchemaForms\Exception\InvalidSchemaException;
use SchemaForms\FormOptions;
use Shaper\Util\Context;

/**
 * Every failure the library reports carries its marker interface.
 *
 * Each exception also extends \InvalidArgumentException, which these
 * failures threw before, so existing catch blocks keep matching.
 */
#[CoversClass(SchemaPreparer::class)]
#[CoversClass(AcceptedSchemas::class)]
#[CoversClass(FormTreeBuilder::class)]
final class LibraryExceptionTest extends TestCase {

  /**
   * Each failure throws the documented library exception.
   */
  #[DataProvider('failureProvider')]
  public function testFailureThrowsLibraryException(\Closure $operation, string $class, string $message): void {
    try {
      $operation();
      $this->fail('The operation must throw.');
    }
    catch (ExceptionInterface $e) {
      $this->assertInstanceOf($class, $e);
      $this->assertInstanceOf(\InvalidArgumentException::class, $e);
      $this->assertStringContainsString($message, $e->getMessage());
    }
  }

  /**
   * Data provider for testFailureThrowsLibraryException().
   */
  public static function failureProvider(): array {
    $schema = json_decode('{"type":"object","properties":{"a":{"type":"string"}}}');
    return [
      'property name read as a render property' => [
        static fn () => (new FormGeneratorDrupal())->transform(json_decode('{"type":"object","properties":{"#process":{"type":"string"}}}')),
        InvalidSchemaException::class,
        'Schema property "properties.#process" starts with "#"',
      ],
      'schema that is not an object' => [
        static fn () => (new SchemaPreparer())->prepare(['type' => 'object']),
        InvalidSchemaException::class,
        'The schema must be a decoded JSON object.',
      ],
      'registered schema that is not a stdClass' => [
        static fn () => (new SchemaPreparer())->prepare(new \ArrayObject()),
        InvalidSchemaException::class,
        'The schema must be a decoded JSON object.',
      ],
      'empty namespace' => [
        static fn () => (new FormGeneratorDrupal())->transform($schema, new Context(['options' => new FormOptions(namespace: '')])),
        InvalidOptionException::class,
        'The "namespace" option must be a non-empty string.',
      ],
      'accepted schema memory without room' => [
        static fn () => new AcceptedSchemas(0),
        InvalidOptionException::class,
        'An accepted schema memory must hold at least one schema.',
      ],
    ];
  }

}
