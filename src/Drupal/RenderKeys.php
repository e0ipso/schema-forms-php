<?php

declare(strict_types=1);

namespace SchemaForms\Drupal;

/**
 * The keys the generator adds to the render arrays it builds.
 *
 * Generation, the widget callbacks and submission validation all read these
 * keys, so they are spelled once here. Host code may read them too, so their
 * values never change.
 *
 * @internal
 */
final class RenderKeys {

  /**
   * The registered schema, on the generated root container.
   */
  public const SCHEMA = '#json_schema';

  /**
   * The property name of an element, or the delta of an item.
   */
  public const PROP_NAME = '#prop_name';

  /**
   * The schema path of an element, from the generated root.
   */
  public const PROP_PARENTS = '#prop_parents';

  /**
   * Marks an editable control that holds a saved NULL.
   */
  public const SAVED_NULL = '#schema_saved_null';

  /**
   * Marks an empty multivalue widget that showed a saved value or a default.
   *
   * Such a widget stores its empty list. Without the mark, an empty list item
   * is one that was appended and never filled, and it is not stored.
   */
  public const SHOWED_VALUE = '#schema_showed_value';

  /**
   * The exact JSON value of a control that collects no input.
   */
  public const FIXED_VALUE = '#schema_fixed_value';

  /**
   * The namespace of the generated container, on its multivalue widgets.
   */
  public const NAMESPACE = '#schema_namespace';

  /**
   * The last item delta of a multivalue widget, which also marks the widget.
   */
  public const MAX_DELTA = '#max_delta';

  /**
   * The item limit of a multivalue widget.
   */
  public const CARDINALITY = '#cardinality';

  /**
   * The item control inside a multivalue item container.
   */
  public const ITEM = 'removable_element';

  /**
   * The Remove button inside a multivalue item container.
   */
  public const REMOVE_BUTTON = 'remove_one';

  /**
   * The Append button of a multivalue widget.
   */
  public const APPEND_BUTTON = 'add_more';

}
