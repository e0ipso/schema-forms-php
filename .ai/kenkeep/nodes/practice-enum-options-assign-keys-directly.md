---
type: practice
title: 'Build enum #options by key assignment, never array_merge'
description: >-
  Assign enum keys directly; checkbox submissions become typed lists in schema
  order, preserving checked zero.
tags:
  - drupal
  - enum
  - gotcha
kk_schema_version: 3
kk_id: practice-enum-options-assign-keys-directly
kk_derived_from:
  - src/Drupal/FormGeneratorDrupal.php
kk_relates_to:
  - map-submission-validation-pipeline
  - practice-preserve-unrepresentable-numbers-and-object-shape
kk_depends_on: []
kk_confidence: high
---
Build radio and checkbox options through `FormGeneratorDrupal::enumOptions()`, which assigns `$options[$key] = $label` one member at a time. Do not merge single-element arrays, and do not type the member as `string`.

Two key-coercion defects explain this rule. `array_merge()` renumbers integer keys, and PHP has already turned `'10'` into the int key `10`, so an enum `["10","12","15"]` offered keys `0, 1, 2`. The browser submits the key, so every choice then failed enum validation. Also, `null` is a legal enum member, and a `string` parameter turned it into a `TypeError` that broke the whole form.

For checkbox enums, `transformCheckboxes()` registers `validateCheckboxes()` as an element validator. It uses Drupal `Checkboxes::getCheckedCheckboxes()` to distinguish checked string `'0'` from unchecked integer `0`, then intersects checked keys with `#options` to return a list in schema enum order. Keep this normalization before recursive item casting, which restores each item's schema type. See `src/Drupal/FormGeneratorDrupal.php:685`, `src/RecursiveTypeCaster.php:28`, and `tests/src/Kernel/ValidationPlacementTest.php:99`.

A `null` member becomes the key `''`, which Form API also submits for an unselected control. A schema that lists both `null` and `""` gets a single option because those members share the same array key.

Convert float members to string keys before direct assignment. PHP truncates a float array key to an integer, so `0.5`, `1.5` and `1.75` must retain keys `"0.5"`, `"1.5"` and `"1.75"` for radios and checkbox enums. Item refinement restores numeric submission types. See `enumOptions()` and the fractional enum cases in `tests/src/Drupal/SchemaForms/FormGeneratorDrupalTest.php` and `tests/src/Kernel/ValidationPlacementTest.php`.

<!-- kk:related:start -->
# Related

- Related: [map-submission-validation-pipeline](map-submission-validation-pipeline.md)
- Related: [practice-preserve-unrepresentable-numbers-and-object-shape](practice-preserve-unrepresentable-numbers-and-object-shape.md)
<!-- kk:related:end -->

<!-- kk:citations:start -->
# Citations

[1] [src/Drupal/FormGeneratorDrupal.php](../../../src/Drupal/FormGeneratorDrupal.php)
<!-- kk:citations:end -->
