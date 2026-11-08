<?php

declare(strict_types=1);

namespace SchemaForms\Tests;

use JsonSchema\SchemaStorage;
use JsonSchema\UriRetrieverInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\ReferenceChecker;
use SchemaForms\SchemaUriResolver;

/**
 * The checker loads every reference target through the storage it is given.
 *
 * A recording retriever stands in for the restricted one, so these tests see
 * which targets the checker asks for instead of only the refusal.
 */
#[CoversClass(ReferenceChecker::class)]
#[CoversClass(SchemaUriResolver::class)]
final class ReferenceCheckerTest extends TestCase {

  /**
   * A reference in a branch that validation may never reach is loaded too.
   */
  #[DataProvider('branchProvider')]
  public function testLoadsReferencesInEveryBranch(string $schema): void {
    $this->assertSame(['internal://other/'], self::check($schema, ['internal://other/' => '{"type":"string"}']));
  }

  /**
   * Data provider for testLoadsReferencesInEveryBranch().
   */
  public static function branchProvider(): array {
    $ref = '{"$ref":"internal://other/#"}';
    return [
      'property' => ['{"properties":{"a":' . $ref . '}}'],
      'definition' => ['{"definitions":{"a":' . $ref . '}}'],
      'not' => ['{"not":' . $ref . '}'],
      'list of schemas' => ['{"anyOf":[{"type":"string"},' . $ref . ']}'],
      'else' => ['{"if":{"type":"string"},"else":' . $ref . '}'],
      'dependency' => ['{"dependencies":{"a":' . $ref . '}}'],
      'items' => ['{"items":' . $ref . '}'],
    ];
  }

  /**
   * A "$ref" inside a literal value is data, not a reference.
   */
  public function testIgnoresLiteralValues(): void {
    $schema = '{"default":{"$ref":"internal://other/#"},"enum":[{"$ref":"internal://other/#"}]}';
    $this->assertSame([], self::check($schema, []));
  }

  /**
   * A target that references its referrer is loaded once, and the walk ends.
   */
  public function testLoadsEachTargetOnce(): void {
    $schema = '{"properties":{"a":{"$ref":"internal://other/#"},"b":{"$ref":"internal://other/#"}}}';
    $other = '{"properties":{"back":{"$ref":"internal://root/#"},"self":{"$ref":"#"}}}';
    $this->assertSame(['internal://other/'], self::check($schema, ['internal://other/' => $other]));
  }

  /**
   * An "extends" string is loaded as the validator would retrieve it.
   */
  public function testLoadsExtends(): void {
    $schema = '{"extends":"internal://parent/"}';
    $this->assertSame(['internal://parent/'], self::check($schema, ['internal://parent/' => '{}']));
  }

  /**
   * Checks a schema with storage whose retriever serves the given documents.
   *
   * @param string $schema
   *   The root schema, registered as internal://root/.
   * @param array<string, string> $documents
   *   The documents the retriever serves, keyed by URI.
   *
   * @return list<string>
   *   The URIs the retriever was asked for, in order.
   */
  private static function check(string $schema, array $documents): array {
    $retriever = new class($documents) implements UriRetrieverInterface {

      /**
       * The URIs asked for, in order.
       *
       * @var list<string>
       */
      public array $retrieved = [];

      /**
       * Serves the given documents.
       *
       * @param array<string, string> $documents
       *   The documents, keyed by URI.
       */
      public function __construct(private readonly array $documents) {}

      /**
       * {@inheritdoc}
       */
      #[\Override]
      public function retrieve($uri, $baseUri = NULL): object {
        $this->retrieved[] = $uri;
        return json_decode($this->documents[$uri] ?? '{}');
      }

    };
    $storage = new SchemaStorage($retriever);
    $storage->addSchema('internal://root/', json_decode($schema));
    ReferenceChecker::check($storage->getSchema('internal://root/'), $storage);
    return $retriever->retrieved;
  }

}
