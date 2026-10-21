---
type: map
title: 'Submission validation: clean, type-cast, validate, map errors'
description: >-
  Clean and refine submissions, restore fixed values, reject JSON encoding failures, then place schema errors once.
tags:
  - validation
  - drupal
  - json-schema
kk_schema_version: 3
kk_id: map-submission-validation-pipeline
kk_derived_from: []
kk_relates_to:
  - practice-enum-options-assign-keys-directly
  - practice-empty-string-to-null-only-when-unholdable
  - practice-keep-elements-in-supported-list
  - practice-preserve-unrepresentable-numbers-and-object-shape
  - map-multivalue-array-widget
kk_depends_on: []
kk_confidence: medium
---
The generated container registers `FormGeneratorDrupal::validateWithSchema()` through `#element_validate` and `validateFormWithSchema()` through `#validate`. Drupal uses the element hook under a parent key and the form hook at form root, so the bridge runs once in either placement (`src/Drupal/FormGeneratorDrupal.php:102`, `:125`, `:526`; `tests/src/Kernel/ValidationPlacementTest.php:32`).

`FormValidatorDrupal::typeCastRecursive()` cleans multivalue widget keys with `UserInputCleaner`, refines values with `RecursiveTypeCaster`, supplies non-input `#schema_fixed_value` values after casting, then writes back through `setValueForElement()` (`src/Drupal/FormValidatorDrupal.php:154`). Checkbox enum element validation has already made selections into lists; recursive list refinement uses the item schema (`src/RecursiveTypeCaster.php:28`).

`FormValidatorDrupal::validateWithSchema()` checks JSON encodability before `ArrayToStdClass` conversion. Encoding failure sets a container error and returns, so invalid UTF-8, recursion or non-finite values cannot become a valid-looking empty object. Preserve `ArrayToStdClass`'s public fallback of an empty `stdClass` on JSON failure; rejection belongs in the validator (`src/Drupal/FormValidatorDrupal.php:31`; `src/ArrayToStdClass.php:20`; `tests/src/Drupal/FormValidatorDrupalTest.php:371`). Empty converted data becomes `[]` if the schema allows an array, otherwise an empty object, before validation with `CHECK_MODE_TYPE_CAST`.

`buildMappingsElementPaths()` maps JSON pointers to relative form array parents, skipping `removable_element` and the `remove_one`/`add_more` buttons. Mapped errors go on their elements with the title and constraint suffix. Unmapped errors stay plain strings, are joined once, then escaped once by the container's `TranslatableMarkup` `@full_message` placeholder. Pre-rendering or pre-escaping each error would double-escape it (`src/Drupal/FormValidatorDrupal.php:54`, `:95`, `:200`; `tests/src/Drupal/FormValidatorDrupalTest.php:344`).
