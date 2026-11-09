<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal;

use Drupal\Component\Render\MarkupInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\MultivalueWidget;
use SchemaForms\Drupal\UserInputCleaner;

/**
 * Unit tests for the \SchemaForms\Drupal\UserInputCleaner class.
 *
 * @package SchemaForms
 */
#[CoversClass(UserInputCleaner::class)]
#[CoversClass(MultivalueWidget::class)]
final class UserInputCleanerTest extends TestCase {

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
      'a NULL under a named key is removed' => [
        ['a_string' => 'hello', 'a_missing_value' => NULL],
        ['a_string' => 'hello'],
      ],
      // Numeric keys alone do not make a list; the widget's buttons do.
      'integer-like property names keep their object' => [
        ['200' => 'a', '404' => 'b'],
        [200 => 'a', 404 => 'b'],
      ],
      'a NULL inside an item is removed' => [
        ['a_list' => [self::removable(['choice' => NULL, 'label' => 'x'])]],
        ['a_list' => [['label' => 'x']]],
      ],
      'a plain list keeps its empty and NULL items' => [
        ['a_list' => [[], NULL, 'x']],
        ['a_list' => [[], NULL, 'x']],
      ],
    ];
  }

  /**
   * Tests the cleaning.
   */
  #[DataProvider('dataProviderCleanUserInput')]
  public function testCleanUserInput(mixed $data, mixed $expected): void {
    $this->assertSame($expected, UserInputCleaner::cleanUserInput($data));
  }

  /**
   * Builds a multivalue widget of list items, the way the generator does.
   *
   * @param array<int, array> $items
   *   The item controls, keyed by form delta.
   *
   * @return array
   *   The widget.
   */
  private static function listWidget(array $items): array {
    $containers = array_map(
      static fn (array $item): array => [
        '#type' => 'container',
        'removable_element' => $item,
        'remove_one' => ['#type' => 'submit'],
      ],
      $items
    );
    return ['#type' => 'details', '#max_delta' => array_key_last($items)]
      + $containers
      + ['add_more' => ['#type' => 'submit']];
  }

  /**
   * Builds a list item control that holds no items of its own.
   *
   * @param array $properties
   *   Extra render properties.
   *
   * @return array
   *   The item control.
   */
  private static function emptyListItem(array $properties = []): array {
    return ['#type' => 'details', '#max_delta' => NULL] + $properties;
  }

  /**
   * Data provider for testCleanWidgetValue.
   *
   * @return array
   *   The data.
   */
  public static function dataProviderCleanWidgetValue(): array {
    $submitted = [
      0 => self::removable([]),
      2 => self::removable(['kept']),
      'add_more' => self::buttonMarkup('Append an item'),
    ];
    return [
      'an appended item that was never filled is dropped' => [
        [0 => self::emptyListItem(), 2 => self::emptyListItem(['#schema_showed_value' => TRUE])],
        $submitted,
        [['kept']],
      ],
      'a saved empty item is kept in place' => [
        [
          0 => self::emptyListItem(['#schema_showed_value' => TRUE]),
          2 => self::emptyListItem(['#schema_showed_value' => TRUE]),
        ],
        $submitted,
        [[], ['kept']],
      ],
      'a saved NULL item is kept in place' => [
        [
          0 => self::emptyListItem(['#schema_saved_null' => TRUE]),
          2 => self::emptyListItem(['#schema_showed_value' => TRUE]),
        ],
        $submitted,
        [[], ['kept']],
      ],
    ];
  }

  /**
   * Tests that the widget decides which items the stored list keeps.
   */
  #[DataProvider('dataProviderCleanWidgetValue')]
  public function testCleanWidgetValue(array $items, array $data, array $expected): void {
    $this->assertSame($expected, UserInputCleaner::cleanUserInput($data, self::listWidget($items)));
  }

  /**
   * Tests that a nested widget's value, already cleaned, is left alone.
   *
   * The NULL of a control left without a choice is dropped, while a NULL
   * inside a single control's list value keeps its position.
   */
  public function testNestedWidgetValueIsNotCleanedAgain(): void {
    $element = [
      'grid' => self::listWidget([0 => self::emptyListItem(['#schema_showed_value' => TRUE])]),
      'choice' => ['#type' => 'radios'],
      'picked' => ['#type' => 'select'],
    ];
    $data = ['grid' => [[], ['a']], 'choice' => NULL, 'picked' => [NULL, 'x']];
    $this->assertSame(
      ['grid' => [[], ['a']], 'picked' => [NULL, 'x']],
      UserInputCleaner::cleanUserInput($data, $element)
    );
  }

}
