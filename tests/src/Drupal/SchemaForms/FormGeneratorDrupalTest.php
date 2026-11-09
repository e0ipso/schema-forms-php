<?php

namespace SchemaForms\Tests\Drupal\SchemaForms;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element\Details;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\ButtonNames;
use SchemaForms\Drupal\ElementScaffold;
use SchemaForms\Drupal\EnumElements;
use SchemaForms\Drupal\FieldBuilder;
use SchemaForms\Drupal\FieldContext;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\FormTreeBuilder;
use SchemaForms\Drupal\Labels;
use SchemaForms\Drupal\MultivalueCallbacks;
use SchemaForms\Drupal\MultivalueWidgetBuilder;
use SchemaForms\Drupal\RenderChildren;
use SchemaForms\Drupal\RequiredMarker;
use SchemaForms\Drupal\SavedInput;
use SchemaForms\Drupal\SchemaPreparer;
use SchemaForms\Drupal\SchemaTypes;
use SchemaForms\Drupal\SchemaValidationBridge;
use SchemaForms\Drupal\UnsupportedElement;
use SchemaForms\Drupal\WidgetButton;
use SchemaForms\Drupal\WidgetLocation;
use SchemaForms\Drupal\WidgetState;
use SchemaForms\Drupal\WidgetStateStorage;
use SchemaForms\FormOptions;
use SchemaForms\SubmissionRefiner;
use SchemaForms\Tests\Fixture\ConstantSchemas;
use SchemaForms\Tests\Fixture\TranslationContainer;
use Shaper\Util\Context;

/**
 * Unit tests for \SchemaForms\Drupal\FormGeneratorDrupal.
 *
 * @package SchemaForms
 */
#[CoversClass(FormGeneratorDrupal::class)]
#[CoversClass(ButtonNames::class)]
#[CoversClass(ElementScaffold::class)]
#[CoversClass(EnumElements::class)]
#[CoversClass(FieldBuilder::class)]
#[CoversClass(FieldContext::class)]
#[CoversClass(FormTreeBuilder::class)]
#[CoversClass(Labels::class)]
#[CoversClass(MultivalueCallbacks::class)]
#[CoversClass(MultivalueWidgetBuilder::class)]
#[CoversClass(RenderChildren::class)]
#[CoversClass(RequiredMarker::class)]
#[CoversClass(SavedInput::class)]
#[CoversClass(SchemaPreparer::class)]
#[CoversClass(SchemaTypes::class)]
#[CoversClass(UnsupportedElement::class)]
#[CoversClass(WidgetButton::class)]
#[CoversClass(WidgetState::class)]
#[CoversClass(WidgetStateStorage::class)]
class FormGeneratorDrupalTest extends TestCase {

  use ConstantSchemas;
  use TranslationContainer;

