<?php

declare(strict_types=1);

namespace SchemaForms\Tests;

use JsonSchema\Exception\UnresolvableJsonPointerException;
use JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use SchemaForms\Exception\ExceptionInterface;
use SchemaForms\Exception\UnreadableMetaSchemaException;
use SchemaForms\Exception\UnsupportedReferenceException;
use SchemaForms\RecursiveTypeCaster;
use SchemaForms\RestrictedSchemaRegistry;
use SchemaForms\ReferenceChecker;
use SchemaForms\SchemaCopier;
use SchemaForms\SchemaReferenceResolver;
use SchemaForms\SchemaUriResolver;
use SchemaForms\SubmissionRefiner;
use SchemaForms\Tests\Fixture\ReferenceSentinel;

/**
 * Retrieval stays inside registered schemas and bundled metadata.
 */
#[CoversClass(SchemaReferenceResolver::class)]
#[CoversClass(ReferenceChecker::class)]
#[CoversClass(SchemaCopier::class)]
#[CoversClass(SchemaUriResolver::class)]
#[CoversClass(RecursiveTypeCaster::class)]
#[CoversClass(RestrictedSchemaRegistry::class)]
#[CoversClass(SubmissionRefiner::class)]
final class SchemaReferenceResolverTest extends TestCase {

  /**
   * The sentinel also detects the pre-fix retrieval without external hosts.
   *
   * Generation and submission run the same check through the Drupal adapter;
   * see \SchemaForms\Tests\Drupal\SchemaReferenceRetrievalTest.
   *
   * The sentinel replaces the file stream wrapper and restores PHP's own, so
   * the test runs in its own process and leaves any wrapper a test runner
   * installed for the rest of the suite.
   */
  #[DataProvider('externalReferenceProvider')]
  #[RunInSeparateProcess]
  public function testExternalRetrievalIsBlocked(string $scheme): void {
    $file = tempnam(sys_get_temp_dir(), 'schema-ref-');
    file_put_contents($file, '{"type":"string","minLength":2}');
    $uri = $scheme === 'file' ? 'file://' . $file : $scheme . '://127.0.0.1:9/sentinel.json';
    $schema = (object) [
      'type' => 'object',
      'properties' => (object) [
        'field' => (object) ['type' => 'string', 'allOf' => [(object) ['$ref' => $uri]]],
      ],
    ];
    try {
      // Prove the observer is armed for this exact URI before the operation.
      ReferenceSentinel::observe($file, static fn () => file_get_contents($uri));
      $this->assertCount(1, ReferenceSentinel::$reads);
      ReferenceSentinel::observe($file, function () use ($schema): void {
        try {
          (new SubmissionRefiner())->refine(['field' => ''], $schema);
          $this->fail('Optional blank refinement must reject the reference.');
        }
        catch (UnresolvableJsonPointerException $e) {
          $this->assertStringContainsString('retriev', $e->getMessage());
          $this->assertInstanceOf(UnsupportedReferenceException::class, $e);
          $this->assertInstanceOf(ExceptionInterface::class, $e);
        }
      });
      $this->assertSame([], ReferenceSentinel::$reads, 'No file read or HTTP(S) retrieval was attempted.');
    }
    finally {
      unlink($file);
    }
  }

  /**
   * Data provider for testExternalRetrievalIsBlocked().
   */
  public static function externalReferenceProvider(): array {
    return ['file' => ['file'], 'http' => ['http'], 'https' => ['https']];
  }

  /**
   * References inside the registered document resolve for every URI form.
   */
  #[DataProvider('internalReferenceProvider')]
  public function testInternalReferences(string $reference): void {
    $schema = (object) [
      'type' => 'object',
      'definitions' => (object) [
        'label' => (object) ['type' => 'string', 'minLength' => 2],
      ],
      'properties' => (object) ['field' => (object) ['type' => 'string', 'allOf' => [(object) ['$ref' => $reference]]]],
    ];
    $this->assertSame([], (new SubmissionRefiner())->refine(['field' => ''], $schema));
    $validator = new Validator((new RestrictedSchemaRegistry())->register($schema)->factory);
    $data = (object) ['field' => 'valid'];
    $validator->validate($data, $schema);
    $this->assertTrue($validator->isValid());
    $validator->reset();
    $data->field = 'x';
    $validator->validate($data, $schema);
    $this->assertFalse($validator->isValid());
  }

  /**
   * Data provider for testInternalReferences().
   */
  public static function internalReferenceProvider(): array {
    return [
      'fragment' => ['#/definitions/label'],
      'legacy registered URI' => [SchemaReferenceResolver::SCHEMA_URI . '#/definitions/label'],
      'normalized URI' => ['internal://schema-with-refs/#/definitions/label'],
    ];
  }

