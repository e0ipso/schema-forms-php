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
kk_derived_from: []
kk_relates_to: []
kk_depends_on: []
kk_confidence: medium
---
`transformMultivalue()` renders an `array` without an item `enum` as an open `details` element. Each item index `$delta` gets a `container` holding `removable_element` (the item control) and `remove_one` (an AJAX `⨯` submit button). An `add_more` submit button labelled "Append an item" follows the items. Cardinality is always unlimited, and a `@todo` marks the place to change that.

The item list lives in form state storage at `['prop_storage', '#parents', ...$prop_parents, '#props', $prop_name]`, as `items_indices` plus `array_parents`. `addMoreSubmit()` appends an index and `removeOneSubmit()` unsets one. Both call `setRebuild()`. On first build the list starts with as many items as `current_input` has. The state is only kept when the caller passes the real `form_state` in the `Context`.

`multiValueAfterBuild()` cleans the submitted value with `UserInputCleaner` while input is processed and records the element's `#array_parents`.

When changing this, verify `UserInputCleaner` and `FormValidatorDrupal`. They match the literal keys `removable_element`, `remove_one` and `add_more`, and `cleanRemoveButton()` only unwraps items whose `remove_one` value is a `MarkupInterface`.
