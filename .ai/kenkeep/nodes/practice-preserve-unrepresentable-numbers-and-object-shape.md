---
type: practice
title: Preserve unrepresentable numbers and object shape
description: >-
  Leave unsafe numeric strings for validation and refine stdClass properties
  without converting objects into arrays.
tags:
  - type-casting
  - validation
  - gotcha
kk_schema_version: 3
kk_id: practice-preserve-unrepresentable-numbers-and-object-shape
kk_derived_from: []
kk_relates_to:
  - practice-empty-string-to-null-only-when-unholdable
  - map-submission-validation-pipeline
kk_depends_on: []
kk_confidence: high
---
`RecursiveTypeCaster::tryCastingNumber()` leaves non-finite numeric strings and strings without a dot outside the PHP integer range unchanged for schema validation. It checks `is_finite()` and `PHP_INT_MIN`/`PHP_INT_MAX`, then uses `FILTER_VALIDATE_INT` at rounded integer boundaries to catch overflow hidden by float rounding (`src/RecursiveTypeCaster.php:188`; `tests/src/RecursiveTypeCasterTest.php:60`).

For accepted numeric strings, a dot selects a float and its absence selects an integer. This behavior does not guarantee lossless exponent or precision handling (`src/RecursiveTypeCaster.php:202`).

`recursiveTypeRefinements()` writes refined object values through properties and removes omitted properties with `unset()`. A `stdClass` therefore retains its object representation through nested refinement and optional-blank omission; array-backed objects remain arrays (`src/RecursiveTypeCaster.php:25`; `tests/src/RecursiveTypeCasterTest.php:94`).

<!-- kk:related:start -->
# Related

- Related: [practice-empty-string-to-null-only-when-unholdable](practice-empty-string-to-null-only-when-unholdable.md)
- Related: [map-submission-validation-pipeline](map-submission-validation-pipeline.md)
<!-- kk:related:end -->
