---
schema_version: 3
nodes_hash: 'sha256:14adc6ccc5189faf00b979913b297446f46e1ccdcf4393c2c865cac5a230d473'
node_count: 14
---
# kenkeep Graph

Total nodes: 14

## map-form-generator-drupal

- **kind:** map
- **title:** FormGeneratorDrupal: JSON Schema to Drupal Form API
- **path:** map-form-generator-drupal.md
- **tags:** drupal, json-schema, form-api
- **relates_to:** map-submission-validation-pipeline, map-multivalue-array-widget, practice-keep-elements-in-supported-list, practice-preserve-unrepresentable-numbers-and-object-shape
- **derived_from:** README.md, src/Drupal/FormGeneratorDrupal.php

## map-multivalue-array-widget

- **kind:** map
- **title:** Multivalue array widget and its form-state storage
- **path:** map-multivalue-array-widget.md
- **tags:** drupal, form-api, arrays
- **relates_to:** map-submission-validation-pipeline, map-form-generator-drupal
- **derived_from:** src/Drupal/FormGeneratorDrupal.php

## map-schema-type-element-mapping

- **kind:** map
- **title:** How JSON Schema types map to Form API elements
- **path:** map-schema-type-element-mapping.md
- **tags:** drupal, json-schema, form-api
- **relates_to:** practice-keep-elements-in-supported-list, practice-enum-options-assign-keys-directly
- **derived_from:** README.md, src/Drupal/FormGeneratorDrupal.php, src/RecursiveTypeCaster.php

## map-submission-validation-pipeline

- **kind:** map
- **title:** Submission validation: clean, type-cast, validate, map errors
- **path:** map-submission-validation-pipeline.md
- **tags:** validation, drupal, json-schema
- **relates_to:** practice-enum-options-assign-keys-directly, practice-empty-string-to-null-only-when-unholdable, practice-keep-elements-in-supported-list, practice-preserve-unrepresentable-numbers-and-object-shape, map-multivalue-array-widget, practice-restrict-schema-reference-retrieval, practice-keep-empty-arrays-under-named-keys
- **derived_from:** src/Drupal/FormValidatorDrupal.php, src/Drupal/SavedNull.php

## map-ui-schema-controls

- **kind:** map
- **title:** UI schema controls (ui:*) for form generation
- **path:** map-ui-schema-controls.md
- **tags:** ui-schema, drupal
- **relates_to:** practice-enum-options-assign-keys-directly, map-form-generator-drupal
- **derived_from:** README.md

## practice-conventional-commits-and-branches

- **kind:** practice
- **title:** Use Conventional Commits and type/kebab branch names
- **path:** practice-conventional-commits-and-branches.md
- **tags:** git, release, conventions
- **relates_to:** practice-run-composer-test-grumphp
- **derived_from:** grumphp.yml

## practice-empty-string-to-null-only-when-unholdable

- **kind:** practice
- **title:** Cast only true absence to NULL on nullable props
- **path:** practice-empty-string-to-null-only-when-unholdable.md
- **tags:** type-casting, validation, gotcha
- **relates_to:** map-submission-validation-pipeline, practice-preserve-unrepresentable-numbers-and-object-shape

## practice-enum-options-assign-keys-directly

- **kind:** practice
- **title:** Build enum #options by key assignment, never array_merge
- **path:** practice-enum-options-assign-keys-directly.md
- **tags:** drupal, enum, gotcha
- **relates_to:** map-submission-validation-pipeline, practice-preserve-unrepresentable-numbers-and-object-shape
- **derived_from:** src/Drupal/FormGeneratorDrupal.php, src/RecursiveTypeCaster.php

## practice-keep-elements-in-supported-list

- **kind:** practice
- **title:** Add every generated element type to SUPPORTED_ELEMENT_TYPES
- **path:** practice-keep-elements-in-supported-list.md
- **tags:** drupal, form-api, gotcha
- **relates_to:** map-schema-type-element-mapping, map-submission-validation-pipeline

## practice-keep-empty-arrays-under-named-keys

- **kind:** practice
- **title:** Filter empty items only from lists, never from named keys
- **path:** practice-keep-empty-arrays-under-named-keys.md
- **tags:** user-input, arrays, gotcha

## practice-preserve-unrepresentable-numbers-and-object-shape

- **kind:** practice
- **title:** Preserve unrepresentable numbers and object shape
- **path:** practice-preserve-unrepresentable-numbers-and-object-shape.md
- **tags:** type-casting, validation, gotcha
- **relates_to:** practice-empty-string-to-null-only-when-unholdable, map-submission-validation-pipeline

## practice-restrict-schema-reference-retrieval

- **kind:** practice
- **title:** Keep schema references inside registered documents
- **path:** practice-restrict-schema-reference-retrieval.md
- **tags:** json-schema, validation, security, gotcha
- **relates_to:** map-form-generator-drupal, map-submission-validation-pipeline, practice-support-both-json-schema-majors

## practice-run-composer-test-grumphp

- **kind:** practice
- **title:** Run composer test: GrumPHP runs tests, Drupal phpcs and code rules
- **path:** practice-run-composer-test-grumphp.md
- **tags:** tooling, testing, coding-standards
- **relates_to:** map-submission-validation-pipeline, practice-conventional-commits-and-branches
- **derived_from:** README.md

## practice-support-both-json-schema-majors

- **kind:** practice
- **title:** Support justinrainbow/json-schema 5.2 and 6.3 side by side
- **path:** practice-support-both-json-schema-majors.md
- **tags:** dependencies, json-schema, validation
- **relates_to:** practice-restrict-schema-reference-retrieval
- **derived_from:** src/SchemaReferenceResolver.php, scripts/test-json-schema-5.sh
