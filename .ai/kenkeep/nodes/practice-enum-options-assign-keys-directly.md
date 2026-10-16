---
type: practice
title: 'Build enum #options by key assignment, never array_merge'
description: >-
  array_merge renumbers numeric-string keys and a string-typed closure crashes
  on null members. Use enumOptions().
tags:
  - drupal
  - enum
  - gotcha
kk_schema_version: 3
kk_id: practice-enum-options-assign-keys-directly
kk_derived_from: []
kk_relates_to: []
kk_depends_on: []
kk_confidence: high
---
Build radio and checkbox options through `FormGeneratorDrupal::enumOptions()`, which assigns `$options[$key] = $label` one member at a time. Do not merge single-element arrays, and do not type the member as `string`.

The code comment records both defects. `array_merge()` renumbers integer keys, and PHP has already turned `'10'` into the int key `10`, so an enum `["10","12","15"]` offered keys `0, 1, 2`. The browser submits the key, so every choice then failed enum validation. Also, `null` is a legal enum member, and a `string` parameter turned it into a `TypeError` that broke the whole form.

A `null` member becomes the key `''`. That is the right key, because Form API submits `''` for an unselected control and validation uses `CHECK_MODE_TYPE_CAST`. A schema that lists both `null` and `""` gets a single option.
