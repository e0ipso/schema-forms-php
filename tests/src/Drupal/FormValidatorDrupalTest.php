<?php

namespace SchemaForms\Tests\Drupal;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\FormValidatorDrupal;

/**
 * Unit tests for the \SchemaForms\Drupal\FormValidatorDrupal class.
 *
 * The form state is a real \Drupal\Core\Form\FormState. It needs no container
 * as long as no TranslatableMarkup is rendered, so errors are asserted through
 * their untranslated string and arguments.
 *
 * @package SchemaForms
 */
#[CoversClass(FormValidatorDrupal::class)]
#[CoversClass(FormGeneratorDrupal::class)]
class FormValidatorDrupalTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // FormState records "any errors" in a static. Reset it between tests.
    (new FormState())->clearErrors();
    parent::tearDown();
  }

  /**
   * Builds a form state the way Form API leaves it before validation.
   *
   * @param array $values
   *   The submitted values.
   * @param array $input
   *   The raw user input.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The form state.
   */
  private static function formState(array $values, array $input = []): FormStateInterface {
    $form_state = new FormState();
    // Form API always sets the user input to an array before validating.
    $form_state->setUserInput($input);
    $form_state->setValues($values);
    return $form_state;
  }

  /**
   * Builds a form element with its #parents and #array_parents.
   *
   * @param array $parents
   *   The element's parents, used for both #parents and #array_parents.
   * @param string $title
   *   The element title.
   * @param array $children
   *   The child elements, keyed by their form key.
   *
   * @return array
   *   The element.
   */
  private static function element(array $parents, string $title, array $children = []): array {
    return [
      '#title' => $title,
      '#parents' => $parents,
      '#array_parents' => $parents,
    ] + $children;
  }

  /**
   * Reduces the errors on a form state to their arguments.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The arguments of each error's markup, keyed by the error name.
   */
  private static function errorArguments(FormStateInterface $form_state): array {
    return array_map(
      static fn (TranslatableMarkup $error) => $error->getArguments(),
      $form_state->getErrors()
    );
  }

  /**
   * Data provider for testTypeCastRecursive.
   *
   * @return array
   *   The data.
   */
  public static function dataProviderTypeCastRecursive(): array {
    $schema = '{"type":"object","properties":{"count":{"type":"integer"},"ratio":{"type":"number"},"flag":{"type":"boolean"},"tags":{"type":"array","items":{"type":"integer"}}}}';
    $remove = new TranslatableMarkup('Remove');
    return [
      'scalars are cast to their schema types' => [
        $schema,
        ['count' => '3', 'ratio' => '1.5', 'flag' => '1'],
        ['count' => 3, 'ratio' => 1.5, 'flag' => TRUE],
      ],
      'the multivalue scaffolding is cleaned before casting' => [
        $schema,
        [
          'tags' => [
            0 => ['removable_element' => '7', 'remove_one' => $remove],
            2 => ['removable_element' => '9', 'remove_one' => $remove],
            'add_more' => new TranslatableMarkup('Append an item'),
          ],
        ],
        ['tags' => [7, 9]],
      ],
    ];
  }

  /**
   * Tests that the cleaned and cast values are written back.
   */
  #[DataProvider('dataProviderTypeCastRecursive')]
  public function testTypeCastRecursive(string $schema, array $submitted, array $expected): void {
    $element = self::element(['wrapper', 'data'], 'Data');
    $form_state = self::formState(['wrapper' => ['data' => $submitted], 'other' => 'untouched']);
    FormValidatorDrupal::typeCastRecursive($element, $form_state, json_decode($schema));
    $this->assertSame($expected, $form_state->getValue(['wrapper', 'data']));
    $this->assertSame('untouched', $form_state->getValue('other'));
  }

  /**
   * Data provider for testValidData.
   *
   * @return array
   *   The data.
   */
  public static function dataProviderValidData(): array {
    return [
      'an object' => ['{"type":"object","properties":{"foo":{"type":"integer"}}}', ['foo' => 3]],
      'a list' => ['{"type":"array","items":{"type":"string"}}', ['a', 'b']],
      'a scalar' => ['{"type":"string","minLength":1}', 'lorem'],
      // The empty array is a list for a schema that accepts arrays.
      'an empty list for an array' => ['{"type":"array"}', []],
      'NULL for an array' => ['{"type":"array"}', NULL],
      'NULL for a nullable array' => ['{"type":["array","null"]}', NULL],
      // Anywhere else the empty array, and NULL, are the empty object.
      'an empty array for an object' => ['{"type":"object","not":{"type":"array"}}', []],
      'NULL for an object' => ['{"type":"object","not":{"type":"array"}}', NULL],
    ];
  }

  /**
   * Tests that valid data sets no error.
   */
  #[DataProvider('dataProviderValidData')]
  public function testValidData(string $schema, $submitted): void {
    $element = self::element(['data'], 'Data');
    $form_state = self::formState(['data' => $submitted]);
    FormValidatorDrupal::validateWithSchema($element, $form_state, json_decode($schema));
    $this->assertSame([], $form_state->getErrors());
  }

  /**
   * Data provider for testEmptyDataCast.
   *
   * @return array
   *   The data.
   */
  public static function dataProviderEmptyDataCast(): array {
    return [
      'an empty array stays a list for an array schema' => [
        '{"type":"array","minItems":1}',
        [],
        'There must be a minimum of 1 items in the array, 0 found [JSON Schema violation of "minItems"]',
      ],
      'NULL becomes a list for an array schema' => [
        '{"type":"array","minItems":1}',
        NULL,
        'There must be a minimum of 1 items in the array, 0 found [JSON Schema violation of "minItems"]',
      ],
      'an empty array becomes an object for any other schema' => [
        '{"type":"string"}',
        [],
        'Object value found, but a string is required [JSON Schema violation of "type"]',
      ],
      'NULL becomes an object for any other schema' => [
        '{"type":"object","required":["foo"]}',
        NULL,
        'The property foo is required [JSON Schema violation of "required"]',
      ],
    ];
  }

  /**
   * Tests the cast of an empty submission before validating.
   */
  #[DataProvider('dataProviderEmptyDataCast')]
  public function testEmptyDataCast(string $schema, ?array $submitted, string $message): void {
    $element = self::element(['data'], 'Data');
    $form_state = self::formState(['data' => $submitted]);
    FormValidatorDrupal::validateWithSchema($element, $form_state, json_decode($schema));
    $this->assertSame(
      ['data' => ['@full_message' => $message]],
      self::errorArguments($form_state)
    );
  }

  /**
   * Tests that the raw user input is validated when there is no value.
   */
  public function testUserInputFallback(): void {
    $schema = json_decode('{"type":"object","properties":{"foo":{"type":"integer"}}}');
    $element = self::element(['data'], 'Data', [
      'foo' => self::element(['data', 'foo'], 'Foo'),
    ]);
    $form_state = self::formState([], ['data' => ['foo' => 'abc']]);
    FormValidatorDrupal::validateWithSchema($element, $form_state, $schema);
    $this->assertSame(
      [
        'data][foo' => [
          '%element' => 'Foo',
          '@message' => 'String value found, but an integer is required [JSON Schema violation of "type"]',
        ],
      ],
      self::errorArguments($form_state)
    );
  }

  /**
   * Tests that errors land on the element their JSON pointer maps to.
   *
   * The element sits below a parent that is not part of the data, and the
   * multivalue item carries the remove button nesting: the pointer /list/0
   * maps to the item's removable_element, and the buttons are not mapped.
   */
  public function testErrorsOnMappedElements(): void {
    $schema = json_decode('{"type":"object","properties":{"foo":{"type":"integer"},"list":{"type":"array","items":{"type":"string","minLength":3}}}}');
    $element = self::element(['wrapper', 'data'], 'Data', [
      'foo' => self::element(['wrapper', 'data', 'foo'], 'Foo'),
      'list' => self::element(['wrapper', 'data', 'list'], 'List', [
        0 => self::element(['wrapper', 'data', 'list', 0], 'Item wrapper', [
          'removable_element' => self::element(['wrapper', 'data', 'list', 0, 'removable_element'], 'Item'),
          'remove_one' => self::element(['wrapper', 'data', 'list', 0, 'remove_one'], 'Remove'),
        ]),
        'add_more' => self::element(['wrapper', 'data', 'list', 'add_more'], 'Append an item'),
      ]),
    ]);
    $form_state = self::formState(['wrapper' => ['data' => ['foo' => 'abc', 'list' => ['ab']]]]);
    FormValidatorDrupal::validateWithSchema($element, $form_state, $schema);
    $errors = $form_state->getErrors();
    $this->assertSame(['wrapper][data][foo', 'wrapper][data][list][0][removable_element'], array_keys($errors));
    $this->assertSame('%element: @message', $errors['wrapper][data][foo']->getUntranslatedString());
    $this->assertSame(
      [
        'wrapper][data][foo' => [
          '%element' => 'Foo',
          '@message' => 'String value found, but an integer is required [JSON Schema violation of "type"]',
        ],
        'wrapper][data][list][0][removable_element' => [
          '%element' => 'Item',
          '@message' => 'Must be at least 3 characters long [JSON Schema violation of "minLength"]',
        ],
      ],
      self::errorArguments($form_state)
    );
  }

  /**
   * Data provider for testConstraintSuffix.
   *
   * @return array
   *   The data.
   */
  public static function dataProviderConstraintSuffix(): array {
    return [
      'json-schema 5.x names the constraint with a string' => [
        ['constraint' => 'minLength'],
        ' [JSON Schema violation of "minLength"]',
      ],
      'json-schema 6.x names the constraint in an array' => [
        ['constraint' => ['name' => 'minLength', 'params' => ['minLength' => 3]]],
        ' [JSON Schema violation of "minLength"]',
      ],
      'an array constraint without a name' => [['constraint' => ['params' => []]], ''],
      'an empty constraint name' => [['constraint' => ''], ''],
      'a NULL constraint' => [['constraint' => NULL], ''],
      'no constraint at all' => [[], ''],
    ];
  }

  /**
   * Tests the constraint suffix for both json-schema error shapes.
   *
   * Only json-schema 6.x is installed, so the 5.x string shape cannot reach
   * this method through the validator. It is called directly instead.
   */
  #[DataProvider('dataProviderConstraintSuffix')]
  public function testConstraintSuffix(array $error, string $expected): void {
    $method = new \ReflectionMethod(FormValidatorDrupal::class, 'constraintSuffix');
    $this->assertSame($expected, $method->invoke(NULL, $error));
  }

  /**
   * Tests that the form generator's validation callback runs both steps.
   */
  public function testFormGeneratorValidateWithSchema(): void {
    $schema = json_decode('{"type":"object","properties":{"count":{"type":"integer"},"label":{"type":"string","minLength":3}}}');
    $element = self::element(['data'], 'Data', [
      'count' => self::element(['data', 'count'], 'Count'),
      'label' => self::element(['data', 'label'], 'Label'),
    ]) + ['#json_schema' => $schema];
    $form_state = self::formState(['data' => ['count' => '3', 'label' => 'ab']]);
    (new FormGeneratorDrupal())->validateWithSchema($element, $form_state);
    // The type cast wrote the integer back.
    $this->assertSame(['count' => 3, 'label' => 'ab'], $form_state->getValue('data'));
    // The validation set the error on the element.
    $this->assertSame(
      [
        'data][label' => [
          '%element' => 'Label',
          '@message' => 'Must be at least 3 characters long [JSON Schema violation of "minLength"]',
        ],
      ],
      self::errorArguments($form_state)
    );
  }

  /**
   * Tests that the form generator skips an element without a schema.
   */
  public function testFormGeneratorValidateWithoutSchema(): void {
    $element = self::element(['data'], 'Data');
    $form_state = self::formState(['data' => ['count' => '3']]);
    (new FormGeneratorDrupal())->validateWithSchema($element, $form_state);
    $this->assertSame(['count' => '3'], $form_state->getValue('data'));
    $this->assertSame([], $form_state->getErrors());
  }

  /**
   * SCHE-0016: unmatched messages are joined once without an empty title.
   */
  public function testUnmatchedErrorsAreJoinedAndEscapedOnce(): void {
    $element = self::element(['schema'], '');
    $state = self::formState(['schema' => []]);
    $schema = json_decode('{"type":"object","required":["first","second"]}');
    FormValidatorDrupal::validateWithSchema($element, $state, $schema);
    $errors = $state->getErrors();
    $this->assertSame(['schema'], array_keys($errors));
    $error = $errors['schema'];
    $this->assertStringNotContainsString('errors errors', $error->getUntranslatedString());
    $args = $error->getArguments();
    $this->assertSame(['@full_message'], array_keys($args));
    $this->assertSame(1, substr_count($args['@full_message'], 'first'));
    $this->assertSame(1, substr_count($args['@full_message'], 'second'));
    $this->assertStringContainsString('"required"', $args['@full_message']);
    $this->assertStringNotContainsString('&quot;', $args['@full_message']);
    // Use the real Drupal placeholder renderer with an identity translator.
    $translator = $this->createMock(\Drupal\Core\StringTranslation\TranslationInterface::class);
    $translator->method('translateString')->willReturn($error->getUntranslatedString());
    $markup = new TranslatableMarkup($error->getUntranslatedString(), $args, [], $translator);
    $this->assertStringContainsString('&quot;required&quot;', (string) $markup);
    $this->assertStringNotContainsString('&amp;quot;', (string) $markup);
  }

  /**
   * SCHE-0019: values that cannot be JSON encoded must never validate as {}.
   */
  #[DataProvider('unencodableProvider')]
  public function testUnencodableSubmission(mixed $value): void {
    $element = self::element(['schema'], 'Data');
    $state = self::formState(['schema' => ['value' => $value]]);
    FormValidatorDrupal::validateWithSchema($element, $state, (object) ['type' => 'object']);
    $this->assertSame(['schema'], array_keys($state->getErrors()));
    $this->assertSame('The submitted data could not be encoded as JSON. Please check the submitted values.', $state->getErrors()['schema']->getUntranslatedString());
  }

  public static function unencodableProvider(): array {
    return [
      'positive infinity' => [INF],
      'not a number' => [NAN],
      'invalid UTF-8' => ["\xB1"],
    ];
  }

}
