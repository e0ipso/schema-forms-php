<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\ButtonNames;
use SchemaForms\Drupal\ElementDataPaths;
use SchemaForms\Drupal\ElementScaffold;
use SchemaForms\Drupal\EnumElements;
use SchemaForms\Drupal\ErrorElementMapper;
use SchemaForms\Drupal\FieldBuilder;
use SchemaForms\Drupal\FieldContext;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\FormTreeBuilder;
use SchemaForms\Drupal\FormValidatorDrupal;
use SchemaForms\Drupal\JsonShape;
use SchemaForms\Drupal\Labels;
use SchemaForms\Drupal\MultivalueCallbacks;
use SchemaForms\Drupal\MultivalueWidget;
use SchemaForms\Drupal\MultivalueWidgetBuilder;
use SchemaForms\Drupal\PreservedValues;
use SchemaForms\Drupal\RenderChildren;
use SchemaForms\Drupal\RequiredMarker;
use SchemaForms\Drupal\SavedInput;
use SchemaForms\Drupal\SchemaPreparer;
use SchemaForms\Drupal\SchemaTypes;
use SchemaForms\Drupal\SubmissionPreparer;
use SchemaForms\Drupal\SubmissionValidator;
use SchemaForms\Drupal\SubmittedValue;
use SchemaForms\Drupal\UnsupportedElement;
use SchemaForms\Drupal\ValidationMessages;
use SchemaForms\Drupal\WidgetButton;
use SchemaForms\Drupal\WidgetState;
use SchemaForms\Drupal\WidgetStateStorage;

/**
 * Unit tests for the \SchemaForms\Drupal\FormValidatorDrupal class.
 *
 * The form state is a real \Drupal\Core\Form\FormState. Errors are rendered
 * with a translator that returns each string untranslated, so assertions
 * see the markup a site in English would show.
 *
 * @package SchemaForms
 */
