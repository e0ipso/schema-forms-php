<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

/**
 * Picks the Form API element that edits a schema with no compound control.
 *
 * Pass mappers to the FormGeneratorDrupal constructor to map a type or a
 * format to an element the generator does not pick on its own, such as
 * "format": "date" to a date element. The generator asks the mappers in the
 * order given, then its own, and uses the first answer. It never asks for
 * "array" and "object", whose controls it builds itself. An enum still
 * turns the element into radios, and the element type a "ui:widget" names
 * still wins.
 *
 * @api
 */
interface ElementMapperInterface {

  /**
   * Gives the element properties for a schema.
   *
   * @param \stdClass $schema
   *   The property schema.
   * @param string $type
   *   The type the control is built for: the schema's "type", the first type
   *   other than "null" of a type list, the type all enum members share, or
   *   the "ui:widget" name when the UI hints set one.
   *
   * @return array<string, mixed>|null
   *   The render properties of the element, including its '#type', such as
   *   ['#type' => 'date']. NULL when this mapper does not handle the schema.
   */
  public function map(\stdClass $schema, string $type): ?array;

}
