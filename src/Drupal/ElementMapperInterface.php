<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

/**
 * Picks the Form API element that edits a schema with no compound control.
 *
 * Pass mappers to the FormGeneratorDrupal constructor. The generator asks
 * them in order, then its own, and uses the first answer. It never asks for
 * "array" and "object".
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
   *   The type the control is built for, or the "ui:widget" name.
   *
   * @return array<string, mixed>|null
   *   The render properties, including '#type', or NULL to pass.
   */
  public function map(\stdClass $schema, string $type): ?array;

}
