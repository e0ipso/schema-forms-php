<?php

declare(strict_types=1);

namespace SchemaForms;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use SchemaForms\Exception\ValidatorReplacementException;
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
   * @param int $mode
   *   The JsonSchema\Constraints\Constraint::CHECK_MODE_* flags.
   *
   * @phpstan-param int-mask-of<Constraint::CHECK_MODE_*> $mode
   */
  public function __construct(int $mode = Constraint::CHECK_MODE_NORMAL) {
    parent::__construct(['$ref' => SchemaReferenceResolver::metaSchemaUri()], self::restrictedValidator($mode), $mode);
  }

  /**
   * Refuses to replace the restricted validator.
   *
   * Another validator's factory may carry a retriever that fetches any URI.
   * Pass the check mode to the constructor instead.
   *
   * @param \JsonSchema\Validator $validator
   *   The validator that is not installed.
   *
   * @throws \SchemaForms\Exception\ValidatorReplacementException
   *   Always.
   */
  #[\Override]
  public function setValidator(Validator $validator): never {
    throw new ValidatorReplacementException('JsonSchemaFormValidator builds its own restricted validator. Pass the check mode to the constructor instead of calling setValidator().');
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
   * Restores the restricted validator after unserialization.
   *
   * The parent would install a validator that retrieves any URI.
   */
  #[\Override]
  public function __wakeup(): void {
    // __sleep() kept the flags the constructor received.
    /** @var int-mask-of<Constraint::CHECK_MODE_*> $mode */
    $mode = $this->checkMode;
    parent::setValidator(self::restrictedValidator($mode));
  }

  /**
   * Builds a validator that only retrieves the bundled meta-schema.
   *
   * @param int $mode
   *   The JsonSchema\Constraints\Constraint::CHECK_MODE_* flags.
   *
   * @return \JsonSchema\Validator
   *   The validator.
   *
   * @phpstan-param int-mask-of<Constraint::CHECK_MODE_*> $mode
   */
  private static function restrictedValidator(int $mode): Validator {
    $factory = SchemaReferenceResolver::createFactory();
    $factory->setConfig($mode);
    return new Validator($factory);
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
