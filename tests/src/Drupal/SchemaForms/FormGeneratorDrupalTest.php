<?php

namespace SchemaForms\Tests\Drupal\SchemaForms;

use Drupal\Component\Utility\Html;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\FormGeneratorDrupal;
use Shaper\Util\Context;

/**
 * Unit tests for \SchemaForms\Drupal\FormGeneratorDrupal.
 *
 * @package SchemaForms
 */
#[CoversClass(FormGeneratorDrupal::class)]
class FormGeneratorDrupalTest extends TestCase {

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
    $expected_form['#element_validate'] = [[$this->sut, 'validateWithSchema']];
    $expected_form['#tree'] = TRUE;
    $expected_form['#validate'] = [[$this->sut, 'validateFormWithSchema']];
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
            '#title' => 'Foo',
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
            '#title' => 'Foo',
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
            '#title' => 'The Big Foo',
            '#type' => 'email',
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
            '#prop_name' => 'foo',
            '#prop_parents' => ['foo'],
          ],
          'bar' => [
            '#title' => 'Bar',
            // phpcs:ignore
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
            '#title' => 'Foo',
            // phpcs:ignore
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
            '#title' => 'Foo',
            '#type' => 'item',
            '#input' => FALSE,
            '#markup' => new TranslatableMarkup(
              'This property cannot be edited: there is no form element for the %type type.',
              ['%type' => 'null']
            ),
            '#required' => FALSE,
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
            '#markup' => 'The Big Foo',
            '#title' => 'Foo',
            '#required' => FALSE,
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
            '#title' => 'A Foo',
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
            '#title' => 'Foo',
            '#type' => 'checkboxes',
            '#element_validate' => [[FormGeneratorDrupal::class, 'validateCheckboxes']],
            '#options' => ['lor-em' => 'Lor Em', 'ipsum' => 'Ipsum'],
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
            '#title' => 'Foo',
            '#type' => 'radios',
            '#options' => ['lor-em' => 'Lor Em', 'ipsum' => 'Ipsum'],
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
            '#title' => 'Foo',
            '#type' => 'radios',
            '#options' => [10 => '10', 12 => '12', 15 => '15', 20 => '20'],
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
            '#title' => 'Foo',
            '#type' => 'radios',
            '#options' => ['' => '', 'lor-em' => 'Lor Em', 'ipsum' => 'Ipsum'],
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
            '#title' => 'Foo',
            '#type' => 'checkboxes',
            '#element_validate' => [[FormGeneratorDrupal::class, 'validateCheckboxes']],
            '#options' => ['' => '', 'lor-em' => 'Lor Em'],
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
    $expected_form['#element_validate'] = [[$this->sut, 'validateWithSchema']];
    $expected_form['#tree'] = TRUE;
    $expected_form['#validate'] = [[$this->sut, 'validateFormWithSchema']];
    $data = json_decode($schema);
    $expected_form['#json_schema'] = $data;
    $context = new Context([
      'ui_hints' => $ui_schema,
      'current_input' => $current_input,
    ]);
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
      '#title' => 'Labels',
      '#type' => 'details',
      '#prop_name' => 'tag_labels',
      '#default_value' => ['first', 'second'],
      '#description' => 'Add useful labels',
      '#placeholder' => 'A label',
      '#prop_parents' => ['tag_labels'],
      '#open' => TRUE,
      '#after_build' => [[FormGeneratorDrupal::class, 'multiValueAfterBuild']],
      '#cardinality' => -1,
      // SCHE-0031 will change the count to the last item's index.
      '#max_delta' => 2,
      '#prefix' => '<div id="tag-labels-add-more-wrapper">',
      '#suffix' => '</div>',
      '#disabled' => FALSE,
      '#visible' => TRUE,
      '#required' => FALSE,
    ];
    foreach (['first', 'second'] as $delta => $value) {
      $multivalue[$delta] = [
        '#type' => 'container',
        // SCHE-0033 will sanitize these item IDs like the outer wrapper ID.
        '#prefix' => '<div id="tag_labels-' . $delta . '-container">',
        '#wrapper_id' => 'tag_labels-' . $delta . '-container',
        '#suffix' => '</div>',
        '#attributes' => ['style' => 'position: relative;'],
        'removable_element' => [
          '#title' => new TranslatableMarkup('@title (value @number)', [
            '@title' => 'Labels',
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
            'style' => 'opacity: 0.9;display: block;position: absolute;top: -10px;left: -10px;margin: 0;overflow: hidden;width: 20px;height: 20px;padding: 0;border-radius: 15px;',
            'title' => new TranslatableMarkup('Remove item'),
          ],
          '#limit_validation_errors' => [],
          '#submit' => [[FormGeneratorDrupal::class, 'removeOneSubmit']],
          '#ajax' => [
            'callback' => [FormGeneratorDrupal::class, 'removeOneAjax'],
            'wrapper' => 'tag_labels-' . $delta . '-container',
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
      '#attributes' => ['class' => ['field-add-more-submit']],
      // SCHE-0032 will remove the duplicated property from this path.
      '#limit_validation_errors' => [['tag_labels', 'tag_labels']],
      '#submit' => [[FormGeneratorDrupal::class, 'addMoreSubmit']],
      '#ajax' => [
        'callback' => [FormGeneratorDrupal::class, 'addMoreAjax'],
        'wrapper' => 'tag-labels-add-more-wrapper',
        'effect' => 'fade',
      ],
    ];

    return [
      'nested hints and wrapper-only object default' => [
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
            '#title' => 'Postal address',
            '#type' => 'details',
            // SCHE-0039 will pass this default to the street control too.
            '#default_value' => (object) ['street' => 'schema street'],
            '#prop_name' => 'address',
            '#prop_parents' => ['address'],
            '#open' => TRUE,
            // A nested required list currently adds no #required key.
            'street' => [
              '#title' => 'Street address',
              '#type' => 'textfield',
              '#prop_name' => 'street',
              '#description' => 'Include the house number',
              '#placeholder' => '12 Main Street',
              '#prop_parents' => ['address', 'street'],
              '#disabled' => FALSE,
              '#visible' => TRUE,
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
            '#title' => 'Disabled',
            '#type' => 'textfield',
            '#required' => FALSE,
            '#disabled' => TRUE,
            '#visible' => TRUE,
            '#prop_name' => 'disabled',
            '#prop_parents' => ['disabled'],
          ],
          'invisible' => [
            '#title' => 'Invisible',
            '#type' => 'textfield',
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => FALSE,
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
            '#title' => 'Foo',
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
            '#title' => 'A title',
            '#type' => 'textfield',
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
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
            '#title' => 'Foo',
            '#type' => 'hidden',
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
            '#prop_name' => 'foo',
            '#prop_parents' => ['foo'],
          ],
          'bar' => [
            '#title' => 'Bar',
            '#type' => 'select',
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
            '#options' => [
              'uuid1' => 'My Super Option #1',
              'uuid2' => 'Uuid2',
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
        '#element_validate' => [[$this->sut, 'validateWithSchema']],
        '#validate' => [[$this->sut, 'validateFormWithSchema']],
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
    $context = new Context([
      'ui_hints' => $ui_schema,
      'current_input' => ['list' => [['name' => 'a'], ['name' => 'b']]],
    ]);
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

}