  /**
   * Registration and refinement leave the caller's schema unchanged.
   */
  public function testCallerSchemaIsNotRewritten(): void {
    $json = '{"type":"object","definitions":{"label":{"type":"string","minLength":2}},"properties":{"field":{"type":"string","allOf":[{"$ref":"#/definitions/label"}]},"list":{"type":"array","items":{"$ref":"#/definitions/label"}}}}';
    $schema = json_decode($json);
    $registered = (new RestrictedSchemaRegistry())->register($schema)->schema;
    $this->assertEquals(json_decode($json), $schema);
    $this->assertNotSame($schema, $registered);
    $this->assertSame('internal://schema-with-refs/#/definitions/label', $registered->properties->field->allOf[0]->{'$ref'});
    $this->assertSame([], (new SubmissionRefiner())->refine(['field' => ''], $schema));
    $this->assertEquals(json_decode($json), $schema);
  }

  /**
   * The deprecated factory helpers still register and read schemas.
   */
  public function testDeprecatedFactoryHelpers(): void {
    $schema = json_decode('{"type":"object","definitions":{"a":{"type":"string"}},"properties":{"x":{"$ref":"#/definitions/a"}}}');
    $factory = SchemaReferenceResolver::createFactory($schema);
    $registered = SchemaReferenceResolver::registeredSchema($factory);
    $this->assertEquals((new RestrictedSchemaRegistry())->register($schema)->schema, $registered);
    $this->assertNotSame($schema, $registered);
    $empty = SchemaReferenceResolver::createFactory();
    $this->assertInstanceOf(SchemaReferenceResolver::class, $empty->getUriRetriever());
  }

  /**
   * A local path as a base URI is refused before any filesystem check.
   *
   * UriResolver runs is_file() and is_dir() on such a base, which a stat
   * recorder on the file wrapper observes. It runs in its own process for the
   * same reason as testExternalRetrievalIsBlocked().
   */
  #[DataProvider('localBaseProvider')]
  #[RunInSeparateProcess]
  public function testLocalBaseIsNotProbed(string $entry): void {
    $sentinel = sys_get_temp_dir() . '/schema-forms-sentinel-' . bin2hex(random_bytes(8));
    $operation = match ($entry) {
      'extends' => static fn () => (new RestrictedSchemaRegistry())->register(
        (object) ['type' => 'object', 'extends' => $sentinel],
      ),
      'retrieve' => static fn () => (new SchemaReferenceResolver())->retrieve('schema.json', $sentinel),
    };
    // Load every class the operation needs before observing the wrapper.
    try {
      $operation();
    }
    catch (UnsupportedReferenceException) {
    }
    try {
      StatRecorder::observe($operation);
      $this->fail('A local base URI must be refused.');
    }
    catch (UnsupportedReferenceException $e) {
      $this->assertStringContainsString('retrieval is disabled', $e->getMessage());
    }
    $this->assertSame([], array_values(array_filter(StatRecorder::$paths, static fn (string $path) => str_contains($path, $sentinel))));
  }

  /**
   * A base that is not valid UTF-8 is refused like a base with no scheme.
   *
   * UriResolver reads such a base as a local path and probes the filesystem.
   */
  public function testInvalidUtf8BaseIsRefused(): void {
    $this->expectException(UnsupportedReferenceException::class);
    $this->expectExceptionMessage('External schema retrieval is disabled.');
    SchemaUriResolver::resolve('', "http://example\xff.com/");
  }

  /**
   * An absolute "extends" URI still reaches the bundled metadata.
   */
  public function testAbsoluteExtendsIsResolved(): void {
    $factory = (new RestrictedSchemaRegistry())->register((object) ['extends' => 'http://json-schema.org/draft-04/schema#'])->factory;
    $metadata = $factory->getSchemaStorage()->getSchema('http://json-schema.org/draft-04/schema#');
    $this->assertSame('http://json-schema.org/draft-04/schema#', $metadata->id);
  }

  /**
   * A "$ref" that is not a string is refused as an unsupported reference.
   */
  public function testNonStringReference(): void {
    $this->expectException(UnsupportedReferenceException::class);
    $this->expectExceptionMessage('A "$ref" value must be a string.');
    (new RestrictedSchemaRegistry())->register((object) ['properties' => (object) ['a' => (object) ['$ref' => 5]]]);
  }

  /**
   * References are checked in branches validation may never reach.
   */
  #[DataProvider('uncheckedBranchProvider')]
  public function testReferencesInEveryBranchAreChecked(string $schema): void {
    $this->expectException(UnsupportedReferenceException::class);
    (new RestrictedSchemaRegistry())->register(json_decode($schema));
  }

  /**
   * Data provider for testReferencesInEveryBranchAreChecked().
   */
  public static function uncheckedBranchProvider(): array {
    $ref = '{"$ref":"http://127.0.0.1:9/sentinel.json"}';
    return [
      'definition' => ['{"definitions":{"unused":' . $ref . '}}'],
      'items' => ['{"type":"array","items":' . $ref . '}'],
      'not' => ['{"not":' . $ref . '}'],
      'referenced definition' => ['{"definitions":{"a":{"$ref":"#/definitions/b"},"b":{"items":' . $ref . '}},"$ref":"#/definitions/a"}'],
      'extends' => ['{"extends":"http://127.0.0.1:9/sentinel.json"}'],
    ];
  }

