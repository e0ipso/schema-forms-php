---
type: map
title: 'Submission validation: clean, type-cast, validate, map errors'
description: >-
  Protect untouched saved NULLs during cleaning, refine types, restore fixed
  values and NULL paths, then map schema errors once.
tags:
  - validation
  - drupal
  - json-schema
kk_schema_version: 3
kk_id: map-submission-validation-pipeline
kk_derived_from:
  - src/Drupal/FormValidatorDrupal.php
  - src/Drupal/SavedNull.php
kk_relates_to:
  - practice-enum-options-assign-keys-directly
  - practice-empty-string-to-null-only-when-unholdable
  - practice-keep-elements-in-supported-list
  - practice-preserve-unrepresentable-numbers-and-object-shape
  - map-multivalue-array-widget
  - practice-restrict-schema-reference-retrieval
  - practice-keep-empty-arrays-under-named-keys
kk_depends_on: []
kk_confidence: medium
---
The generated container registers `FormGeneratorDrupal::validateWithSchema()` through `#element_validate` and `validateFormWithSchema()` through `#validate`. Drupal uses the element hook under a parent key and the form hook at form root, so the bridge runs once in either placement (`src/Drupal/FormGeneratorDrupal.php:102`, `:125`, `:526`; `tests/src/Kernel/ValidationPlacementTest.php:32`).

`FormValidatorDrupal::typeCastRecursive()` marks untouched `#schema_saved_null` controls with the internal `SchemaForms\Drupal\SavedNull` placeholder before `UserInputCleaner` can drop their NULL values. Multivalue descendants are marked in `multiValueAfterBuild()` before cleaning compacts deltas; the validator does not walk those old item paths again. It cleans widget keys, replaces placeholders with NULL while recording their cleaned paths, refines types with `RecursiveTypeCaster`, supplies non-input `#schema_fixed_value` values, restores the recorded NULL paths again, then writes through `setValueForElement()`. That final restoration prevents fixed child properties from turning a saved NULL object into an object. `isUntouched()` checks the empty representation of each control, including an unchecked checkbox, and ignores buttons and fixed controls. A nonempty edit bypasses the placeholder and saves normally. Editable saved NULL controls must stay editable; marking them as fixed values would discard edits. See `src/Drupal/SavedNull.php`, `src/Drupal/FormValidatorDrupal.php` and `tests/src/Kernel/SavedValuesSubmissionTest.php`. Checkbox enum element validation has already made selections into lists; recursive list refinement uses the item schema (`src/RecursiveTypeCaster.php:28`).

`FormValidatorDrupal::validateWithSchema()` checks JSON encodability before `ArrayToStdClass` conversion. Encoding failure sets a container error and returns, so invalid UTF-8, recursion or non-finite values cannot become a valid-looking empty object. Preserve `ArrayToStdClass`'s public fallback of an empty `stdClass` on JSON failure; rejection belongs in the validator (`src/Drupal/FormValidatorDrupal.php:31`; `src/ArrayToStdClass.php:20`; `tests/src/Drupal/FormValidatorDrupalTest.php:371`). Empty converted data becomes `[]` if the schema allows an array, otherwise an empty object, before validation with `CHECK_MODE_TYPE_CAST`.

`buildMappingsElementPaths()` maps JSON pointers to relative form array parents, skipping `removable_element` and the `remove_one`/`add_more` buttons. Each property segment is escaped with `~` to `~0`, `/` to `~1` and `%` to `%25`, the form both supported json-schema majors use in error pointers; without it, errors for a property such as `a/b` or `a%b` fall through to the container. `testSpecialPropertyErrors` covers flat, nested and gapped multivalue placement (`src/Drupal/FormValidatorDrupal.php:222`). Cleaned list values use compact ordinal pointers even when stored form deltas have removal gaps. The mapper counts actual `removable_element` children in order while retaining each child's real `#array_parents`, so an error for item ordinal 1 reaches the second remaining widget. See `tests/src/Kernel/ValidationPlacementTest.php`. Mapped errors go on their elements with the title and constraint suffix. Unmapped errors stay plain strings, are joined once, then escaped once by the container's `TranslatableMarkup` `@full_message` placeholder. Pre-rendering or pre-escaping each error would double-escape it (`src/Drupal/FormValidatorDrupal.php:54`, `:95`, `:200`; `tests/src/Drupal/FormValidatorDrupalTest.php:344`).

Generation, submission validation and optional-blank refinement share `SchemaReferenceResolver::createFactory()`. Unsupported references become a container error without retrieving their targets. The resolver checks schema branches before blank omission can bypass them; see the related reference policy.

Submission regressions for unchanged saved values need an actual Save trigger. `SchemaFormsKernelTestBase::submitSchemaForm()` builds `GeneratedSchemaForm` without its optional Save button, so an empty multivalue widget can make Append the implicit trigger and its limited validation can omit sibling values. Use `GeneratedSchemaForm(..., withSave: TRUE)` through the real FormBuilder, submit `op => Save`, and assert the triggering element. `SavedValuesSubmissionTest::save()` collects browser controls using their final `#parents` and covers form-root, nested, multivalue, edited and unchanged saved values.

<!-- kk:related:start -->
# Related

- Related: [practice-enum-options-assign-keys-directly](practice-enum-options-assign-keys-directly.md)
- Related: [practice-empty-string-to-null-only-when-unholdable](practice-empty-string-to-null-only-when-unholdable.md)
- Related: [practice-keep-elements-in-supported-list](practice-keep-elements-in-supported-list.md)
- Related: [practice-preserve-unrepresentable-numbers-and-object-shape](practice-preserve-unrepresentable-numbers-and-object-shape.md)
- Related: [map-multivalue-array-widget](map-multivalue-array-widget.md)
- Related: [practice-restrict-schema-reference-retrieval](practice-restrict-schema-reference-retrieval.md)
- Related: [practice-keep-empty-arrays-under-named-keys](practice-keep-empty-arrays-under-named-keys.md)
<!-- kk:related:end -->

<!-- kk:citations:start -->
# Citations

[1] [src/Drupal/FormValidatorDrupal.php](../../../src/Drupal/FormValidatorDrupal.php)
[2] [src/Drupal/SavedNull.php](../../../src/Drupal/SavedNull.php)
<!-- kk:citations:end -->
