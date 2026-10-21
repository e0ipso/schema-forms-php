---
type: practice
title: Cast only true absence to NULL on nullable props
description: >-
  Refine nullable blanks first; omit only optional rejected blanks, using each object's own required list.
tags:
  - type-casting
  - validation
  - gotcha
kk_schema_version: 3
kk_id: practice-empty-string-to-null-only-when-unholdable
kk_derived_from: []
kk_relates_to:
  - map-submission-validation-pipeline
  - practice-preserve-unrepresentable-numbers-and-object-shape
kk_depends_on: []
kk_confidence: high
---
`RecursiveTypeCaster::tryCastingNull()` turns a value into `NULL` only when it already means "nothing here". `null` in a type union means the property also accepts absence. It does not mean that every falsy value becomes absence. `FALSE` on `["boolean","null"]` and `"0"` on `["string","null"]` are values the user chose, and they must survive.

The empty string is the ambiguous case, because Form API submits it for a control nobody touched. It is decided per type. A plain `string` keeps `''`. Every other type, and a `string` limited by `minLength > 0`, `format`, `pattern`, or an `enum` without `""`, turns it into `NULL` (`acceptsTheEmptyString()`).

`recursiveTypeRefinements()` first refines each object property with its own schema. It omits an optional property only when both its original and refined values are `''` and that property's schema rejects the blank. A nullable blank already refined to `NULL` stays present; a valid empty string stays present too. Required properties retain their refined value and any schema error. Each nested object consults its own `required` list, independently of root Form API `#required` flags. See `src/RecursiveTypeCaster.php:31`, `tests/src/Kernel/ReadmeExampleSubmissionTest.php:121`, and `tests/src/Kernel/ValidationPlacementTest.php:122`.

When changing the caster, keep `tests/src/RecursiveTypeCasterTest.php` passing. The order number, boolean, null, string matters, because the first cast that succeeds wins.
