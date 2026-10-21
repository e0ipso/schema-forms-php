---
type: map
title: Multivalue array widget and its form-state storage
description: >-
  Non-enum arrays render as details with per-item remove buttons and an AJAX
  'Append an item' button.
tags:
  - drupal
  - form-api
  - arrays
kk_schema_version: 3
kk_id: map-multivalue-array-widget
kk_derived_from:
  - src/Drupal/FormGeneratorDrupal.php
kk_relates_to:
  - map-submission-validation-pipeline
  - map-form-generator-drupal
kk_depends_on: []
kk_confidence: medium
---
`transformMultivalue()` renders an `array` without an item `enum` as an open `details` element. Each item index `$delta` gets a `container` holding `removable_element` (the item control) and `remove_one` (an AJAX `⨯` submit button). An `add_more` submit button labelled "Append an item" follows the items. Cardinality is always unlimited, and a `@todo` marks the place to change that.

The item list lives in form state storage at `['prop_storage', '#parents', ...$prop_parents, '#props', $prop_name]`, as `items_indices` plus `array_parents`. `addMoreSubmit()` appends an index and `removeOneSubmit()` unsets one. Both call `setRebuild()`. On first build the list starts with as many items as `current_input` has. The state is only kept when the caller passes the real `form_state` in the `Context`.

`multiValueAfterBuild()` cleans the submitted value with `UserInputCleaner` while input is processed and records the element's `#array_parents`.

When changing this, verify `UserInputCleaner` and `FormValidatorDrupal`. They match the literal keys `removable_element`, `remove_one` and `add_more`, and `cleanRemoveButton()` only unwraps items whose `remove_one` value is a `MarkupInterface`.

`#max_delta` is the last actual item delta, or `NULL` for an empty list. `addMoreAjax()` decorates that existing item only; the empty guard prevents a phantom item. Removing items leaves stored delta gaps, so item count is not the last delta.

The details `#process` list preserves `Details::processGroup()` and `Details::processAjaxForm()` before `multiValueProcess()`. That callback sets the Append button's `#limit_validation_errors` from the widget's final `#parents`, including any parent form key. Each item obtains one sanitized unique ID from `Html::getUniqueId()`, shared by its HTML wrapper, `#wrapper_id` metadata and remove button AJAX target. Keep these values identical; recomputing a unique ID produces a different target.

<!-- kk:related:start -->
# Related

- Related: [map-submission-validation-pipeline](map-submission-validation-pipeline.md)
- Related: [map-form-generator-drupal](map-form-generator-drupal.md)
<!-- kk:related:end -->

<!-- kk:citations:start -->
# Citations

[1] [src/Drupal/FormGeneratorDrupal.php](../../../src/Drupal/FormGeneratorDrupal.php)
<!-- kk:citations:end -->
