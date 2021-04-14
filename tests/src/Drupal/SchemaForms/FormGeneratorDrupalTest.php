<?php

namespace SchemaForms\Tests\Drupal\FormGeneratorDrupal;

use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\FormGeneratorDrupal;
use Shaper\Util\Context;

/**
 * Unit tests for \SchemaForms\Drupal\FormGeneratorDrupal.
 *
 * @package SchemaForms
 *
 * @coversDefaultClass \SchemaForms\Drupal\FormGeneratorDrupal
 */
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
   *
   * @dataProvider dataProviderFormGeneration
   */
  public function testFormGeneration(string $schema, array $expected_form) {
    $data = json_decode($schema);
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
  public function dataProviderFormGeneration() {
    return [
      [
        '{"type":"object","properties":{"foo":{"type":["string","null"]}}}',
        [
          'foo' => [
            '#title' => 'Foo',
            '#type' => 'textfield',
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
          ],
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
          ],
          'bar' => [
            '#title' => 'Bar',
            // phpcs:ignore
            '#description' => 'It is just a bar',
            '#type' => 'number',
            '#required' => TRUE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
          ],
        ],
      ],
      [
        '{"type":"object","properties":{"foo":{"type":"string","const":"The Big Foo"}}}',
        [
          'foo' => [
            '#markup' => 'The Big Foo',
            '#title' => 'Foo',
            '#required' => FALSE,
          ],
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
          ],
        ],
      ],
      [
        '{"type":"object","properties":{"foo":{"type":"array","items":{"type":"string","enum":["lor-em","ipsum"]}}}}',
        [
          'foo' => [
            '#title' => 'Foo',
            '#type' => 'checkboxes',
            '#options' => ['lor-em' => 'Lor Em', 'ipsum' => 'Ipsum'],
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
          ],
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
          ],
        ],
      ],
    ];
  }

  /**
   * Tests invalid types.
   *
   * @dataProvider dataProviderInvalidTypes
   */
  public function testInvalidTypes(string $schema) {
    $this->expectException(\InvalidArgumentException::class);
    $this->sut->transform(json_decode(($schema)));
  }

  /**
   * Data provider for the testInvalidTypes.
   *
   * @return array
   *   The data.
   */
  public function dataProviderInvalidTypes() {
    return [
      ['{"type":"object","properties":{"foo":{"type":"array","items":{"type":"string"}}}}'],
      ['{"type":"object","properties":{"foo":{"type":"object","properties":{}}}}'],
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
   *
   * @dataProvider dataProviderFormGenerationWithUi
   */
  public function testFormGenerationWithUi(string $schema, array $ui_schema, array $expected_form) {
    $data = json_decode($schema);
    $context = new Context($ui_schema);
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
  public function dataProviderFormGenerationWithUi(): array {
    return [
      [
        '{"type":"object","properties":{"foo":{"type":["string","null"]}}}',
        [
          'foo' => [
            'ui:title' => 'A title',
            'ui:help' => 'Some help text',
            'ui:placeholder' => 'This is a placeholder',
          ],
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
          ],
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
        ],
        [
          'foo' => [
            '#title' => 'Foo',
            '#type' => 'hidden',
            '#required' => FALSE,
            '#disabled' => FALSE,
            '#visible' => TRUE,
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
          ],
        ],
      ],
    ];
  }

}
