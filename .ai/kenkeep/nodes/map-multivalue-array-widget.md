---
type: map
title: Multivalue array widget and its form-state storage
description: >-
  Arrays with one non-enum item schema use stateful AJAX widgets; positive
  integer maxItems limits Append by actual item count.
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
kk_confidence: high
---
`transformMultivalue()` renders an `array` with one object-form item schema and no item `enum` as an open `details` element. Missing and tuple/list-form `items` use the unsupported fallback instead. Each item index `$delta` gets a `container` holding `removable_element` (the item control) and `remove_one` (an AJAX `⨯` submit button). An `add_more` submit button labelled "Append an item" follows the items. `cardinality()` accepts only integer `maxItems >= 1` as finite; missing, zero, negative and non-integer values select `FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED`. Invalid schemas may still be rejected before rendering. `isFull()` counts actual stored indices, since removed deltas leave gaps. Both Append visibility and `addMoreSubmit()` use this guard, so even a forced Append cannot exceed the limit. Saved items already above the limit remain editable and are rejected by normal schema validation on Save, rather than truncated.

The item list lives in form state storage at `['prop_storage', '#parents', ...$prop_parents, '#props', $prop_name]`, as `items_indices` plus `array_parents`. `addMoreSubmit()` appends an index and `removeOneSubmit()` unsets one. Both call `setRebuild()`. On first build the list starts with as many items as `current_input` has. The state is only kept when the caller passes the real `form_state` in the `Context`.

`multiValueAfterBuild()` marks untouched saved NULL descendants before `UserInputCleaner` compacts item deltas, then records the element's `#array_parents`. The submission validator stops its own marking walk at multivalue widgets so it does not apply old delta paths to cleaned lists.

When changing this, verify `UserInputCleaner` and `FormValidatorDrupal`. They match the literal keys `removable_element`, `remove_one` and `add_more`, and `cleanRemoveButton()` only unwraps items whose `remove_one` value is a `MarkupInterface`.

`addMoreAjax()` returns the rebuilt widget for both finite and unlimited cardinality, allowing the Append button to disappear at the limit. `#max_delta` is the last actual item delta, or `NULL` for an empty list. `addMoreAjax()` decorates that existing item only; the empty guard prevents a phantom item. Removing items leaves stored delta gaps, so item count is not the last delta.

The details `#process` list preserves `Details::processGroup()` and `Details::processAjaxForm()` before `multiValueProcess()`. That callback sets the Append button's `#limit_validation_errors` from the widget's final `#parents`, including any parent form key. Each item obtains one sanitized unique ID from `Html::getUniqueId()`, shared by its HTML wrapper, `#wrapper_id` metadata and remove button AJAX target. Keep these values identical; recomputing a unique ID produces a different target.

`testCardinalitySelection()` and `testFiniteRemovalReappendAndForcedAppend()` in `tests/src/Drupal/SchemaForms/FormGeneratorDrupalCallbacksTest.php` cover finite selection, delta gaps and the forced-submit guard. `tests/src/Kernel/SavedValuesSubmissionTest.php` exercises real FormBuilder rebuilds, final paths, Save validation and retention of oversized saved lists.

<!-- kk:related:start -->
# Related

- Related: [map-submission-validation-pipeline](map-submission-validation-pipeline.md)
- Related: [map-form-generator-drupal](map-form-generator-drupal.md)
<!-- kk:related:end -->

<!-- kk:citations:start -->
# Citations

[1] [src/Drupal/FormGeneratorDrupal.php](../../../src/Drupal/FormGeneratorDrupal.php)
<!-- kk:citations:end -->