  /**
   * The retriever refuses every URI but the bundled metadata.
   */
  #[DataProvider('refusedRetrievalProvider')]
  public function testRetrievalIsRefused(mixed $uri, mixed $base): void {
    $this->expectException(UnsupportedReferenceException::class);
    $this->expectExceptionMessage('External schema retrieval is disabled.');
    (new SchemaReferenceResolver())->retrieve($uri, $base);
  }

  /**
   * Data provider for testRetrievalIsRefused().
   */
  public static function refusedRetrievalProvider(): array {
    return [
      'absolute URL' => ['http://127.0.0.1:9/sentinel.json', NULL],
      'relative to a URL' => ['sentinel.json', 'http://127.0.0.1:9/'],
      'non-string URI' => [5, 'http://127.0.0.1:9/'],
      'non-string base' => ['sentinel.json', 5],
    ];
  }

  /**
   * A meta-schema that cannot be read throws a library exception.
   *
   * The recorder makes every file look missing. It runs in its own process
   * for the same reason as testExternalRetrievalIsBlocked().
   */
  #[RunInSeparateProcess]
  public function testUnreadableMetaSchema(): void {
    // Load the classes before the file wrapper hides their files.
    $retriever = new SchemaReferenceResolver();
    $this->assertSame('http://json-schema.org/draft-04/schema#', $retriever->retrieve('http://json-schema.org/draft-04/schema#')->id);
    $this->assertTrue(class_exists(UnreadableMetaSchemaException::class));
    try {
      StatRecorder::observe(static fn () => $retriever->retrieve('http://json-schema.org/draft-04/schema#'));
      $this->fail('An unreadable meta-schema must throw.');
    }
    catch (UnreadableMetaSchemaException $e) {
      $this->assertInstanceOf(ExceptionInterface::class, $e);
      $this->assertStringContainsString('Reinstall the e0ipso/schema-forms package.', $e->getMessage());
    }
    $this->assertSame([dirname(__DIR__, 2) . '/meta-schema-draft4.json'], StatRecorder::$paths);
  }

  /**
   * The bundled meta-schema is addressed by its file URI.
   */
  public function testMetaSchemaUri(): void {
    $this->assertSame('file://' . dirname(__DIR__, 2) . '/meta-schema-draft4.json', SchemaReferenceResolver::metaSchemaUri());
  }

  /**
   * Data provider for testLocalBaseIsNotProbed().
   */
  public static function localBaseProvider(): array {
    return ['extends' => ['extends'], 'retrieve' => ['retrieve']];
  }

  /**
   * The bundled meta-schema validates through each of its allowed URIs.
   */
  #[DataProvider('metadataProvider')]
  public function testBundledMetadata(string $uri): void {
    $schema = (object) ['$ref' => $uri];
    $validator = new Validator((new RestrictedSchemaRegistry())->register($schema)->factory);
    $data = (object) ['type' => 'string'];
    $validator->validate($data, $schema);
    $this->assertTrue($validator->isValid());
    $validator->reset();
    $data->type = 'not-a-type';
    $validator->validate($data, $schema);
    $this->assertFalse($validator->isValid());
  }

  /**
   * Data provider for testBundledMetadata().
   */
  public static function metadataProvider(): array {
    return [
      'bundled file' => ['file://' . dirname(__DIR__, 2) . '/meta-schema-draft4.json'],
      'HTTP alias' => ['http://json-schema.org/draft-04/schema'],
      'HTTPS alias' => ['https://json-schema.org/draft-04/schema#'],
    ];
  }

}

/**
 * Records the paths the file wrapper is asked to stat.
 */
final class StatRecorder {

  /**
   * The stream context PHP sets on every wrapper instance.
   *
   * @var resource|null
   */
  public $context;

  /**
   * The paths seen since observe() started.
   *
   * @var list<string>
   */
  public static array $paths = [];

  /**
   * Runs a callback and records the paths the file wrapper sees.
   */
  public static function observe(callable $callback): mixed {
    self::$paths = [];
    if (!stream_wrapper_unregister('file') || !stream_wrapper_register('file', self::class)) {
      throw new \RuntimeException('Cannot replace the file wrapper.');
    }
    try {
      return $callback();
    }
    finally {
      stream_wrapper_unregister('file');
      stream_wrapper_restore('file');
    }
  }

  /**
   * Records a stat call and reports that the path does not exist.
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps -- PHP names stream wrapper methods.
  public function url_stat(string $path, int $flags): array|false {
    self::$paths[] = $path;
    return FALSE;
  }

  /**
   * Records an open call and refuses it.
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps -- PHP names stream wrapper methods.
  public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool {
    self::$paths[] = $path;
    return FALSE;
  }

}
