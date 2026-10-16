---
type: practice
title: Cast only true absence to NULL on nullable props
description: >-
  A null union is not a falsy-to-null rule. '' becomes NULL only when no
  declared type can hold it.
tags:
  - type-casting
  - validation
  - gotcha
kk_schema_version: 3
kk_id: practice-empty-string-to-null-only-when-unholdable
kk_derived_from: []
kk_relates_to: []
kk_depends_on: []
kk_confidence: high
---
`RecursiveTypeCaster::tryCastingNull()` turns a value into `NULL` only when it already means "nothing here". `null` in a type union means the property also accepts absence. It does not mean that every falsy value becomes absence. `FALSE` on `["boolean","null"]` and `"0"` on `["string","null"]` are values the user chose, and they must survive.

The empty string is the ambiguous case, because Form API submits it for a control nobody touched. It is decided per type. A plain `string` keeps `''`. Every other type, and a `string` limited by `minLength > 0`, `format`, `pattern`, or an `enum` without `""`, turns it into `NULL` (`acceptsTheEmptyString()`).

When changing the caster, keep `tests/src/RecursiveTypeCasterTest.php` passing. The order number, boolean, null, string matters, because the first cast that succeeds wins.
