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
- Open [**Build enum #options by key assignment, never array_merge**](practice-enum-options-assign-keys-directly.md) to learn about: array_merge renumbers numeric-string keys and a string-typed closure crashes on null members. Use enumOptions(). #drupal #enum #gotcha
- Open [**Cast only true absence to NULL on nullable props**](practice-empty-string-to-null-only-when-unholdable.md) to learn about: A null union is not a falsy-to-null rule. '' becomes NULL only when no declared type can hold it. #type-casting #validation #gotcha
- Open [**Filter empty items only from lists, never from named keys**](practice-keep-empty-arrays-under-named-keys.md) to learn about: UserInputCleaner drops \[\] items in multivalue lists but keeps an array property's legitimate empty value. #user-input #arrays #gotcha
- Open [**Keep src/ runnable on PHP 7.4**](practice-keep-php-74-compatible.md) to learn about: composer.json allows php >=7.4 on purpose, but CI only runs 8.3 and 8.4, so nothing catches PHP 8 syntax. #php #compatibility #ci
- Open [**Run composer test: GrumPHP runs tests, Drupal phpcs and code rules**](practice-run-composer-test-grumphp.md) to learn about: composer test runs PHPUnit, Drupal/DrupalPractice phpcs, phpmnd and a parser that bans else, exit and var_dump. #tooling #testing #coding-standards
- Open [**Support justinrainbow/json-schema 5.2 and 6.3 side by side**](practice-support-both-json-schema-majors.md) to learn about: composer allows ^5.2 || ^6.3, and error constraint data changed shape in 6.0. Never assume one shape. #dependencies #json-schema #validation
- Open [**Use Conventional Commits and type/kebab branch names**](practice-conventional-commits-and-branches.md) to learn about: GrumPHP hooks enforce commit type/scope and a 50-char subject. semantic-release tags master from commits. #git #release #conventions

## Components (what exists)
- Open [**FormGeneratorDrupal: JSON Schema to Drupal Form API**](map-form-generator-drupal.md) to learn about: Drupal entry point: transform() turns a draft-04 object schema plus Context hints into a Form API container. #drupal #json-schema #form-api
- Open [**How JSON Schema types map to Form API elements**](map-schema-type-element-mapping.md) to learn about: Type-to-element rules in FormGeneratorDrupal: textfield, email, number, checkbox, radios, details, markup, item fallback. #drupal #json-schema #form-api
- Open [**Multivalue array widget and its form-state storage**](map-multivalue-array-widget.md) to learn about: Non-enum arrays render as details with per-item remove buttons and an AJAX 'Append an item' button. #drupal #form-api #arrays
- Open [**Submission validation: clean, type-cast, validate, map errors**](map-submission-validation-pipeline.md) to learn about: validateWithSchema() cleans widget cruft, casts strings to schema types, validates, then pins errors to elements. #validation #drupal #json-schema
- Open [**UI schema controls (ui:*) for form generation**](map-ui-schema-controls.md) to learn about: Per-property ui:* hints: title, help, placeholder, widget, enabled, visible, enum label mappings. Others are ignored. #ui-schema #drupal

## By topic

### #drupal
- Open [**FormGeneratorDrupal: JSON Schema to Drupal Form API**](map-form-generator-drupal.md) — Drupal entry point: transform() turns a draft-04 object schema plus Context hints into a Form API container.
- Open [**How JSON Schema types map to Form API elements**](map-schema-type-element-mapping.md) — Type-to-element rules in FormGeneratorDrupal: textfield, email, number, checkbox, radios, details, markup, item fallback.
- Open [**Add every generated element type to SUPPORTED_ELEMENT_TYPES**](practice-keep-elements-in-supported-list.md) — Elements not in the list (and not from ui:widget) become a disabled 'cannot be edited' item, by design.
### #form-api
- Open [**FormGeneratorDrupal: JSON Schema to Drupal Form API**](map-form-generator-drupal.md) — Drupal entry point: transform() turns a draft-04 object schema plus Context hints into a Form API container.
- Open [**How JSON Schema types map to Form API elements**](map-schema-type-element-mapping.md) — Type-to-element rules in FormGeneratorDrupal: textfield, email, number, checkbox, radios, details, markup, item fallback.
- Open [**Add every generated element type to SUPPORTED_ELEMENT_TYPES**](practice-keep-elements-in-supported-list.md) — Elements not in the list (and not from ui:widget) become a disabled 'cannot be edited' item, by design.
### #gotcha
- Open [**Add every generated element type to SUPPORTED_ELEMENT_TYPES**](practice-keep-elements-in-supported-list.md) — Elements not in the list (and not from ui:widget) become a disabled 'cannot be edited' item, by design.
- Open [**Build enum #options by key assignment, never array_merge**](practice-enum-options-assign-keys-directly.md) — array_merge renumbers numeric-string keys and a string-typed closure crashes on null members. Use enumOptions().
- Open [**Cast only true absence to NULL on nullable props**](practice-empty-string-to-null-only-when-unholdable.md) — A null union is not a falsy-to-null rule. '' becomes NULL only when no declared type can hold it.
### #json-schema
- Open [**FormGeneratorDrupal: JSON Schema to Drupal Form API**](map-form-generator-drupal.md) — Drupal entry point: transform() turns a draft-04 object schema plus Context hints into a Form API container.
- Open [**How JSON Schema types map to Form API elements**](map-schema-type-element-mapping.md) — Type-to-element rules in FormGeneratorDrupal: textfield, email, number, checkbox, radios, details, markup, item fallback.
- Open [**Submission validation: clean, type-cast, validate, map errors**](map-submission-validation-pipeline.md) — validateWithSchema() cleans widget cruft, casts strings to schema types, validates, then pins errors to elements.
### #validation
- Open [**Submission validation: clean, type-cast, validate, map errors**](map-submission-validation-pipeline.md) — validateWithSchema() cleans widget cruft, casts strings to schema types, validates, then pins errors to elements.
- Open [**Support justinrainbow/json-schema 5.2 and 6.3 side by side**](practice-support-both-json-schema-majors.md) — composer allows ^5.2 || ^6.3, and error constraint data changed shape in 6.0. Never assume one shape.
- Open [**Cast only true absence to NULL on nullable props**](practice-empty-string-to-null-only-when-unholdable.md) — A null union is not a falsy-to-null rule. '' becomes NULL only when no declared type can hold it.
### #arrays
- Open [**Filter empty items only from lists, never from named keys**](practice-keep-empty-arrays-under-named-keys.md) — UserInputCleaner drops \[\] items in multivalue lists but keeps an array property's legitimate empty value.
- Open [**Multivalue array widget and its form-state storage**](map-multivalue-array-widget.md) — Non-enum arrays render as details with per-item remove buttons and an AJAX 'Append an item' button.
### #ci
- Open [**Keep src/ runnable on PHP 7.4**](practice-keep-php-74-compatible.md) — composer.json allows php >=7.4 on purpose, but CI only runs 8.3 and 8.4, so nothing catches PHP 8 syntax.
### #coding-standards
- Open [**Run composer test: GrumPHP runs tests, Drupal phpcs and code rules**](practice-run-composer-test-grumphp.md) — composer test runs PHPUnit, Drupal/DrupalPractice phpcs, phpmnd and a parser that bans else, exit and var_dump.
### #compatibility
- Open [**Keep src/ runnable on PHP 7.4**](practice-keep-php-74-compatible.md) — composer.json allows php >=7.4 on purpose, but CI only runs 8.3 and 8.4, so nothing catches PHP 8 syntax.
### #conventions
- Open [**Use Conventional Commits and type/kebab branch names**](practice-conventional-commits-and-branches.md) — GrumPHP hooks enforce commit type/scope and a 50-char subject. semantic-release tags master from commits.
### #dependencies
- Open [**Support justinrainbow/json-schema 5.2 and 6.3 side by side**](practice-support-both-json-schema-majors.md) — composer allows ^5.2 || ^6.3, and error constraint data changed shape in 6.0. Never assume one shape.
### #enum
- Open [**Build enum #options by key assignment, never array_merge**](practice-enum-options-assign-keys-directly.md) — array_merge renumbers numeric-string keys and a string-typed closure crashes on null members. Use enumOptions().
### #git
- Open [**Use Conventional Commits and type/kebab branch names**](practice-conventional-commits-and-branches.md) — GrumPHP hooks enforce commit type/scope and a 50-char subject. semantic-release tags master from commits.
### #php
- Open [**Keep src/ runnable on PHP 7.4**](practice-keep-php-74-compatible.md) — composer.json allows php >=7.4 on purpose, but CI only runs 8.3 and 8.4, so nothing catches PHP 8 syntax.
### #release
- Open [**Use Conventional Commits and type/kebab branch names**](practice-conventional-commits-and-branches.md) — GrumPHP hooks enforce commit type/scope and a 50-char subject. semantic-release tags master from commits.
### #testing
- Open [**Run composer test: GrumPHP runs tests, Drupal phpcs and code rules**](practice-run-composer-test-grumphp.md) — composer test runs PHPUnit, Drupal/DrupalPractice phpcs, phpmnd and a parser that bans else, exit and var_dump.
### #tooling
- Open [**Run composer test: GrumPHP runs tests, Drupal phpcs and code rules**](practice-run-composer-test-grumphp.md) — composer test runs PHPUnit, Drupal/DrupalPractice phpcs, phpmnd and a parser that bans else, exit and var_dump.
### #type-casting
- Open [**Cast only true absence to NULL on nullable props**](practice-empty-string-to-null-only-when-unholdable.md) — A null union is not a falsy-to-null rule. '' becomes NULL only when no declared type can hold it.
### #ui-schema
- Open [**UI schema controls (ui:*) for form generation**](map-ui-schema-controls.md) — Per-property ui:* hints: title, help, placeholder, widget, enabled, visible, enum label mappings. Others are ignored.
### #user-input
- Open [**Filter empty items only from lists, never from named keys**](practice-keep-empty-arrays-under-named-keys.md) — UserInputCleaner drops \[\] items in multivalue lists but keeps an array property's legitimate empty value.
