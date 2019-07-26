<?php

namespace SchemaForms\Tests\Drupal\FormGeneratorDrupal;

use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\FormGeneratorDrupal;

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
  protected function setUp() {
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
          ],
          'bar' => [
            '#title' => 'Bar',
            // phpcs:ignore
            '#description' => 'It is just a bar',
            '#type' => 'number',
            '#required' => TRUE,
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

}