#[CoversClass(FormValidatorDrupal::class)]
#[CoversClass(FormGeneratorDrupal::class)]
#[CoversClass(SubmissionPreparer::class)]
#[CoversClass(SubmissionValidator::class)]
#[CoversClass(ErrorElementMapper::class)]
#[CoversClass(ElementDataPaths::class)]
#[CoversClass(MultivalueWidget::class)]
#[CoversClass(SubmittedValue::class)]
#[CoversClass(ValidationMessages::class)]
#[CoversClass(PreservedValues::class)]
#[CoversClass(RenderChildren::class)]
#[CoversClass(JsonShape::class)]
#[CoversClass(ButtonNames::class)]
#[CoversClass(ElementScaffold::class)]
#[CoversClass(EnumElements::class)]
#[CoversClass(FieldBuilder::class)]
#[CoversClass(FieldContext::class)]
#[CoversClass(FormTreeBuilder::class)]
#[CoversClass(Labels::class)]
#[CoversClass(MultivalueCallbacks::class)]
#[CoversClass(MultivalueWidgetBuilder::class)]
#[CoversClass(RequiredMarker::class)]
#[CoversClass(SavedInput::class)]
#[CoversClass(SchemaPreparer::class)]
#[CoversClass(SchemaTypes::class)]
#[CoversClass(UnsupportedElement::class)]
#[CoversClass(WidgetButton::class)]
#[CoversClass(WidgetState::class)]
#[CoversClass(WidgetStateStorage::class)]
final class FormValidatorDrupalTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $translator = $this->createMock(TranslationInterface::class);
    $translator->method('translateString')->willReturnCallback(
      static fn (TranslatableMarkup $markup): string => $markup->getUntranslatedString()
    );
    $container = new ContainerBuilder();
    $container->set('string_translation', $translator);
    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // FormState records "any errors" in a static. Reset it between tests.
    (new FormState())->clearErrors();
    \Drupal::unsetContainer();
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
   * Renders the errors on a form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, string>
   *   The rendered errors, keyed by the error name.
   */
  private static function renderedErrors(FormStateInterface $form_state): array {
    return array_map('strval', $form_state->getErrors());
  }

  /**
   * Renders the error for an element that shows the violation.
   *
   * @param string $title
   *   The element title.
   * @param string $violation
   *   The violation, as plain text.
   *
   * @return string
   *   The markup.
   */
  private static function elementError(string $title, string $violation): string {
    return '<em class="placeholder">' . $title . '</em>: ' . Html::escape($violation);
  }

  /**
   * Renders the error for violations no element shows.
   *
   * @param string $violations
   *   The violations, as plain text.
   *
   * @return string
   *   The markup.
   */
  private static function containerError(string $violations): string {
    return '<p>Invalid data, please make sure all data is valid according to the schema. Schema validation returned the following errors: ' . Html::escape($violations) . '</p>';
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
    // Library releases differ in whether minItems reports the actual count.
    $messages = [$message, str_replace(', 0 found', '', $message)];
    $this->assertContains(
      self::renderedErrors($form_state),
      array_map(static fn (string $expected): array => ['data' => self::containerError($expected)], $messages)
    );
  }

  /**
   * Tests that the stored value is validated, never the raw user input.
   *
   * Regression: with no stored value the raw input was validated, so input
   * that was never cleaned, cast or stored could satisfy the schema.
   */
  #[DataProvider('dataProviderRawInputIgnored')]
  public function testRawInputIsNotValidated(array $values, array $input, string $expected): void {
    $schema = json_decode('{"type":"object","required":["foo"],"properties":{"foo":{"type":"integer"}}}');
    $element = self::element(['data'], 'Data', [
      'foo' => self::element(['data', 'foo'], 'Foo'),
    ]);
    $form_state = self::formState($values, $input);
    FormValidatorDrupal::validateWithSchema($element, $form_state, $schema);
    // The json-schema majors place a "required" error differently.
    $errors = implode(' ', self::renderedErrors($form_state));
    $this->assertSame($expected === '', $errors === '');
    $this->assertStringContainsString($expected, $errors);
  }

  /**
   * Data provider for testRawInputIsNotValidated.
   *
   * @return array
   *   The data.
   */
  public static function dataProviderRawInputIgnored(): array {
    return [
      'raw input cannot satisfy the schema' => [
        [],
        ['data' => ['foo' => 3]],
        Html::escape('The property foo is required [JSON Schema violation of "required"]'),
      ],
      'raw input cannot fail the schema' => [
        ['data' => ['foo' => 3]],
        ['data' => ['foo' => 'abc']],
        '',
      ],
    ];
  }

  /**
   * Tests that errors land on the element their JSON pointer maps to.
   *
   * The element sits below a parent that is not part of the data, and the
   * multivalue item carries the remove button nesting: the pointer /list/0
   * maps to the item's removable_element, and the buttons are not mapped.
   * The widget and its buttons carry the markers the generator gives them.
   */
  public function testErrorsOnMappedElements(): void {
    $schema = json_decode('{"type":"object","properties":{"foo":{"type":"integer"},"list":{"type":"array","items":{"type":"string","minLength":3}}}}');
    $element = self::element(['wrapper', 'data'], 'Data', [
      'foo' => self::element(['wrapper', 'data', 'foo'], 'Foo'),
      'list' => self::element(['wrapper', 'data', 'list'], 'List', [
        '#max_delta' => 0,
        0 => self::element(['wrapper', 'data', 'list', 0], 'Item wrapper', [
          '#type' => 'container',
          'removable_element' => self::element(['wrapper', 'data', 'list', 0, 'removable_element'], 'Item'),
          'remove_one' => self::element(['wrapper', 'data', 'list', 0, 'remove_one'], 'Remove', ['#type' => 'submit']),
        ]),
        'add_more' => self::element(['wrapper', 'data', 'list', 'add_more'], 'Append an item', ['#type' => 'submit']),
      ]),
    ]);
    $form_state = self::formState(['wrapper' => ['data' => ['foo' => 'abc', 'list' => ['ab']]]]);
    FormValidatorDrupal::validateWithSchema($element, $form_state, $schema);
    $errors = $form_state->getErrors();
    $this->assertSame(['wrapper][data][foo', 'wrapper][data][list][0][removable_element'], array_keys($errors));
    $this->assertSame('%element: @message', $errors['wrapper][data][foo']->getUntranslatedString());
    $this->assertSame(
      [
        'wrapper][data][foo' => self::elementError('Foo', 'String value found, but an integer is required [JSON Schema violation of "type"]'),
        'wrapper][data][list][0][removable_element' => self::elementError('Item', 'Must be at least 3 characters long [JSON Schema violation of "minLength"]'),
      ],
      self::renderedErrors($form_state)
    );
  }

  /**
   * Data provider for testConstraintName.
   *
   * @return array
   *   The data.
   */
  public static function dataProviderConstraintName(): array {
    return [
      'json-schema 5.x names the constraint with a string' => [
        ['constraint' => 'minLength'],
        'minLength',
      ],
      'json-schema 6.x names the constraint in an array' => [
        ['constraint' => ['name' => 'minLength', 'params' => ['minLength' => 3]]],
        'minLength',
      ],
      'an array constraint without a name' => [['constraint' => ['params' => []]], NULL],
      'an empty constraint name' => [['constraint' => ''], NULL],
      'a NULL constraint' => [['constraint' => NULL], NULL],
      'no constraint at all' => [[], NULL],
    ];
  }

  /**
   * Tests the constraint name for both json-schema error shapes.
   *
   * Only json-schema 6.x is installed, so the 5.x string shape cannot reach
   * this method through the validator. It is called directly instead.
   */
  #[DataProvider('dataProviderConstraintName')]
  public function testConstraintName(array $error, ?string $expected): void {
    $method = new \ReflectionMethod(ErrorElementMapper::class, 'constraintName');
    $this->assertSame($expected, $method->invoke(NULL, $error));
  }

  /**
   * Tests that a violation without a constraint name has no suffix.
   */
  public function testViolationWithoutConstraint(): void {
    $this->assertSame('Plain message', ValidationMessages::violation('Plain message', NULL));
    $this->assertSame(
      'A &lt;b&gt; [JSON Schema violation of &quot;type&quot;]',
      (string) ValidationMessages::violation('A <b>', 'type')
    );
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
      ['data][label' => self::elementError('Label', 'Must be at least 3 characters long [JSON Schema violation of "minLength"]')],
      self::renderedErrors($form_state)
    );
  }

  /**
   * The generator runs with collaborators its caller constructs.
   */
  public function testGeneratorWithExplicitCollaborators(): void {
    $generator = new FormGeneratorDrupal(
      new SchemaPreparer(),
      new FormTreeBuilder(new FieldBuilder(new MultivalueWidgetBuilder())),
      new SubmissionPreparer(new PreservedValues()),
      new SubmissionValidator(new ErrorElementMapper()),
    );
    $schema = json_decode('{"type":"object","properties":{"count":{"type":"integer","const":3},"label":{"type":"string","minLength":3}}}');
    $element = $generator->transform($schema);
    $this->assertSame('textfield', $element['label']['#type']);
    $element = self::element(['data'], 'Data', [
      'count' => self::element(['data', 'count'], 'Count') + ['#schema_fixed_value' => 3],
      'label' => self::element(['data', 'label'], 'Label'),
    ]) + ['#json_schema' => $element['#json_schema']];
    $form_state = self::formState(['data' => ['label' => 'ab']]);
    $generator->validateWithSchema($element, $form_state);
    $this->assertSame(['label' => 'ab', 'count' => 3], $form_state->getValue('data'));
    $this->assertSame(['data][label'], array_keys(self::renderedErrors($form_state)));
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
   * Unmatched messages are joined once without an empty title.
   *
   * Each violation is escaped exactly once: the validator's message and the
   * quoted constraint name, but not the markup of the joined list.
   */
  public function testUnmatchedErrorsAreJoinedAndEscapedOnce(): void {
    $element = self::element(['schema'], '');
    $state = self::formState(['schema' => []]);
    $schema = json_decode('{"type":"object","required":["first","a&b"]}');
    FormValidatorDrupal::validateWithSchema($element, $state, $schema);
    $errors = $state->getErrors();
    $this->assertSame(['schema'], array_keys($errors));
    $this->assertStringNotContainsString('errors errors', $errors['schema']->getUntranslatedString());
    $rendered = (string) $errors['schema'];
    $this->assertSame(1, substr_count($rendered, 'first'));
    $this->assertSame(1, substr_count($rendered, 'a&amp;b'));
    $this->assertStringContainsString('&quot;required&quot;], ', $rendered);
    $this->assertStringNotContainsString('&amp;quot;', $rendered);
    $this->assertStringNotContainsString('&amp;amp;', $rendered);
  }

  /**
   * Values that cannot be JSON encoded must never validate as {}.
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
