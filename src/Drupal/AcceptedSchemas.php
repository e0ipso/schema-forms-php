<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

use SchemaForms\Exception\InvalidOptionException;

/**
 * Remembers the schemas a generator accepted, and their registered copies.
 *
 * Every AJAX rebuild transforms the same schema again. Validating it against
 * the meta-schema, registering it and walking its references would repeat
 * the same work each time. The memory belongs to one generator, so a
 * long-running worker never shares it between requests, and a cached form
 * never stores it.
 *
 * @internal
 */
final class AcceptedSchemas {

  /**
   * How many schemas are remembered unless the caller says otherwise.
   *
   * A form usually builds one schema; the limit bounds memory when a
   * long-lived generator builds many.
   */
  public const DEFAULT_LIMIT = 16;

  /**
   * The accepted schemas, keyed by fingerprint, the most recent last.
   *
   * Each value is the registered copy once one was made, otherwise NULL.
   *
   * @var array<string, \stdClass|null>
   */
  private array $schemas = [];

  /**
   * Creates the memory.
   *
   * @param int $limit
   *   How many schemas to remember, at least one.
   *
   * @throws \InvalidArgumentException
   *   When the limit is below one.
   */
  public function __construct(
    private readonly int $limit = self::DEFAULT_LIMIT,
  ) {
    if ($limit < 1) {
      throw new InvalidOptionException('An accepted schema memory must hold at least one schema.');
    }
  }

  /**
   * Decides whether a schema was accepted before.
   *
   * @param mixed $data
   *   The decoded schema.
   *
   * @return bool
   *   TRUE for a remembered schema. A schema that cannot be fingerprinted is
   *   never remembered.
   */
  public function contains(mixed $data): bool {
    $fingerprint = self::fingerprint($data);
    return $fingerprint !== NULL && array_key_exists($fingerprint, $this->schemas);
  }

  /**
   * Remembers a schema that passed the input validation.
   *
   * @param mixed $data
   *   The decoded schema.
   */
  public function add(mixed $data): void {
    $fingerprint = self::fingerprint($data);
    if ($fingerprint === NULL) {
      return;
    }
    $this->remember($fingerprint, $this->schemas[$fingerprint] ?? NULL);
  }

  /**
   * Registers a schema, reusing the copy made for an accepted schema.
   *
   * @param mixed $data
   *   The decoded schema.
   * @param \SchemaForms\Drupal\SchemaPreparer $preparer
   *   Registers the schema when no copy is remembered.
   *
   * @return \stdClass
   *   The registered copy.
   *
   * @throws \SchemaForms\Exception\UnsupportedReferenceException
   *   When the schema references a document outside itself.
   * @throws \JsonSchema\Exception\RuntimeException
   *   When a reference is not a valid URI.
   * @throws \InvalidArgumentException
   *   When the schema is not an object or a property name starts with '#'.
   */
  public function register(mixed $data, SchemaPreparer $preparer): \stdClass {
    $fingerprint = self::fingerprint($data);
    // Only a schema that passed the input validation is kept: doTransform()
    // is public, and a direct call must not mark a schema as valid.
    if ($fingerprint === NULL || !array_key_exists($fingerprint, $this->schemas)) {
      return $preparer->prepare($data);
    }
    $schema = $this->schemas[$fingerprint] ?? $preparer->prepare($data);
    $this->remember($fingerprint, $schema);
    return $schema;
  }

  /**
   * Keeps the remembered schemas out of serialized forms.
   *
   * A cached form serializes its generator, and the schemas are only worth
   * their size within one request.
   *
   * @return string[]
   *   The names of the properties to serialize.
   */
  public function __sleep(): array {
    return ['limit'];
  }

  /**
   * Records a schema as the most recent, dropping the oldest beyond the limit.
   *
   * @param string $fingerprint
   *   The schema fingerprint.
   * @param \stdClass|null $schema
   *   The registered copy, or NULL until one is made.
   */
  private function remember(string $fingerprint, ?\stdClass $schema): void {
    unset($this->schemas[$fingerprint]);
    $this->schemas[$fingerprint] = $schema;
    $this->schemas = array_slice($this->schemas, -$this->limit, NULL, TRUE);
  }

  /**
   * Fingerprints a decoded schema by value.
   *
   * @param mixed $data
   *   The decoded schema.
   *
   * @return string|null
   *   The fingerprint, or NULL when the value cannot be serialized.
   */
  private static function fingerprint(mixed $data): ?string {
    try {
      return hash('xxh128', serialize($data));
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

}
