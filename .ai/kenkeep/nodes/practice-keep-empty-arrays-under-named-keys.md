---
type: practice
title: 'Filter empty items only from lists, never from named keys'
description: >-
  UserInputCleaner drops [] items in multivalue lists but keeps an array
  property's legitimate empty value.
tags:
  - user-input
  - arrays
  - gotcha
kk_schema_version: 3
kk_id: practice-keep-empty-arrays-under-named-keys
kk_derived_from: []
kk_relates_to: []
kk_depends_on: []
kk_confidence: high
---
`UserInputCleaner::filterEmptyItems()` removes `[]` entries only from lists. A `[]` in a multivalue item list is what an appended item that was never filled leaves behind, and it must not be stored as an item.

A `[]` under a named key is different. It is the whole value of an `array`-typed property with no items, which the user chose and the schema accepts. Filtering both made such a property vanish from the submission with no way to tell the cases apart.

After filtering, call `array_values()`. A list with gaps is an object by the time it reaches the JSON Schema validator, and the property declares `array`.
