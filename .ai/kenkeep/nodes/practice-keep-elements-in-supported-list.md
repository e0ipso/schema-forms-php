---
type: practice
title: Add every generated element type to SUPPORTED_ELEMENT_TYPES
description: >-
  Elements not in the list (and not from ui:widget) become a disabled 'cannot be
  edited' item, by design.
tags:
  - drupal
  - form-api
  - gotcha
kk_schema_version: 3
kk_id: practice-keep-elements-in-supported-list
kk_derived_from: []
kk_relates_to:
  - map-schema-type-element-mapping
  - map-submission-validation-pipeline
kk_depends_on: []
kk_confidence: high
---
`FormGeneratorDrupal::SUPPORTED_ELEMENT_TYPES` lists the element types the generator builds on purpose: `checkbox`, `checkboxes`, `details`, `email`, `number`, `radios` and `textfield`. If an element's `#type` is not in that list and no `ui:widget` set it, `transformUnsupported()` replaces it.

The reason is in the code. A JSON Schema type that reaches `#type` unchanged is not a Drupal element. `ElementInfoManager::getInfo()` returns an empty array for it, so it renders as an empty string with no exception, warning or log entry. The property then looks like one the schema never declared.

The replacement is an `item` with `#input => FALSE`, `#disabled => TRUE` and a message naming the type. `#input` must stay `FALSE` so it does not submit a fabricated blank. Unsupported properties carry `#schema_fixed_value` when a saved value is present, otherwise when a schema default exists. Presence checks preserve saved `NULL`, `FALSE` and zero, with saved input winning over defaults. Constants use the same marker whenever `const` exists, including falsy values (`src/Drupal/FormGeneratorDrupal.php:165`, `:631`).

`FormValidatorDrupal::supplyFixedValues()` inserts these exact JSON values after cleaning and casting. Non-input elements receive no Form API `#required`; schema validation still enforces required keys and constraints. A property with no available fixed value remains missing and must surface its schema error (`src/Drupal/FormGeneratorDrupal.php:526`; `src/Drupal/FormValidatorDrupal.php:154`, `:173`; `tests/src/Kernel/ValidationPlacementTest.php:149`, `:172`).

When you teach the generator a new schema-to-element mapping, add the element type to the list in the same change, or it gets replaced by the unsupported item.