  /**
   * The form generator.
   *
   * @var \SchemaForms\Drupal\FormGeneratorDrupal
   */
  private $sut;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->sut = new FormGeneratorDrupal();
  }

  /**
   * Tests the form generation.
   *
   * @param string $schema
   *   The JSON string containing the schema for the form.
   * @param array $expected_form
   *   The expected form as in Drupal's Form API.
   */
  #[DataProvider('dataProviderFormGeneration')]
  public function testFormGeneration(string $schema, array $expected_form) {
    $expected_form['#element_validate'] = [[SchemaValidationBridge::class, 'elementValidate']];
    $expected_form['#tree'] = TRUE;
    $expected_form['#validate'] = [[SchemaValidationBridge::class, 'formValidate']];
    $data = json_decode($schema);
    $expected_form['#json_schema'] = $data;
    $actual_form = $this->sut->transform($data);
    $this->assertEquals(
      $expected_form,
      $actual_form
    );
  }

  /**
   * Data provider for the testFormGeneration.
   *
   * @return array
   *   The data.
   */
  public static function dataProviderFormGeneration() {
    return [
      'schema default without current input' => [
        '{"type":"object","properties":{"foo":{"type":"string","default":"schema default"}}}',
        [
          'foo' => [
            '#title' => Markup::create('Foo'),
            '#type' => 'textfield',
            '#default_value' => 'schema default',
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
            '#prop_name' => 'foo',
            '#prop_parents' => ['foo'],
          ],
          '#type' => 'container',
        ],
      ],
      [
        '{"type":"object","properties":{"foo":{"type":["string","null"]}}}',
        [
          'foo' => [
            '#title' => Markup::create('Foo'),
            '#type' => 'textfield',
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
            '#prop_name' => 'foo',
            '#prop_parents' => ['foo'],
          ],
          '#type' => 'container',
        ],
      ],
      [
        '{"type":"object","required":["bar"],"properties":{"foo":{"type":"string","title":"The Big Foo","format":"email"},"bar":{"type":"number","description":"It is just a bar"}}}',
        [
          'foo' => [
            '#title' => Markup::create('The Big Foo'),
            '#type' => 'email',
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
            '#prop_name' => 'foo',
            '#prop_parents' => ['foo'],
          ],
          'bar' => [
            '#title' => Markup::create('Bar'),
            // phpcs:ignore DrupalPractice.General.DescriptionT -- The generator passes schema text through untranslated.
            '#description' => 'It is just a bar',
            '#type' => 'number',
            '#required' => TRUE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
            '#prop_name' => 'bar',
            '#prop_parents' => ['bar'],
          ],
          '#type' => 'container',
        ],
      ],
      [
        '{"type":"object","properties":{"foo":{"type":"integer","description":"How many"}}}',
        [
          'foo' => [
            '#title' => Markup::create('Foo'),
            // phpcs:ignore DrupalPractice.General.DescriptionT -- The generator passes schema text through untranslated.
            '#description' => 'How many',
            // Drupal has no 'integer' element, so an integer is a number that
            // steps by one.
            '#type' => 'number',
            '#step' => 1,
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
            '#prop_name' => 'foo',
            '#prop_parents' => ['foo'],
          ],
          '#type' => 'container',
        ],
      ],
      [
        // A type with no Form API counterpart gets an explanation, never an
        // element with a '#type' Drupal renders as an empty string.
        '{"type":"object","properties":{"foo":{"type":"null"}}}',
        [
          'foo' => [
            '#title' => Markup::create('Foo'),
            '#type' => 'item',
            '#input' => FALSE,
            '#markup' => new TranslatableMarkup(
              'This property cannot be edited: there is no form element for the %type type.',
              ['%type' => 'null']
            ),
            '#disabled' => TRUE,
            '#visible' => TRUE,
            '#prop_name' => 'foo',
            '#prop_parents' => ['foo'],
          ],
          '#type' => 'container',
        ],
      ],
      [
        '{"type":"object","properties":{"foo":{"type":"string","const":"The Big Foo"}}}',
        [
          'foo' => [
            '#type' => 'item',
            '#plain_text' => 'The Big Foo',
            '#title' => Markup::create('Foo'),
            '#input' => FALSE,
            '#schema_fixed_value' => 'The Big Foo',
            '#prop_name' => 'foo',
            '#prop_parents' => ['foo'],
          ],
          '#type' => 'container',
        ],
      ],
      [
        '{"type":"object","properties":{"a-foo":{"type":"boolean"}}}',
        [
          'a-foo' => [
            '#title' => Markup::create('A Foo'),
            '#type' => 'checkbox',
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
            '#prop_name' => 'a-foo',
            '#prop_parents' => ['a-foo'],
          ],
          '#type' => 'container',
        ],
      ],
      [
        '{"type":"object","properties":{"foo":{"type":"array","items":{"type":"string","enum":["lor-em","ipsum"]}}}}',
        [
          'foo' => [
            '#title' => Markup::create('Foo'),
            '#type' => 'checkboxes',
            '#element_validate' => [[EnumElements::class, 'validateCheckboxes']],
            '#options' => ['lor-em' => Markup::create('Lor Em'), 'ipsum' => Markup::create('Ipsum')],
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
            '#prop_name' => 'foo',
            '#prop_parents' => ['foo'],
          ],
          '#type' => 'container',
        ],
      ],
      [
        '{"type":"object","properties":{"foo":{"type":"string","enum":["lor-em","ipsum"]}}}',
        [
          'foo' => [
            '#title' => Markup::create('Foo'),
            '#type' => 'radios',
            '#options' => ['lor-em' => Markup::create('Lor Em'), 'ipsum' => Markup::create('Ipsum')],
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
            '#prop_name' => 'foo',
            '#prop_parents' => ['foo'],
          ],
          '#type' => 'container',
        ],
      ],
      // A numeric-string enum keeps its members as option keys. array_merge()
      // used to renumber them to 0, 1, 2, 3, so the browser submitted an index
      // and every choice failed validation against the enumeration.
      [
        '{"type":"object","properties":{"foo":{"type":"string","enum":["10","12","15","20"]}}}',
        [
          'foo' => [
            '#title' => Markup::create('Foo'),
            '#type' => 'radios',
            '#options' => [
              10 => Markup::create('10'),
              12 => Markup::create('12'),
              15 => Markup::create('15'),
              20 => Markup::create('20'),
            ],
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
            '#prop_name' => 'foo',
            '#prop_parents' => ['foo'],
          ],
          '#type' => 'container',
        ],
      ],
      // A `null` member is how a schema spells "no choice made". It used to
      // raise a TypeError against the closure's `string $opt`, which cost the
      // caller every other control on the form, not just this one.
      [
        '{"type":"object","properties":{"foo":{"type":["string","null"],"enum":[null,"lor-em","ipsum"]}}}',
        [
          'foo' => [
            '#title' => Markup::create('Foo'),
            '#type' => 'radios',
            '#options' => [
              '' => new TranslatableMarkup('None'),
              'lor-em' => Markup::create('Lor Em'),
              'ipsum' => Markup::create('Ipsum'),
            ],
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
            '#prop_name' => 'foo',
            '#prop_parents' => ['foo'],
          ],
          '#type' => 'container',
        ],
      ],
      // The same two defects reached the checkboxes case through a second copy
      // of the same closure, which is why #46 -- which patched the radios case
      // alone -- did not fix an array-typed prop with a nullable enum.
      [
        '{"type":"object","properties":{"foo":{"type":"array","items":{"type":["string","null"],"enum":[null,"lor-em"]}}}}',
        [
          'foo' => [
            '#title' => Markup::create('Foo'),
            '#type' => 'checkboxes',
            '#element_validate' => [[EnumElements::class, 'validateCheckboxes']],
            '#options' => ['' => new TranslatableMarkup('None'), 'lor-em' => Markup::create('Lor Em')],
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
            '#prop_name' => 'foo',
            '#prop_parents' => ['foo'],
          ],
          '#type' => 'container',
        ],
      ],
    ];
  }

  /**
   * Tests the form generation.
   *
   * @param string $schema
   *   The JSON string containing the schema for the form.
   * @param array $ui_schema
   *   The JSON string containing the UI schema for the form.
   * @param array $expected_form
   *   The expected form as in Drupal's Form API.
   * @param array $current_input
   *   Saved values to prefill the generated controls.
   */
  #[DataProvider('dataProviderFormGenerationWithUi')]
  public function testFormGenerationWithUi(string $schema, array $ui_schema, array $expected_form, array $current_input = []) {
    Html::resetSeenIds();
    $expected_form['#element_validate'] = [[SchemaValidationBridge::class, 'elementValidate']];
    $expected_form['#tree'] = TRUE;
    $expected_form['#validate'] = [[SchemaValidationBridge::class, 'formValidate']];
    $data = json_decode($schema);
    $expected_form['#json_schema'] = $data;
    $options = new FormOptions(
      uiHints: $ui_schema,
      currentInput: $current_input,
    );
    $context = new Context(['options' => $options]);
    $actual_form = $this->sut->transform($data, $context);
    $this->assertEquals(
      $expected_form,
      $actual_form
    );
  }

  /**
   * Data provider for the testFormGenerationWithUI.
   *
   * @return array
   *   The data.
   */
  public static function dataProviderFormGenerationWithUi(): array {
    $multivalue = [
      '#title' => Markup::create('Labels'),
      '#type' => 'details',
      '#prop_name' => 'tag_labels',
      // phpcs:ignore DrupalPractice.General.DescriptionT -- The generator passes schema text through untranslated.
      '#description' => 'Add useful labels',
      '#placeholder' => 'A label',
      '#prop_parents' => ['tag_labels'],
      '#open' => TRUE,
      '#process' => [
        [Details::class, 'processGroup'],
        [Details::class, 'processAjaxForm'],
        [MultivalueCallbacks::class, 'process'],
      ],
      '#after_build' => [[MultivalueCallbacks::class, 'afterBuild']],
      '#cardinality' => -1,
      '#max_delta' => 1,
      '#prefix' => '<div id="tag-labels-add-more-wrapper">',
      '#suffix' => '</div>',
      '#disabled' => FALSE,
      '#visible' => TRUE,
      '#required' => FALSE,
    ];
    foreach (['first', 'second'] as $delta => $value) {
      $multivalue[$delta] = [
        '#type' => 'container',
        '#prefix' => '<div id="tag-labels-' . $delta . '-container">',
        '#wrapper_id' => 'tag-labels-' . $delta . '-container',
        '#suffix' => '</div>',
        '#attributes' => ['style' => 'position: relative;'],
        'removable_element' => [
          '#title' => new TranslatableMarkup('@title (value @number)', [
            '@title' => Markup::create('Labels'),
            '@number' => $delta + 1,
          ]),
          '#title_display' => 'invisible',
          '#description' => '',
          '#type' => 'textfield',
          '#prop_name' => (string) $delta,
          '#default_value' => $value,
          '#placeholder' => 'A label',
          '#prop_parents' => ['tag_labels', $delta],
          '#disabled' => FALSE,
          '#visible' => TRUE,
        ],
        'remove_one' => [
          '#delta' => $delta,
          '#type' => 'submit',
          '#name' => 'tag_labels-' . $delta . '-remove',
          '#value' => new TranslatableMarkup('⨯'),
          '#validate' => [],
          '#prop_parents' => ['tag_labels', $delta],
          '#attributes' => [
            'class' => ['field-remove-one-submit'],
            'style' => 'opacity: 0.9;display: block;position: absolute;top: -12px;left: -12px;margin: 0;overflow: hidden;width: 24px;height: 24px;padding: 0;border-radius: 15px;',
            'aria-label' => new TranslatableMarkup('Remove @title (value @number)', [
              '@title' => Markup::create('Labels'),
              '@number' => $delta + 1,
            ]),
            'title' => new TranslatableMarkup('Remove @title (value @number)', [
              '@title' => Markup::create('Labels'),
              '@number' => $delta + 1,
            ]),
          ],
          '#limit_validation_errors' => [],
          '#submit' => [[MultivalueCallbacks::class, 'removeSubmit']],
          '#ajax' => [
            'callback' => [MultivalueCallbacks::class, 'removeAjax'],
            'wrapper' => 'tag-labels-add-more-wrapper',
            'effect' => 'fade',
          ],
          '#weight' => 101,
        ],
      ];
    }
    $multivalue['add_more'] = [
      '#type' => 'submit',
      '#name' => 'tag_labels_add_more',
      '#value' => new TranslatableMarkup('Append an item'),
      '#attributes' => [
        'class' => ['field-add-more-submit'],
        'aria-label' => new TranslatableMarkup('Append an item to @title', [
          '@title' => Markup::create('Labels'),
        ]),
      ],
      '#submit' => [[MultivalueCallbacks::class, 'appendSubmit']],
      '#ajax' => [
        'callback' => [MultivalueCallbacks::class, 'appendAjax'],
        'wrapper' => 'tag-labels-add-more-wrapper',
        'effect' => 'fade',
      ],
    ];

    return [
      'nested hints and object default' => [
        '{"type":"object","properties":{"address":{"type":"object","required":["street"],"default":{"street":"schema street"},"properties":{"street":{"type":"string"}}}}}',
        [
          'address' => [
            'ui:title' => 'Postal address',
            'street' => [
              'ui:title' => 'Street address',
              'ui:help' => 'Include the house number',
              'ui:placeholder' => '12 Main Street',
            ],
          ],
        ],
        [
          'address' => [
            '#title' => Markup::create('Postal address'),
            '#type' => 'details',
            '#prop_name' => 'address',
            '#prop_parents' => ['address'],
            '#open' => TRUE,
            // A nested required list shows the marker without #required.
            'street' => [
              '#title' => Markup::create('Street address'),
              '#type' => 'textfield',
              '#default_value' => 'schema street',
              '#prop_name' => 'street',
              // phpcs:ignore DrupalPractice.General.DescriptionT -- The generator passes schema text through untranslated.
              '#description' => 'Include the house number',
              '#placeholder' => '12 Main Street',
              '#prop_parents' => ['address', 'street'],
              '#disabled' => FALSE,
              '#visible' => TRUE,
              '#label_attributes' => ['class' => ['form-required']],
              '#attributes' => ['aria-required' => 'true'],
            ],
            '#disabled' => FALSE,
            '#visible' => TRUE,
            '#required' => FALSE,
          ],
          '#type' => 'container',
        ],
      ],
      'two saved multivalue items with inherited hints' => [
        '{"type":"object","properties":{"tag_labels":{"type":"array","default":["schema first","schema second"],"items":{"type":"string"}}}}',
        [
          'tag_labels' => [
            'ui:title' => 'Labels',
            'ui:help' => 'Add useful labels',
            'ui:placeholder' => 'A label',
          ],
        ],
        ['tag_labels' => $multivalue, '#type' => 'container'],
        ['tag_labels' => ['first', 'second']],
      ],
      'disabled and invisible controls' => [
        '{"type":"object","properties":{"disabled":{"type":"string"},"invisible":{"type":"string"}}}',
        [
          'disabled' => ['ui:enabled' => 0],
          'invisible' => ['ui:visible' => 0],
        ],
        [
          'disabled' => [
            '#title' => Markup::create('Disabled'),
            '#type' => 'textfield',
            '#required' => FALSE,
            '#disabled' => TRUE,
            '#visible' => TRUE,
            '#prop_name' => 'disabled',
            '#prop_parents' => ['disabled'],
          ],
          'invisible' => [
            '#title' => Markup::create('Invisible'),
            '#type' => 'textfield',
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => FALSE,
            '#access' => FALSE,
            '#prop_name' => 'invisible',
            '#prop_parents' => ['invisible'],
          ],
          '#type' => 'container',
        ],
      ],
      'current input overrides schema default' => [
        '{"type":"object","properties":{"foo":{"type":"string","default":"schema default"}}}',
        [],
        [
          'foo' => [
            '#title' => Markup::create('Foo'),
            '#type' => 'textfield',
            '#default_value' => 'saved value',
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
            '#prop_name' => 'foo',
            '#prop_parents' => ['foo'],
          ],
          '#type' => 'container',
        ],
        ['foo' => 'saved value'],
      ],
      [
        '{"type":"object","properties":{"foo":{"type":["string","null"]}}}',
        [
          'foo' => [
            'ui:title' => 'A title',
            'ui:help' => 'Some help text',
            'ui:placeholder' => 'This is a placeholder',
          ],
          '#type' => 'container',
        ],
        [
          'foo' => [
            '#title' => Markup::create('A title'),
            '#type' => 'textfield',
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
            // phpcs:ignore DrupalPractice.General.DescriptionT -- The generator passes schema text through untranslated.
            '#description' => 'Some help text',
            '#placeholder' => 'This is a placeholder',
            '#prop_name' => 'foo',
            '#prop_parents' => ['foo'],
          ],
          '#type' => 'container',
        ],
      ],
      [
        '{"type":"object","properties":{"foo":{"type":["string","null"]},"bar":{"type":"string","enum":["uuid1","uuid2"]}}}',
        [
          'foo' => [
            'ui:widget' => 'hidden',
          ],
          'bar' => [
            'ui:widget' => 'select',
            'ui:enum' => ['labels' => ['mappings' => ['uuid1' => 'My Super Option #1']]],
          ],
          '#type' => 'container',
        ],
        [
          'foo' => [
            '#title' => Markup::create('Foo'),
            '#type' => 'hidden',
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
            '#prop_name' => 'foo',
            '#prop_parents' => ['foo'],
          ],
          'bar' => [
            '#title' => Markup::create('Bar'),
            '#type' => 'select',
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
            '#options' => [
              'uuid1' => Markup::create('My Super Option #1'),
              'uuid2' => Markup::create('Uuid2'),
            ],
            '#prop_name' => 'bar',
            '#prop_parents' => ['bar'],
          ],
          '#type' => 'container',
        ],
      ],
    ];
  }

  /**
   * Tests that a root object without properties yields an empty container.
   *
   * Regression: doTransform() read $schema->properties unguarded, so a root
   * schema without 'properties' raised an undefined-property warning.
   *
   * @param string $schema
   *   The JSON string containing the schema for the form.
   */
  #[DataProvider('dataProviderEmptyRootObject')]
  public function testEmptyRootObject(string $schema): void {
    $data = json_decode($schema);
    $warnings = [];
    set_error_handler(static function (int $errno, string $message) use (&$warnings): bool {
      $warnings[] = $message;
      return TRUE;
    });
    try {
      $actual_form = $this->sut->transform($data);
    }
    finally {
      restore_error_handler();
    }
    $this->assertSame([], $warnings);
    $this->assertEquals(
      [
        '#type' => 'container',
        '#tree' => TRUE,
        '#json_schema' => $data,
        '#element_validate' => [[SchemaValidationBridge::class, 'elementValidate']],
        '#validate' => [[SchemaValidationBridge::class, 'formValidate']],
      ],
      $actual_form
    );
  }

  /**
   * Data provider for testEmptyRootObject.
   *
   * @return array
   *   The data.
   */
  public static function dataProviderEmptyRootObject(): array {
    return [
      'no properties' => ['{"type":"object"}'],
      'empty properties' => ['{"type":"object","properties":{}}'],
    ];
  }

  /**
   * Tests that a root schema whose type is not object is rejected.
   */
  public function testNonObjectRootThrows(): void {
    $this->expectException(\TypeError::class);
    $this->sut->transform(json_decode('{"type":"array","items":{"type":"string"}}'));
  }

  /**
   * Tests that a rejected schema names the problem in the TypeError.
   *
   * Regression: each getInputValidator() call built a new validator, so the
   * message read the errors of one that never ran and always ended in `[]`.
   *
   * @param mixed $data
   *   The rejected schema input.
   * @param string $expected
   *   The error message the TypeError must contain.
   */
  #[DataProvider('dataProviderRejectionMessage')]
  public function testRejectionMessageNamesTheProblem(mixed $data, string $expected): void {
    try {
      $this->sut->transform($data);
      $this->fail('The schema was not rejected.');
    }
    catch (\TypeError $e) {
      $this->assertStringContainsString('received invalid input data', $e->getMessage());
      $this->assertStringNotContainsString('invalid input data: [].', $e->getMessage());
      $this->assertStringContainsString($expected, $e->getMessage());
    }
  }

  /**
   * Data provider for testRejectionMessageNamesTheProblem.
   */
  public static function dataProviderRejectionMessage(): array {
    return [
      'an array schema' => [
        ['type' => 'object', 'properties' => []],
        'Array value found, but an object is required',
      ],
      'a string root' => [json_decode('{"type":"string"}'), 'enumeration'],
      'NULL' => [NULL, 'NULL value found, but an object is required'],
    ];
  }

  /**
   * Tests that a serialized generator rebuilds its input validator.
   */
  public function testSerializationDropsTheInputValidator(): void {
    $schema = json_decode('{"type":"object","properties":{"a":{"type":"string"}}}');
    $this->sut->transform($schema);
    $serialized = serialize($this->sut);
    $this->assertStringNotContainsString('JsonSchemaFormValidator', $serialized);
    // phpcs:ignore DrupalPractice.FunctionCalls.InsecureUnserialize -- Drupal's form cache restores every class, as this does.
    $generator = unserialize($serialized);
    $this->assertArrayHasKey('a', $generator->transform($schema));
  }

  /**
   * Tests that property names starting with '#' are rejected.
   *
   * Regression: such a name became a render property, so a schema could set
   * '#process' or '#element_validate' to a callback Drupal then called.
   *
   * @param string $schema
   *   The JSON string containing the schema for the form.
   * @param string $path
   *   The property path the exception message must name.
   */
  #[DataProvider('dataProviderHashPropertyNames')]
  public function testHashPropertyNamesThrow(string $schema, string $path): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage(sprintf('"%s"', $path));
    $this->sut->transform(json_decode($schema));
  }

  /**
   * Data provider for testHashPropertyNamesThrow.
   *
   * @return array
   *   The data.
   */
  public static function dataProviderHashPropertyNames(): array {
    return [
      'root' => [
        '{"type":"object","properties":{"#process":{"type":"string","title":"var_dump"}}}',
        'properties.#process',
      ],
      'nested object' => [
        '{"type":"object","properties":{"address":{"type":"object","properties":{"street":{"type":"string"},"#element_validate":{"type":"string"}}}}}',
        'properties.address.properties.#element_validate',
      ],
      'array items with no rendered item yet' => [
        '{"type":"object","properties":{"list":{"type":"array","items":{"type":"object","properties":{"#after_build":{"type":"string"}}}}}}',
        'properties.list.items.properties.#after_build',
      ],
    ];
  }

  /**
   * Tests that a disabled parent disables every descendant.
   *
   * Regression: each element took #disabled from its own 'ui:enabled' only,
   * so the sub-fields of a disabled object or array stayed editable and Drupal
   * processed their input.
   *
   * @param array $ui_schema
   *   The UI schema for the form.
   * @param array $expected
   *   The expected #disabled values, keyed by dotted element path.
   */
  #[DataProvider('dataProviderDisabledParent')]
  public function testDisabledParentDisablesChildren(array $ui_schema, array $expected): void {
    $schema = '{"type":"object","properties":{'
      . '"o":{"type":"object","properties":{"role":{"type":"string"},"inner":{"type":"object","properties":{"deep":{"type":"string"}}}}},'
      . '"list":{"type":"array","items":{"type":"object","properties":{"name":{"type":"string"}}}},'
      . '"plain":{"type":"string"}}}';
    $options = new FormOptions(
      uiHints: $ui_schema,
      currentInput: ['list' => [['name' => 'a'], ['name' => 'b']]],
    );
    $context = new Context(['options' => $options]);
    $actual_form = $this->sut->transform(json_decode($schema), $context);
    ksort($expected);
    $this->assertSame($expected, static::collectDisabled($actual_form));
  }

  /**
   * Data provider for testDisabledParentDisablesChildren.
   *
   * @return array
   *   The data.
   */
  public static function dataProviderDisabledParent(): array {
    $all_enabled = [
      'o' => FALSE,
      'o.role' => FALSE,
      'o.inner' => FALSE,
      'o.inner.deep' => FALSE,
      'list' => FALSE,
      'list.0.removable_element' => FALSE,
      'list.0.removable_element.name' => FALSE,
      'list.1.removable_element' => FALSE,
      'list.1.removable_element.name' => FALSE,
      'plain' => FALSE,
    ];
    return [
      'no ui:enabled' => [[], $all_enabled],
      'disabled object with an enabled child' => [
        ['o' => ['ui:enabled' => 0, 'inner' => ['ui:enabled' => 1]]],
        [
          'o' => TRUE,
          'o.role' => TRUE,
          'o.inner' => TRUE,
          'o.inner.deep' => TRUE,
        ] + $all_enabled,
      ],
      'disabled array of objects with an enabled child' => [
        ['list' => ['ui:enabled' => 0, 'name' => ['ui:enabled' => 1]]],
        [
          'list' => TRUE,
          'list.0.removable_element' => TRUE,
          'list.0.removable_element.name' => TRUE,
          'list.1.removable_element' => TRUE,
          'list.1.removable_element.name' => TRUE,
        ] + $all_enabled,
      ],
    ];
  }

  /**
   * Collects the #disabled value of every element that sets one.
   *
   * @param array $element
   *   The render array.
   * @param string $path
   *   The dotted path of $element.
   *
   * @return array
   *   The #disabled values, keyed by dotted element path, sorted by key.
   */
  private static function collectDisabled(array $element, string $path = ''): array {
    $disabled = [];
    foreach ($element as $key => $child) {
      if (!is_array($child) || str_starts_with((string) $key, '#')) {
        continue;
      }
      $child_path = $path === '' ? (string) $key : $path . '.' . $key;
      if (array_key_exists('#disabled', $child)) {
        $disabled[$child_path] = $child['#disabled'];
      }
      $disabled += static::collectDisabled($child, $child_path);
    }
    ksort($disabled);
    return $disabled;
  }

  /**
   * Null does not hide an editable member of a union.
   */
  #[DataProvider('nullableTypeProvider')]
  public function testNullFirstTypes(array $types, string $expected): void {
    $schema = (object) ['type' => 'object', 'properties' => (object) ['field' => (object) ['type' => $types]]];
    $field = $this->sut->transform($schema)['field'];
    $this->assertSame($expected, $field['#type']);
    $this->assertSame($expected === 'item', $field['#disabled']);
    if ($expected === 'item') {
      $this->assertStringContainsString('cannot be edited', $field['#markup']->getUntranslatedString());
    }
  }

  /**
   * Provides type unions with null and the element each renders.
   *
   * @return array
   *   The test cases.
   */
  public static function nullableTypeProvider(): array {
    return [
      'null first string' => [['null', 'string'], 'textfield'],
      'null first number' => [['null', 'number'], 'number'],
      'null only' => [['null'], 'item'],
    ];
  }

  /**
   * Falsy and structured constants are non-input plain text.
   */
  #[DataProvider('constantProvider')]
  public function testEveryConstant(mixed $value, string $text): void {
    $field = $this->sut->transform(self::constantSchema($value))['fixed'];
    $this->assertSame($text, $field['#plain_text']);
    $this->assertFalse($field['#input']);
    $this->assertSame('item', $field['#type']);
    $this->assertEquals($value, $field['#schema_fixed_value']);
  }

  /**
   * The label used to check that only titles and option labels are filtered.
   */
  private const UNTRUSTED_LABEL = '<img src="x" onerror="alert(1)"><em>emphasis</em><strong>strong</strong><a href="https://example.com">link</a>';

  /**
   * Title filtering preserves other metadata and fixed text.
   */
  public function testOtherMetadataIsUnchanged(): void {
    $schema = (object) [
      'type' => 'object',
      'properties' => (object) [
        'fixed' => (object) ['type' => 'string', 'const' => self::UNTRUSTED_LABEL],
        'field' => (object) ['type' => 'string', 'description' => self::UNTRUSTED_LABEL],
      ],
    ];
    $options = new FormOptions(
      uiHints: ['field' => ['ui:placeholder' => self::UNTRUSTED_LABEL]],
    );
    $form = (new FormGeneratorDrupal())->transform($schema, new Context(['options' => $options]));
    $this->assertSame(self::UNTRUSTED_LABEL, $form['fixed']['#plain_text']);
    $this->assertSame(self::UNTRUSTED_LABEL, (string) $form['field']['#description']);
    $this->assertSame(self::UNTRUSTED_LABEL, $form['field']['#placeholder']);
  }

  /**
   * Fractional keys survive in both enum controls.
   */
  #[DataProvider('fractionalEnumProvider')]
  public function testFractionalEnumOptions(bool $multiple): void {
    $property = (object) ['type' => 'number', 'enum' => [0.5, 1.5, 1.75]];
    if ($multiple) {
      $property = (object) ['type' => 'array', 'items' => $property];
    }
    $schema = (object) ['type' => 'object', 'properties' => (object) ['field' => $property]];
    $options = new FormOptions(
      uiHints: [
        'field' => ['ui:enum' => ['labels' => ['mappings' => ['0.5' => '<script>bad</script><em>Half</em>']]]],
      ],
    );
    $context = new Context(['options' => $options]);
    $field = $this->sut->transform($schema, $context)['field'];
    $this->assertSame(['0.5', '1.5', '1.75'], array_keys($field['#options']));
    $this->assertSame('bad<em>Half</em>', (string) $field['#options']['0.5']);
  }

  /**
   * Provides whether to build checkboxes instead of radios.
   *
   * @return array
   *   The test cases.
   */
  public static function fractionalEnumProvider(): array {
    return ['radios' => [FALSE], 'checkboxes' => [TRUE]];
  }

  /**
   * Boolean and null members get readable labels unless a mapping names them.
   *
   * @param bool $multiple
   *   Whether to build checkboxes instead of radios.
   */
  #[DataProvider('fractionalEnumProvider')]
  public function testBooleanAndNullEnumLabels(bool $multiple): void {
    $property = (object) ['enum' => [TRUE, FALSE, NULL]];
    if ($multiple) {
      $property = (object) ['type' => 'array', 'items' => $property];
    }
    $schema = (object) ['type' => 'object', 'properties' => (object) ['field' => $property, 'mapped' => $property]];
    $options = new FormOptions(
      uiHints: ['mapped' => ['ui:enum' => ['labels' => ['mappings' => ['1' => 'Yes', '' => 'Unset']]]]],
    );
    $context = new Context(['options' => $options]);
    $form = $this->sut->transform($schema, $context);
    $this->assertSame([1, 0, ''], array_keys($form['field']['#options']));
    $this->assertEquals([
      1 => new TranslatableMarkup('True'),
      0 => new TranslatableMarkup('False'),
      '' => new TranslatableMarkup('None'),
    ], $form['field']['#options']);
    $this->assertEquals([
      1 => Markup::create('Yes'),
      0 => new TranslatableMarkup('False'),
      '' => Markup::create('Unset'),
    ], $form['mapped']['#options']);
  }

  /**
   * AJAX wraps the final real delta, including after removal.
   */
  #[DataProvider('ajaxDeltaProvider')]
  public function testAppendAjaxUsesRealDelta(array $indices): void {
    $state = new FormState();
    (new WidgetStateStorage($state))->set(new WidgetLocation(['items'], 'items'), [
      'items_indices' => $indices,
      'array_parents' => [],
    ]);
    $schema = json_decode('{"type":"object","properties":{"items":{"type":"array","items":{"type":"string"}}}}');
    $form = $this->sut->transform($schema, new Context(['options' => new FormOptions(formState: $state)]));
    $state->setTriggeringElement(['#array_parents' => ['items', 'add_more']]);
    $result = MultivalueCallbacks::appendedWidget($form, $state);
    $this->assertSame(array_keys($form['items']), array_keys($result));
    if (!$indices) {
      $this->assertSame($form['items'], $result);
      return;
    }
    $last = end($indices);
    foreach ($indices as $delta) {
      $expected = $form['items'][$delta]['#prefix'];
      if ($delta === $last) {
        $expected = '<div class="ajax-new-content">' . $expected;
      }
      $this->assertSame($expected, $result[$delta]['#prefix']);
    }
    $this->assertSame($form['items'][$last]['#suffix'] . '</div>', $result[$last]['#suffix']);
  }

  /**
   * Provides stored item deltas, including gaps left by removals.
   *
   * @return array
   *   The test cases.
   */
  public static function ajaxDeltaProvider(): array {
    return ['three items' => [[0, 1, 2]], 'gap' => [[0, 2]], 'removed first' => [[1, 2]], 'empty' => [[]]];
  }

  /**
   * Every AJAX reference shares one safe, unique container ID.
   */
  public function testItemIdsAcrossForms(): void {
    $name = 'odd " field';
    $schema = (object) [
      'type' => 'object',
      'properties' => (object) [$name => (object) ['type' => 'array', 'items' => (object) ['type' => 'string']]],
    ];
    $ids = [];
    foreach (range(1, 2) as $form_number) {
      $options = new FormOptions(currentInput: [$name => ['one', 'two']]);
      $form = $this->sut->transform($schema, new Context(['options' => $options]));
      foreach ([0, 1] as $delta) {
        $item = $form[$name][$delta];
        $id = $item['#wrapper_id'];
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/D', $id, 'Form ' . $form_number);
        $this->assertSame('<div id="' . $id . '">', $item['#prefix']);
        $this->assertSame($form[$name]['add_more']['#ajax']['wrapper'], $item['remove_one']['#ajax']['wrapper']);
        $ids[] = $id;
      }
    }
    $this->assertCount(4, array_unique($ids));
  }

  /**
   * Decoded objects and associative saved values are equivalent.
   */
  public function testObjectCurrentInput(): void {
    $schema = json_decode('{"type":"object","properties":{"profile":{"type":"object","properties":{"name":{"type":"string","default":"fallback"},"tags":{"type":"array","items":{"type":"object","properties":{"label":{"type":"string"}}}}}},"other":{"type":"string","default":"default"}}}');
    $input = '{"profile":{"name":"saved","tags":[{"label":"first"},{"label":"second"}]}}';
    Html::resetSeenIds();
    $array_form = $this->sut->transform($schema, new Context(['options' => new FormOptions(currentInput: json_decode($input, TRUE))]));
    Html::resetSeenIds();
    $object_form = $this->sut->transform($schema, new Context(['options' => new FormOptions(currentInput: json_decode($input))]));
    $this->assertEquals($array_form, $object_form);
    $this->assertSame('saved', $object_form['profile']['name']['#default_value']);
    $this->assertSame('second', $object_form['profile']['tags'][1]['removable_element']['label']['#default_value']);
    $this->assertSame('default', $object_form['other']['#default_value']);
  }

  /**
   * Enum controls infer types; fixed and unsupported schemas render safely.
   */
  #[DataProvider('enumOnlyControlProvider')]
  public function testEnumOnlyControls(array $enum): void {
    $schema = (object) ['type' => 'object', 'properties' => (object) ['choice' => (object) ['enum' => $enum]]];
    $form = $this->sut->transform($schema);
    $this->assertSame('radios', $form['choice']['#type']);
    $this->assertCount(count($enum), $form['choice']['#options']);
  }

  /**
   * Provides enums without a type, one per inferred type.
   *
   * @return array
   *   The test cases.
   */
  public static function enumOnlyControlProvider(): array {
    return [
      'string' => [['a', 'b']],
      'integer' => [[0, 12]],
      'number' => [[0.5, 1.75]],
      'boolean' => [[FALSE, TRUE]],
      'mixed' => [['a', TRUE, 2.5]],
    ];
  }

  /**
   * An untyped constant renders as text and an empty schema as an item.
   */
  public function testUntypedConstantAndEmptySchema(): void {
    $text = '<script>alert("x")</script>&';
    $schema = (object) [
      'type' => 'object',
      'properties' => (object) ['fixed' => (object) ['const' => $text], 'unknown' => (object) []],
    ];
    $form = $this->sut->transform($schema);
    $this->assertSame($text, $form['fixed']['#plain_text']);
    $this->assertFalse($form['fixed']['#input']);
    $this->assertArrayNotHasKey('#markup', $form['fixed']);
    $this->assertSame('item', $form['unknown']['#type']);
    $this->assertTrue($form['unknown']['#disabled']);
  }

  /**
   * Compound defaults are normalized before filling descendants.
   */
  #[DataProvider('compoundInputProvider')]
  public function testCompoundDefaults(array $input, array $expected): void {
    $schema = json_decode('{"type":"object","properties":{"tags":{"type":"array","default":["first","second"],"items":{"type":"string"}},"profile":{"type":"object","default":{"name":"default","rows":[{"label":"nested"}]},"properties":{"name":{"type":"string"},"rows":{"type":"array","items":{"type":"object","properties":{"label":{"type":"string"}}}}}}}}');
    $before = serialize($schema);
    $form = $this->sut->transform($schema, new Context(['options' => new FormOptions(currentInput: $input)]));
    $tags = [];
    foreach ($form['tags'] as $key => $element) {
      if (is_int($key)) {
        $tags[] = $element['removable_element']['#default_value'] ?? NULL;
      }
    }
    $this->assertSame($expected[0], $tags);
    $this->assertSame($expected[1], $form['profile']['name']['#default_value'] ?? NULL);
    $this->assertSame($expected[2], $form['profile']['rows'][0]['removable_element']['label']['#default_value'] ?? NULL);
    $this->assertSame($before, serialize($schema));
  }

  /**
   * Provides compound defaults and saved values to normalize.
   *
   * @return array
   *   The test cases.
   */
  public static function compoundInputProvider(): array {
    return [
      'defaults' => [[], [['first', 'second'], 'default', 'nested']],
      'saved arrays' => [
        ['tags' => ['saved'], 'profile' => ['name' => 'saved', 'rows' => [['label' => 'saved row']]]],
        [['saved'], 'saved', 'saved row'],
      ],
      'saved objects' => [
        ['tags' => ['saved'], 'profile' => (object) ['name' => 'saved', 'rows' => [(object) ['label' => 'saved row']]]],
        [['saved'], 'saved', 'saved row'],
      ],
      'explicit empty arrays' => [['tags' => [], 'profile' => []], [[], NULL, NULL]],
      'explicit empty object' => [['tags' => [], 'profile' => (object) []], [[], NULL, NULL]],
    ];
  }

  /**
   * Scalar defaults cannot reach compound array-only helpers.
   */
  #[DataProvider('scalarCompoundDefaultProvider')]
  public function testScalarCompoundDefaults(mixed $type, mixed $default): void {
    $property = (object) [
      'type' => $type,
      'default' => $default,
      'items' => (object) ['type' => 'string'],
      'properties' => (object) ['name' => (object) ['type' => 'string']],
    ];
    $schema = (object) ['type' => 'object', 'properties' => (object) ['value' => $property]];
    $form = $this->sut->transform($schema);
    $this->assertSame('details', $form['value']['#type']);
    $this->assertArrayNotHasKey(0, $form['value']);
  }

  /**
   * Provides compound types with scalar defaults.
   *
   * @return array
   *   The test cases.
   */
  public static function scalarCompoundDefaultProvider(): array {
    $cases = [];
    foreach (['array', 'object', ['array', 'string']] as $type) {
      foreach (['scalar', 7, TRUE] as $default) {
        $cases[json_encode([$type, $default])] = [$type, $default];
      }
    }
    return $cases;
  }

  /**
   * Itemless and tuple arrays cannot be edited by homogeneous array widgets.
   */
  #[DataProvider('unsupportedItemsProvider')]
  public function testUnsupportedArrayItems(mixed $items, bool $saved): void {
    $property = (object) ['type' => 'array', 'default' => ['default']];
    if ($items !== NULL) {
      $property->items = $items;
    }
    $schema = (object) ['type' => 'object', 'properties' => (object) ['value' => $property]];
    $context = new Context(['options' => new FormOptions(currentInput: $saved ? ['value' => ['saved']] : [])]);
    $form = $this->sut->transform($schema, $context);
    $this->assertSame('item', $form['value']['#type']);
    $this->assertTrue($form['value']['#disabled']);
    $this->assertSame($saved ? ['saved'] : ['default'], $form['value']['#schema_fixed_value']);
    $this->assertArrayNotHasKey('add_more', $form['value']);
    $this->assertSame(['value' => ['1']], (new SubmissionRefiner())->refine(['value' => ['1']], $schema));
  }

  /**
   * Provides arrays with missing or tuple items, with saved values or defaults.
   *
   * @return array
   *   The test cases.
   */
  public static function unsupportedItemsProvider(): array {
    return [
      'missing default' => [NULL, FALSE],
      'missing saved' => [NULL, TRUE],
      'tuple default' => [[(object) ['type' => 'string']], FALSE],
      'tuple saved' => [[(object) ['type' => 'string']], TRUE],
    ];
  }

  /**
   * Saved NULL wins over both compound and child defaults.
   */
  public function testNullCompoundDefaultPrecedence(): void {
    $schema = json_decode('{"type":"object","properties":{"tags":{"type":["array","null"],"default":["default"],"items":{"type":"string"}},"profile":{"type":["object","null"],"default":{"name":"default"},"properties":{"name":{"type":"string","default":"child"}}}}}');
    $options = new FormOptions(currentInput: ['tags' => NULL, 'profile' => NULL]);
    $form = $this->sut->transform($schema, new Context(['options' => $options]));
    $this->assertArrayNotHasKey(0, $form['tags']);
    $this->assertArrayNotHasKey('#default_value', $form['profile']['name']);
    $this->assertTrue($form['tags']['#schema_saved_null']);
    $this->assertTrue($form['profile']['#schema_saved_null']);
  }

}
