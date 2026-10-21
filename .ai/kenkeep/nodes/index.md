---
okf_version: '0.1'
---
# kenkeep Index

> kenkeep navigation: the injected body above is the root index node, the top-level catalog of branches and root-level leaves. Do not expect the whole knowledge base here; descend on demand. Read the root index node, pick one or more branches whose intent and tags match your task (several branches can be relevant), and read those branch `index.md` nodes. Descend further only where the task needs it, opening only the leaves you have confirmed are relevant. Follow each leaf's `relates_to` and `depends_on` cross edges to reach related leaves in other branches. You decide how deep to go per branch.

> This index only orients you; leaves hold the durable guidance. Open at least one relevant leaf before acting.

## Subfolders
_None._

## Conventions (how we build)
- Open [**Add every generated element type to SUPPORTED_ELEMENT_TYPES**](practice-keep-elements-in-supported-list.md) to learn about: Elements not in the list (and not from ui:widget) become a disabled 'cannot be edited' item, by design. #drupal #form-api #gotcha
- Open [**Build enum #options by key assignment, never array_merge**](practice-enum-options-assign-keys-directly.md) to learn about: Assign enum keys directly; checkbox submissions become typed lists in schema order, preserving checked zero. #drupal #enum #gotcha
- Open [**Preserve unrepresentable numbers and object shape**](practice-preserve-unrepresentable-numbers-and-object-shape.md) to learn about: Leave unsafe numeric strings for validation and refine stdClass properties without converting objects into arrays. #type-casting #validation #gotcha
- Open [**Cast only true absence to NULL on nullable props**](practice-empty-string-to-null-only-when-unholdable.md) to learn about: Refine nullable blanks first; omit only optional rejected blanks, using each object's own required list. #type-casting #validation #gotcha
- Open [**Filter empty items only from lists, never from named keys**](practice-keep-empty-arrays-under-named-keys.md) to learn about: UserInputCleaner drops \[\] items in multivalue lists but keeps an array property's legitimate empty value. #user-input #arrays #gotcha
- Open [**Run composer test: GrumPHP runs tests, Drupal phpcs and code rules**](practice-run-composer-test-grumphp.md) to learn about: composer test runs GrumPHP with PHPUnit 11.5 and strict Composer validation; CI separately enforces the coverage floor. #tooling #testing #coding-standards
- Open [**Support justinrainbow/json-schema 5.2 and 6.3 side by side**](practice-support-both-json-schema-majors.md) to learn about: composer allows ^5.2 || ^6.3, and error constraint data changed shape in 6.0. Never assume one shape. #dependencies #json-schema #validation
- Open [**Use Conventional Commits and type/kebab branch names**](practice-conventional-commits-and-branches.md) to learn about: GrumPHP hooks enforce commit type/scope and a 50-char subject. semantic-release tags master from commits. #git #release #conventions

## Components (what exists)
- Open [**Submission validation: clean, type-cast, validate, map errors**](map-submission-validation-pipeline.md) to learn about: Clean and refine submissions, restore fixed values, reject JSON encoding failures, then place schema errors once. #validation #drupal #json-schema
- Open [**Multivalue array widget and its form-state storage**](map-multivalue-array-widget.md) to learn about: Non-enum arrays render as details with per-item remove buttons and an AJAX 'Append an item' button. #drupal #form-api #arrays
- Open [**FormGeneratorDrupal: JSON Schema to Drupal Form API**](map-form-generator-drupal.md) to learn about: transform() turns draft-04 object schemas into Form API containers whose root #tree preserves nested submission paths. #drupal #json-schema #form-api
- Open [**How JSON Schema types map to Form API elements**](map-schema-type-element-mapping.md) to learn about: Type-to-element rules in FormGeneratorDrupal: textfield, email, number, checkbox, radios, details, markup, item fallback. #drupal #json-schema #form-api
- Open [**UI schema controls (ui:*) for form generation**](map-ui-schema-controls.md) to learn about: ui:* hints set titles, help, widgets and enum labels; titles and labels use Xss::filter with its default tag list. #ui-schema #drupal

## By topic

### #drupal
- Open [**FormGeneratorDrupal: JSON Schema to Drupal Form API**](map-form-generator-drupal.md) — transform() turns draft-04 object schemas into Form API containers whose root #tree preserves nested submission paths.
- Open [**How JSON Schema types map to Form API elements**](map-schema-type-element-mapping.md) — Type-to-element rules in FormGeneratorDrupal: textfield, email, number, checkbox, radios, details, markup, item fallback.
- Open [**Add every generated element type to SUPPORTED_ELEMENT_TYPES**](practice-keep-elements-in-supported-list.md) — Elements not in the list (and not from ui:widget) become a disabled 'cannot be edited' item, by design.
### #gotcha
- Open [**Cast only true absence to NULL on nullable props**](practice-empty-string-to-null-only-when-unholdable.md) — Refine nullable blanks first; omit only optional rejected blanks, using each object's own required list.
- Open [**Preserve unrepresentable numbers and object shape**](practice-preserve-unrepresentable-numbers-and-object-shape.md) — Leave unsafe numeric strings for validation and refine stdClass properties without converting objects into arrays.
- Open [**Add every generated element type to SUPPORTED_ELEMENT_TYPES**](practice-keep-elements-in-supported-list.md) — Elements not in the list (and not from ui:widget) become a disabled 'cannot be edited' item, by design.
### #form-api
- Open [**FormGeneratorDrupal: JSON Schema to Drupal Form API**](map-form-generator-drupal.md) — transform() turns draft-04 object schemas into Form API containers whose root #tree preserves nested submission paths.
- Open [**How JSON Schema types map to Form API elements**](map-schema-type-element-mapping.md) — Type-to-element rules in FormGeneratorDrupal: textfield, email, number, checkbox, radios, details, markup, item fallback.
- Open [**Add every generated element type to SUPPORTED_ELEMENT_TYPES**](practice-keep-elements-in-supported-list.md) — Elements not in the list (and not from ui:widget) become a disabled 'cannot be edited' item, by design.
### #json-schema
- Open [**FormGeneratorDrupal: JSON Schema to Drupal Form API**](map-form-generator-drupal.md) — transform() turns draft-04 object schemas into Form API containers whose root #tree preserves nested submission paths.
- Open [**How JSON Schema types map to Form API elements**](map-schema-type-element-mapping.md) — Type-to-element rules in FormGeneratorDrupal: textfield, email, number, checkbox, radios, details, markup, item fallback.
- Open [**Submission validation: clean, type-cast, validate, map errors**](map-submission-validation-pipeline.md) — Clean and refine submissions, restore fixed values, reject JSON encoding failures, then place schema errors once.
### #validation
- Open [**Preserve unrepresentable numbers and object shape**](practice-preserve-unrepresentable-numbers-and-object-shape.md) — Leave unsafe numeric strings for validation and refine stdClass properties without converting objects into arrays.
- Open [**Cast only true absence to NULL on nullable props**](practice-empty-string-to-null-only-when-unholdable.md) — Refine nullable blanks first; omit only optional rejected blanks, using each object's own required list.
- Open [**Submission validation: clean, type-cast, validate, map errors**](map-submission-validation-pipeline.md) — Clean and refine submissions, restore fixed values, reject JSON encoding failures, then place schema errors once.
### #arrays
- Open [**Multivalue array widget and its form-state storage**](map-multivalue-array-widget.md) — Non-enum arrays render as details with per-item remove buttons and an AJAX 'Append an item' button.
- Open [**Filter empty items only from lists, never from named keys**](practice-keep-empty-arrays-under-named-keys.md) — UserInputCleaner drops \[\] items in multivalue lists but keeps an array property's legitimate empty value.
### #type-casting
- Open [**Preserve unrepresentable numbers and object shape**](practice-preserve-unrepresentable-numbers-and-object-shape.md) — Leave unsafe numeric strings for validation and refine stdClass properties without converting objects into arrays.
- Open [**Cast only true absence to NULL on nullable props**](practice-empty-string-to-null-only-when-unholdable.md) — Refine nullable blanks first; omit only optional rejected blanks, using each object's own required list.
### #coding-standards
- Open [**Run composer test: GrumPHP runs tests, Drupal phpcs and code rules**](practice-run-composer-test-grumphp.md) — composer test runs GrumPHP with PHPUnit 11.5 and strict Composer validation; CI separately enforces the coverage floor.
### #conventions
- Open [**Use Conventional Commits and type/kebab branch names**](practice-conventional-commits-and-branches.md) — GrumPHP hooks enforce commit type/scope and a 50-char subject. semantic-release tags master from commits.
### #dependencies
- Open [**Support justinrainbow/json-schema 5.2 and 6.3 side by side**](practice-support-both-json-schema-majors.md) — composer allows ^5.2 || ^6.3, and error constraint data changed shape in 6.0. Never assume one shape.
### #enum
- Open [**Build enum #options by key assignment, never array_merge**](practice-enum-options-assign-keys-directly.md) — Assign enum keys directly; checkbox submissions become typed lists in schema order, preserving checked zero.
### #git
- Open [**Use Conventional Commits and type/kebab branch names**](practice-conventional-commits-and-branches.md) — GrumPHP hooks enforce commit type/scope and a 50-char subject. semantic-release tags master from commits.
### #release
- Open [**Use Conventional Commits and type/kebab branch names**](practice-conventional-commits-and-branches.md) — GrumPHP hooks enforce commit type/scope and a 50-char subject. semantic-release tags master from commits.
### #testing
- Open [**Run composer test: GrumPHP runs tests, Drupal phpcs and code rules**](practice-run-composer-test-grumphp.md) — composer test runs GrumPHP with PHPUnit 11.5 and strict Composer validation; CI separately enforces the coverage floor.
### #tooling
- Open [**Run composer test: GrumPHP runs tests, Drupal phpcs and code rules**](practice-run-composer-test-grumphp.md) — composer test runs GrumPHP with PHPUnit 11.5 and strict Composer validation; CI separately enforces the coverage floor.
### #ui-schema
- Open [**UI schema controls (ui:*) for form generation**](map-ui-schema-controls.md) — ui:* hints set titles, help, widgets and enum labels; titles and labels use Xss::filter with its default tag list.
### #user-input
- Open [**Filter empty items only from lists, never from named keys**](practice-keep-empty-arrays-under-named-keys.md) — UserInputCleaner drops \[\] items in multivalue lists but keeps an array property's legitimate empty value.
