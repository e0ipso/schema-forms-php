---
type: map
title: How JSON Schema types map to Form API elements
description: >-
  Type-to-element rules in FormGeneratorDrupal: textfield, email, number,
  checkbox, radios, details, plain text, item fallback.
tags:
  - drupal
  - json-schema
  - form-api
kk_schema_version: 3
kk_id: map-schema-type-element-mapping
kk_derived_from:
  - README.md
  - src/Drupal/FormGeneratorDrupal.php
  - src/RecursiveTypeCaster.php
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
- Any other `array` whose `items` is one schema object uses the multivalue widget, an open `details` with add and remove buttons. Arrays without `items`, or with list-form tuple `items`, use the disabled unsupported `item` because no single editable item schema exists.
- An `object` with `properties` is an open `details` holding one element per sub-property.
- Any present `const`, including `NULL`, `FALSE` and zero, drops `#type`, renders as escaped `#plain_text`, and carries the exact value in `#schema_fixed_value` with `#input => FALSE` in `doTransformOneField()`. Strings display verbatim as text; other values display as JSON, including structured constants.

A schema without `type` but with `enum` takes its type from the members in `guessSchemaType()`: all integers give `integer`, all integers or floats give `number`, all booleans give `boolean`, and anything else, including mixed members, gives `string`. The enum then renders as editable radios. A schema with neither `type`, `enum` nor `const`, such as `{}`, resolves to `unknown` and becomes the disabled unsupported item. See `testEnumOnlyControls()` and `testUntypedConstantAndEmptySchema()` in `tests/src/Drupal/SchemaForms/FormGeneratorDrupalTest.php`.

`guessSchemaType()` chooses the first type other than the string `"null"` from a type union. Thus `["null","string"]` renders a textfield. An all-null union still resolves to `null` and uses the unsupported item. An explicit `ui:widget` takes precedence.

Unsupported arrays retain their exact saved value, or their schema default when no saved value exists, in `#schema_fixed_value`. `RecursiveTypeCaster::refineValue()` returns list data unchanged when no object-form item schema exists. Keep this fallback separate from supported enum-checkbox arrays and object-form item schemas. Rendering cases live in `tests/src/Drupal/SchemaForms/FormGeneratorDrupalTest.php`; real Save round trips live in `tests/src/Kernel/SavedValuesSubmissionTest.php`.

When changing this, also run the data providers in `tests/src/Drupal/SchemaForms/FormGeneratorDrupalTest.php`. They compare the full render array, so any key a change adds or drops fails them.

<!-- kk:related:start -->
# Related

- Related: [practice-keep-elements-in-supported-list](practice-keep-elements-in-supported-list.md)
- Related: [practice-enum-options-assign-keys-directly](practice-enum-options-assign-keys-directly.md)
<!-- kk:related:end -->

<!-- kk:citations:start -->
# Citations

[1] [README.md](../../../README.md)
[2] [src/Drupal/FormGeneratorDrupal.php](../../../src/Drupal/FormGeneratorDrupal.php)
[3] [src/RecursiveTypeCaster.php](../../../src/RecursiveTypeCaster.php)
<!-- kk:citations:end -->
