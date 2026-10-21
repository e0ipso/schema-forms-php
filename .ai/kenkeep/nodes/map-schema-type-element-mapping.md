---
type: map
title: How JSON Schema types map to Form API elements
description: >-
  Type-to-element rules in FormGeneratorDrupal: textfield, email, number,
  checkbox, radios, details, markup, item fallback.
tags:
  - drupal
  - json-schema
  - form-api
kk_schema_version: 3
kk_id: map-schema-type-element-mapping
kk_derived_from:
  - README.md
kk_relates_to:
  - practice-keep-elements-in-supported-list
  - practice-enum-options-assign-keys-directly
kk_depends_on: []
kk_confidence: high
---
`FormGeneratorDrupal::doTransformOneField()` and `scaffoldFormElement()` pick the element:

- `string` is a `textfield`, or an `email` when `format` is `email`.
- `integer` is a `number` with `#step` 1, because Drupal has no integer element.
- `number` is a `number` and `boolean` is a `checkbox`.
- A property with `enum` is `radios`. An `array` whose `items` has an `enum` is `checkboxes`. `ui:widget` can replace either, for example with `select`.
- Any other `array` is the multivalue widget, an open `details` with add and remove buttons.
- An `object` with `properties` is an open `details` holding one element per sub-property.
- Any present `const`, including `NULL`, `FALSE` and zero, drops `#type`, renders as `#markup`, and carries the exact value in `#schema_fixed_value` with `#input => FALSE` (`src/Drupal/FormGeneratorDrupal.php:169`).

A type list such as `["string","null"]` uses its first entry. The filter in `guessSchemaType()` drops PHP `NULL`, not the string `"null"`, so `["null","string"]` resolves to `null` and renders the "cannot be edited" item.

When changing this, also run the data providers in `tests/src/Drupal/SchemaForms/FormGeneratorDrupalTest.php`. They compare the full render array, so any key a change adds or drops fails them.

<!-- kk:citations:start -->
# Citations

[1] [README.md](../../../README.md)
<!-- kk:citations:end -->
