<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\EnumElements;
use SchemaForms\Drupal\Labels;
use SchemaForms\Tests\Fixture\TranslationContainer;

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
