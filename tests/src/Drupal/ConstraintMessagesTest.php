<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\ConstraintMessages;
use SchemaForms\Drupal\JsonSchema5ConstraintMessages;
use SchemaForms\Drupal\ValidationMessages;
use SchemaForms\RestrictedSchemaRegistry;
use SchemaForms\Tests\Fixture\TranslationContainer;

/**
 * Common violation messages are translatable and read as before in English.
 *
 * The cases run the real validator, so the json-schema 5 run of the suite
 * checks the 5.x wording and constraint names too.
 */
#[CoversClass(ConstraintMessages::class)]
#[CoversClass(JsonSchema5ConstraintMessages::class)]
#[CoversClass(ValidationMessages::class)]
final class ConstraintMessagesTest extends TestCase {

  use TranslationContainer;

  /**
   * The translatable message renders the validator's message unchanged.
   */
  #[DataProvider('violationProvider')]
  public function testEnglishIsUnchanged(string $property, mixed $value): void {
    [$constraint, $message] = self::violation($property, $value);

    $translatable = ConstraintMessages::translatable($constraint, $message);

    $this->assertInstanceOf(TranslatableMarkup::class, $translatable);
    // The values are escaped, as the whole message was before.
    $this->assertSame(Html::escape($message), (string) $translatable);
    $this->assertSame(
      (string) new TranslatableMarkup('@message [x]', ['@message' => $message]),
      (string) new TranslatableMarkup('@message [x]', ['@message' => $translatable])
    );
  }

  /**
   * Provides property schemas and values that violate one constraint each.
   *
   * @return array<string, array{string, mixed}>
   *   The property schema as JSON, and the value.
   */
  public static function violationProvider(): array {
    return [
      'required' => ['{"type":"object","required":["a"]}', new \stdClass()],
      'type' => ['{"type":["integer","null"]}', 'text'],
      'enum' => ['{"enum":["a","<b>"]}', 'c'],
      'minLength' => ['{"type":"string","minLength":3}', 'ab'],
      'maxLength' => ['{"type":"string","maxLength":1}', 'ab'],
      'minimum' => ['{"type":"number","minimum":1.5}', 1],
      'maximum' => ['{"type":"integer","maximum":3}', 4],
      'pattern' => ['{"type":"string","pattern":"^<[a-z]+>$"}', '1'],
      'minItems' => ['{"type":"array","minItems":2}', ['a']],
      'maxItems' => ['{"type":"array","maxItems":1}', ['a', 'b']],
      'email' => ['{"type":"string","format":"email"}', 'nope'],
      'uri' => ['{"type":"string","format":"uri"}', 'nope'],
      'ipv4' => ['{"type":"string","format":"ipv4"}', 'nope'],
      'hostname' => ['{"type":"string","format":"hostname"}', '-nope-'],
      'date' => ['{"type":"string","format":"date"}', 'nope'],
      'time' => ['{"type":"string","format":"time"}', 'nope'],
      'date-time' => ['{"type":"string","format":"date-time"}', 'nope'],
    ];
  }

  /**
   * Messages no template matches stay as the validator wrote them.
   */
  public function testOtherMessagesAreNotTranslated(): void {
    $this->assertNull(ConstraintMessages::translatable('multipleOf', 'Must be a multiple of 2'));
    $this->assertNull(ConstraintMessages::translatable('minLength', 'Something else'));
    $this->assertSame('Must be a multiple of 2', (string) ValidationMessages::violation('Must be a multiple of 2', 'multipleOf')->getArguments()['@message']);
  }

  /**
   * A site translation of a template reaches the error message.
   */
  public function testTranslationIsUsed(): void {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnCallback(
      static fn (TranslatableMarkup $markup): string => match ($markup->getUntranslatedString()) {
        'Must be at least @minLength characters long' => 'Au moins @minLength caractères',
        '@message [JSON Schema violation of @constraint]' => '@message [violation de @constraint]',
        default => $markup->getUntranslatedString(),
      }
    );
    $container = new ContainerBuilder();
    $container->set('string_translation', $translation);
    \Drupal::setContainer($container);

    $violation = ValidationMessages::violation('Must be at least 3 characters long', 'minLength');

    $this->assertSame('Au moins 3 caractères [violation de &quot;minLength&quot;]', (string) $violation);
  }

  /**
   * Validates a value and returns the one violation it causes.
   *
   * @param string $property
   *   The schema, as JSON.
   * @param mixed $value
   *   The value.
   *
   * @return array{string, string}
   *   The constraint name, in the shape of either json-schema major, and
   *   the message.
   */
  private static function violation(string $property, mixed $value): array {
    $registered = (new RestrictedSchemaRegistry())->register(json_decode($property));
    $validator = new Validator($registered->factory);
    $validator->validate($value, $registered->schema, Constraint::CHECK_MODE_TYPE_CAST);
    $errors = $validator->getErrors();
    self::assertCount(1, $errors);
    $constraint = $errors[0]['constraint'];
    return [is_array($constraint) ? $constraint['name'] : $constraint, $errors[0]['message']];
  }

}
