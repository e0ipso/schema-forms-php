<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\AcceptedSchemas;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\SchemaPreparer;
use SchemaForms\Tests\Fixture\TranslationContainer;

/**
 * The memory of accepted schemas stays small and never outlives a request.
 */
#[CoversClass(AcceptedSchemas::class)]
#[CoversClass(FormGeneratorDrupal::class)]
#[CoversClass(SchemaPreparer::class)]
final class AcceptedSchemasTest extends TestCase {

  use TranslationContainer;

  /**
   * Only an accepted schema reuses its registered copy.
   */
  public function testRegisterReusesOnlyAcceptedCopies(): void {
    $memory = new AcceptedSchemas();
    $preparer = new SchemaPreparer();
    $schema = json_decode('{"type":"object","properties":{"a":{"type":"string"}}}');

    $this->assertNotSame($memory->register($schema, $preparer), $memory->register($schema, $preparer));
    $this->assertFalse($memory->contains($schema));

    $memory->add($schema);
    $copy = $memory->register($schema, $preparer);

    $this->assertTrue($memory->contains($schema));
    $this->assertSame($copy, $memory->register(json_decode(json_encode($schema)), $preparer));
  }

  /**
   * The least recently used schema goes once 16 are remembered.
   */
  public function testLimit(): void {
    $memory = new AcceptedSchemas();
    $schemas = array_map(static fn (int $name): \stdClass => (object) ['title' => $name], range(0, 16));
    array_map([$memory, 'add'], array_slice($schemas, 0, 16));
    $memory->add($schemas[0]);
    $memory->add($schemas[16]);

    $this->assertSame([TRUE, FALSE, TRUE], array_map([$memory, 'contains'], [$schemas[0], $schemas[1], $schemas[16]]));
  }

  /**
   * A schema that cannot be fingerprinted is never remembered.
   */
  public function testUnserializableSchema(): void {
    $memory = new AcceptedSchemas();
    $schema = (object) ['type' => 'object', 'x-handler' => static fn (): bool => TRUE];

    $memory->add($schema);

    $this->assertFalse($memory->contains($schema));
  }

  /**
   * A serialized memory forgets its schemas.
   */
  public function testSerializationForgetsSchemas(): void {
    $memory = new AcceptedSchemas();
    $schema = json_decode('{"type":"object","properties":{"remembered_marker":{"type":"string"}}}');
    $memory->add($schema);
    $memory->register($schema, new SchemaPreparer());

    $serialized = serialize($memory);
    // phpcs:ignore DrupalPractice.FunctionCalls.InsecureUnserialize -- Drupal's form cache restores every class, as this does.
    $restored = unserialize($serialized);

    $this->assertStringNotContainsString('remembered_marker', $serialized);
    $this->assertFalse($restored->contains($schema));
  }

  /**
   * A generator that a form cached before its properties existed still works.
   *
   * Release 3.1.1 serialized the generator with no properties at all.
   */
  public function testGeneratorCachedWithoutProperties(): void {
    $schema = json_decode('{"type":"object","properties":{"a":{"type":"string"}}}');
    $earlier = 'O:' . strlen(FormGeneratorDrupal::class) . ':"' . FormGeneratorDrupal::class . '":0:{}';

    // phpcs:ignore DrupalPractice.FunctionCalls.InsecureUnserialize -- Drupal's form cache restores every class, as this does.
    $generator = unserialize($earlier);

    $this->assertInstanceOf(FormGeneratorDrupal::class, $generator);
    $this->assertArrayHasKey('a', $generator->transform($schema));
    $this->assertArrayHasKey('a', $generator->transform($schema));
  }

}
