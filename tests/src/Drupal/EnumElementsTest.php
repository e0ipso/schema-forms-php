<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\EnumElements;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\Labels;
use SchemaForms\FormOptions;
use SchemaForms\Tests\Fixture\TranslationContainer;
use Shaper\Util\Context;

/**
 * Every enum option has a label assistive technology can read.
 */
#[CoversClass(EnumElements::class)]
#[CoversClass(Labels::class)]
final class EnumElementsTest extends TestCase {

  use TranslationContainer;

  /**
   * The empty string is labelled "Empty" unless a mapping labels it.
   */
  #[DataProvider('emptyMemberProvider')]
  public function testEmptyStringLabel(array $enum, array $mappings, array $expected): void {
    $hints = ['ui:enum' => ['labels' => ['mappings' => $mappings]]];

    $radios = EnumElements::radios([], (object) ['enum' => $enum], $hints);
    $checkboxes = EnumElements::checkboxes([], $enum, $hints);

    $this->assertSame($expected, array_map('strval', $radios['#options']));
    $this->assertSame($expected, array_map('strval', $checkboxes['#options']));
  }

  /**
   * An enum reads 'ui:widget' the way the type guess does.
   *
   * A blank or structured widget names no element, so the enum keeps its
   * radios instead of becoming the "cannot be edited" item.
   */
  #[DataProvider('widgetProvider')]
  public function testWidgetHint(mixed $widget, string $expected): void {
    $schema = json_decode('{"type":"object","properties":{"plan":{"type":"string","enum":["free","pro"]}}}');
    $context = new Context(['options' => new FormOptions(uiHints: ['plan' => ['ui:widget' => $widget]])]);

    $form = (new FormGeneratorDrupal())->transform($schema, $context);

    $this->assertSame($expected, $form['plan']['#type']);
    $this->assertSame(['free', 'pro'], array_keys($form['plan']['#options']));
  }

  /**
   * Provides 'ui:widget' values and the element type an enum gets.
   *
   * @return array<string, array{mixed, string}>
   *   The test cases.
   */
  public static function widgetProvider(): array {
    return [
      'select' => ['select', 'select'],
      'none' => [NULL, 'radios'],
      'blank' => ['', 'radios'],
      'zero' => ['0', 'radios'],
      'structured' => [['select'], 'radios'],
    ];
  }

  /**
   * Provides enums with an empty string member and their option labels.
   *
   * @return array
   *   The test cases.
   */
  public static function emptyMemberProvider(): array {
    return [
      'unmapped' => [['', 'a b'], [], ['' => 'Empty', 'a b' => 'A B']],
      'mapped' => [['', 'a'], ['' => 'Nothing'], ['' => 'Nothing', 'a' => 'A']],
      // NULL and '' share the key '', and the last member wins.
      'after null' => [[NULL, ''], [], ['' => 'Empty']],
      'before null' => [['', NULL], [], ['' => 'None']],
    ];
  }

}
