<?php

declare(strict_types=1);

namespace SchemaForms\Tests\Drupal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SchemaForms\Drupal\ElementMapperInterface;
use SchemaForms\Drupal\ElementScaffold;
use SchemaForms\Drupal\FieldBuilder;
use SchemaForms\Drupal\FormGeneratorDrupal;
use SchemaForms\Drupal\ScalarElementMapper;
use SchemaForms\Exception\InvalidOptionException;
use SchemaForms\Tests\Fixture\TranslationContainer;

/**
 * Element mappers pick the elements of scalar schemas.
 *
 * Each entry of the default map renders its element, and a mapper a caller
 * passes adds an element without replacing the defaults.
 */
#[CoversClass(ElementScaffold::class)]
#[CoversClass(FieldBuilder::class)]
#[CoversClass(FormGeneratorDrupal::class)]
#[CoversClass(ScalarElementMapper::class)]
final class ElementMappingTest extends TestCase {

  use TranslationContainer;

  /**
   * Each default map entry renders its element, not the unsupported item.
   */
  #[DataProvider('mappingProvider')]
  public function testEntryRendersItsElement(string $key, string $element_type): void {
    $form = (new FormGeneratorDrupal())->transform(self::schema($key));

    $this->assertSame($element_type, $form['value']['#type']);
  }

  /**
   * Provides every entry of the default element map.
   *
   * @return array<string, array{string, string}>
   *   The map key and the element type it maps to.
   */
  public static function mappingProvider(): array {
    return array_combine(
      array_keys(ScalarElementMapper::ELEMENTS),
      array_map(
        static fn (string $key, array $element): array => [$key, $element['#type']],
        array_keys(ScalarElementMapper::ELEMENTS),
        ScalarElementMapper::ELEMENTS
      )
    );
  }

  /**
   * A caller's mapper turns "format": "date" into a date element.
   *
   * Other strings keep the default textfield, and a type no mapper handles
   * still becomes the "cannot be edited" item.
   */
  public function testCallerMapperAddsAnElement(): void {
    $generator = new FormGeneratorDrupal(element_mappers: [self::dateMapper()]);
    $schema = json_decode('{"type":"object","properties":{"day":{"type":"string","format":"date"},"name":{"type":"string"},"nothing":{"type":"null"}}}');

    $form = $generator->transform($schema);

    $this->assertSame('date', $form['day']['#type']);
    $this->assertSame('textfield', $form['name']['#type']);
    $this->assertSame('item', $form['nothing']['#type']);
    $this->assertEquals((new FormGeneratorDrupal())->transform($schema)['name'], $form['name']);
  }

  /**
   * An enum still becomes radios, whatever a mapper picks.
   */
  public function testEnumWinsOverMapper(): void {
    $generator = new FormGeneratorDrupal(element_mappers: [self::dateMapper()]);
    $schema = json_decode('{"type":"object","properties":{"day":{"type":"string","format":"date","enum":["2024-01-01"]}}}');

    $this->assertSame('radios', $generator->transform($schema)['day']['#type']);
  }

  /**
   * Anything but an element mapper is refused.
   */
  public function testInvalidMapper(): void {
    $this->expectException(InvalidOptionException::class);
    new FormGeneratorDrupal(element_mappers: [new \stdClass()]);
  }

  /**
   * Builds a schema with one property of a map key's type and format.
   *
   * @param string $key
   *   The map key, a type or type:format.
   *
   * @return \stdClass
   *   The decoded schema.
   */
  private static function schema(string $key): \stdClass {
    [$type, $format] = array_pad(explode(':', $key, 2), 2, NULL);
    $property = $format === NULL ? ['type' => $type] : ['type' => $type, 'format' => $format];
    $json = json_encode(['type' => 'object', 'properties' => ['value' => $property]], JSON_THROW_ON_ERROR);
    return json_decode($json, flags: JSON_THROW_ON_ERROR);
  }

  /**
   * Builds the mapper the README shows.
   *
   * @return \SchemaForms\Drupal\ElementMapperInterface
   *   A mapper of "format": "date" strings to date elements.
   */
  private static function dateMapper(): ElementMapperInterface {
    return new class() implements ElementMapperInterface {

      /**
       * {@inheritdoc}
       */
      public function map(\stdClass $schema, string $type): ?array {
        return $type === 'string' && ($schema->format ?? NULL) === 'date' ? ['#type' => 'date'] : NULL;
      }

    };
  }

}
