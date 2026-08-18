<?php

namespace SchemaForms\Tests\Drupal;

use Drupal\Component\Render\MarkupInterface;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\UserInputCleaner;

/**
 * Unit tests for the \SchemaForms\Drupal\UserInputCleaner class.
 *
 * @package SchemaForms
 *
 * @coversDefaultClass \SchemaForms\Drupal\UserInputCleaner
 */
class UserInputCleanerTest extends TestCase {

  /**
   * Builds a stand-in for the markup a submit button contributes.
   *
   * @param string $text
   *   The button label.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The markup.
   */
  private static function buttonMarkup(string $text): MarkupInterface {
    return new class($text) implements MarkupInterface {

      /**
       * The button label.
       *
       * @var string
       */
      private string $text;

      /**
       * Constructs the markup.
       *
       * @param string $text
       *   The button label.
       */
      public function __construct(string $text) {
        $this->text = $text;
      }

      /**
       * {@inheritdoc}
       */
      public function __toString() {
        return $this->text;
      }

      /**
       * {@inheritdoc}
       */
      public function jsonSerialize(): string {
        return $this->text;
      }

    };
  }

  /**
   * Wraps a value the way the remove button's form nesting does.
   *
   * @param mixed $value
   *   The item's own submitted value.
   *
   * @return array
   *   The wrapped item.
   */
  private static function removable($value): array {
    return [
      'removable_element' => $value,
      'remove_one' => self::buttonMarkup('x'),
    ];
  }

  /**
   * Data provider for testCleanUserInput.
   *
   * @return array
   *   The data.
   */
  public static function dataProviderCleanUserInput(): array {
    return [
      'a scalar is returned as it came' => ['lorem', 'lorem'],
      'the empty string is a value' => ['', ''],
      'an untouched submission is unchanged' => [
        ['a_string' => 'hello'],
        ['a_string' => 'hello'],
      ],
      // The property's whole value is an empty array. This is the value an
      // `array` property has when its item list is empty, and it is a value
      // the caller chose, not scaffolding.
      'an empty array property is kept' => [
        ['a_string' => 'hello', 'a_string_array' => []],
        ['a_string' => 'hello', 'a_string_array' => []],
      ],
      'an empty array property nested in an object is kept' => [
        ['level_one' => ['label' => 'hi', 'a_list' => []]],
        ['level_one' => ['label' => 'hi', 'a_list' => []]],
      ],
      'a multivalue with no items at all is an empty array' => [
        ['a_string_array' => ['add_more' => self::buttonMarkup('Append an item')]],
        ['a_string_array' => []],
      ],
      // The scaffolding this class exists to remove.
      'the add_more button is removed' => [
        [
          'a_string_array' => [
            0 => self::removable('kept'),
            'add_more' => self::buttonMarkup('Append an item'),
          ],
        ],
        ['a_string_array' => ['kept']],
      ],
      'the remove button nesting is undone' => [
        ['a_string_array' => [self::removable('one'), self::removable('two')]],
        ['a_string_array' => ['one', 'two']],
      ],
      'deleted indices are re-keyed into a list' => [
        ['a_string_array' => [0 => self::removable('one'), 2 => self::removable('three')]],
        ['a_string_array' => ['one', 'three']],
      ],
      'an item that submitted nothing at all is dropped' => [
        ['a_list_of_lists' => [self::removable([]), self::removable(['kept'])]],
        ['a_list_of_lists' => [['kept']]],
      ],
      // Dropping an item must leave a list behind, not a gapped array: a
      // gapped array is an object once it reaches the JSON Schema validator,
      // and the property declares `array`.
      'dropping an item re-indexes the list' => [
        ['a_list_of_lists' => [self::removable(['kept']), self::removable([])]],
        ['a_list_of_lists' => [['kept']]],
      ],
      'an emptied text item is a value and is kept' => [
        ['a_string_array' => [self::removable('kept'), self::removable('')]],
        ['a_string_array' => ['kept', '']],
      ],
    ];
  }

  /**
   * Tests the cleaning.
   *
   * @dataProvider dataProviderCleanUserInput
   *
   * @covers ::cleanUserInput
   */
  public function testCleanUserInput($data, $expected): void {
    $this->assertSame($expected, UserInputCleaner::cleanUserInput($data));
  }

}
