<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\ElementScaffold;
use SchemaForms\Drupal\EnumElements;
use SchemaForms\Drupal\FieldBuilder;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Tests\Fixture\TranslationContainer;

/**
 * The scalar element map and the supported element list agree.
 *
 * A mapping is added as one entry in ElementScaffold::SCALAR_ELEMENTS. These
 * tests fail when an entry would be replaced by the "cannot be edited" item,
 * or when the supported list allows an element no builder produces.
 */
#[CoversClass(ElementScaffold::class)]
#[CoversClass(FieldBuilder::class)]
final class ElementMappingTest extends TestCase {

  use TranslationContainer;

  /**
   * Each map entry renders its element, not the unsupported item.
   */
  #[DataProvider('mappingProvider')]
  public function testEntryRendersItsElement(string $key, string $element_type): void {
    [$type, $format] = array_pad(explode(':', $key, 2), 2, NULL);
    $property = $format === NULL ? ['type' => $type] : ['type' => $type, 'format' => $format];
    $schema = json_decode(json_encode(['type' => 'object', 'properties' => ['value' => $property]], JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);

    $form = (new FormGeneratorDrupal())->transform($schema);

    $this->assertSame($element_type, $form['value']['#type']);
    $this->assertContains($element_type, FieldBuilder::supportedElementTypes());
  }

  /**
   * Provides every entry of the scalar element map.
   *
   * @return array<string, array{string, string}>
   *   The map key and the element type it maps to.
   */
  public static function mappingProvider(): array {
    return array_combine(
      array_keys(ElementScaffold::SCALAR_ELEMENTS),
      array_map(
        static fn (string $key, array $element): array => [$key, $element['#type']],
        array_keys(ElementScaffold::SCALAR_ELEMENTS),
        ElementScaffold::SCALAR_ELEMENTS
      )
    );
  }

  /**
   * The supported list holds the mapped elements and the compound builders'.
   *
   * Radios and checkboxes come from enums, and details from objects and
   * multivalue widgets. Anything else in the list would let an element
   * through that no builder makes on purpose.
   */
  public function testSupportedListHoldsOnlyBuiltElements(): void {
    $mapped = array_column(ElementScaffold::SCALAR_ELEMENTS, '#type');
    $compound = [EnumElements::RADIOS, EnumElements::CHECKBOXES, 'details'];

    $supported = FieldBuilder::supportedElementTypes();

    $this->assertEqualsCanonicalizing(array_unique([...$mapped, ...$compound]), $supported);
    $this->assertSame(array_unique($supported), $supported);
  }

}
