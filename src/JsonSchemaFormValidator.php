<?php

declare(strict_types=1);

namespace SchemaForms;

use JsonSchema\Constraints\BaseConstraint;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Constraints\Factory;
use JsonSchema\Validator;
use Shaper\Validator\JsonSchemaValidator;

/**
 * Checks that a schema is a decoded draft-04 schema with an object root.
 *
 * @internal
 */
final class JsonSchemaFormValidator extends JsonSchemaValidator {

  /**
   * The schema every form root must match, built on first use.
   *
   * It is not serialized; an unserialized validator builds it again.
   */
  private ?object $formRoot = NULL;

  /**
   * Validates schemas against the bundled draft-04 meta-schema.
   *
   * @param \JsonSchema\Validator $validator
   *   The validator whose check mode to use; see setValidator().
   * @param int|null $mode
   *   The JsonSchema\Constraints\Constraint::CHECK_MODE_* flags. NULL uses
   *   the check mode of the validator.
   */
  public function __construct(Validator $validator, ?int $mode = NULL) {
    parent::__construct(['$ref' => SchemaReferenceResolver::metaSchemaUri()], NULL, $mode);
    $this->setValidator($validator);
  }

  /**
   * Installs a validator that only retrieves the bundled meta-schema.
   *
   * The given validator's factory may carry a retriever that fetches any URI,
   * so only its check mode is kept. The installed validator is built on
   * SchemaReferenceResolver::createFactory(), whatever the caller passes.
   *
   * @param \JsonSchema\Validator $validator
   *   The validator whose check mode to use.
   */
  #[\Override]
  public function setValidator(Validator $validator): void {
    $factory = SchemaReferenceResolver::createFactory();
    $factory->setConfig(self::checkModeOf($validator));
    parent::setValidator(new Validator($factory));
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function isValid(mixed $data): bool {
    return parent::isValid($data) && $this->isFormRoot($data);
  }

  /**
   * Keeps the check mode, so an unserialized validator validates the same way.
   *
   * @return string[]
   *   The names of the properties to serialize.
   */
  #[\Override]
  public function __sleep(): array {
    return [...parent::__sleep(), 'checkMode'];
  }

  /**
   * Restores the restricted reference resolver after unserialization.
   *
   * The parent installs a validator that retrieves any URI; setValidator()
   * replaces it with a restricted one in the default check mode.
   */
  #[\Override]
  public function __wakeup(): void {
    $this->setValidator(new Validator());
  }

  /**
   * Reads the check mode a validator's factory is configured with.
   *
   * Both supported json-schema majors keep the factory in a protected
   * BaseConstraint property and offer no getter for it.
   *
   * @param \JsonSchema\Validator $validator
   *   The validator.
   *
   * @return int
   *   The JsonSchema\Constraints\Constraint::CHECK_MODE_* flags.
   *
   * @phpstan-return int-mask-of<Constraint::CHECK_MODE_*>
   */
  private static function checkModeOf(Validator $validator): int {
    $factory = (new \ReflectionProperty(BaseConstraint::class, 'factory'))->getValue($validator);
    assert($factory instanceof Factory);
    return $factory->getConfig();
  }

  /**
   * Checks that the schema is a decoded object whose root type is object.
   *
   * Type casting lets an associative array pass the meta-schema, so the root
   * check runs in normal mode: an array is rejected instead of being read as
   * an object. Running it through the validator leaves errors in the shape of
   * the installed json-schema major.
   *
   * @param mixed $data
   *   The schema that passed the meta-schema validation.
   *
   * @return bool
   *   TRUE if the root is an object schema.
   */
  private function isFormRoot(mixed $data): bool {
    $this->formRoot ??= (object) [
      'type' => 'object',
      'required' => ['type'],
      'properties' => (object) [
        'type' => (object) ['enum' => ['object']],
      ],
    ];
    // getErrors() reads the validator, so its errors need no copy here.
    return $this->validator->validate($data, $this->formRoot, Constraint::CHECK_MODE_NORMAL) === 0;
  }

}
