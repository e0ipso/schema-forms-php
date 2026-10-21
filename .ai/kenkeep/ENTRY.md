---
schema_version: 3
nodes_hash: 'sha256:4e65db5046663e411815cd2ae415c6214a6c31ca63e1dd36b793cb1ba70c471e'
node_count: 13
---
# kenkeep

> kenkeep navigation: the injected body above is the root index node, the top-level catalog of branches and root-level leaves. Do not expect the whole knowledge base here; descend on demand. Read the root index node, pick one or more branches whose intent and tags match your task (several branches can be relevant), and read those branch `index.md` nodes. Descend further only where the task needs it, opening only the leaves you have confirmed are relevant. Follow each leaf's `relates_to` and `depends_on` cross edges to reach related leaves in other branches. You decide how deep to go per branch.

## Branches
_None._

## Conventions (how we build)
- Open [**Add every generated element type to SUPPORTED_ELEMENT_TYPES**](nodes/practice-keep-elements-in-supported-list.md) to learn about: Elements not in the list (and not from ui:widget) become a disabled 'cannot be edited' item, by design. #drupal #form-api #gotcha
- Open [**Build enum #options by key assignment, never array_merge**](nodes/practice-enum-options-assign-keys-directly.md) to learn about: Assign enum keys directly; checkbox submissions become typed lists in schema order, preserving checked zero. #drupal #enum #gotcha
- Open [**Preserve unrepresentable numbers and object shape**](nodes/practice-preserve-unrepresentable-numbers-and-object-shape.md) to learn about: Leave unsafe numeric strings for validation and refine stdClass properties without converting objects into arrays. #type-casting #validation #gotcha
- Open [**Cast only true absence to NULL on nullable props**](nodes/practice-empty-string-to-null-only-when-unholdable.md) to learn about: Refine nullable blanks first; omit only optional rejected blanks, using each object's own required list. #type-casting #validation #gotcha
- Open [**Filter empty items only from lists, never from named keys**](nodes/practice-keep-empty-arrays-under-named-keys.md) to learn about: UserInputCleaner drops \[\] items in multivalue lists but keeps an array property's legitimate empty value. #user-input #arrays #gotcha
- Open [**Run composer test: GrumPHP runs tests, Drupal phpcs and code rules**](nodes/practice-run-composer-test-grumphp.md) to learn about: composer test runs GrumPHP with PHPUnit 11.5 and strict Composer validation; CI separately enforces the coverage floor. #tooling #testing #coding-standards
- Open [**Support justinrainbow/json-schema 5.2 and 6.3 side by side**](nodes/practice-support-both-json-schema-majors.md) to learn about: composer allows ^5.2 || ^6.3, and error constraint data changed shape in 6.0. Never assume one shape. #dependencies #json-schema #validation
- Open [**Use Conventional Commits and type/kebab branch names**](nodes/practice-conventional-commits-and-branches.md) to learn about: GrumPHP hooks enforce commit type/scope and a 50-char subject. semantic-release tags master from commits. #git #release #conventions

## Components (what exists)
- Open [**Submission validation: clean, type-cast, validate, map errors**](nodes/map-submission-validation-pipeline.md) to learn about: Clean and refine submissions, restore fixed values, reject JSON encoding failures, then place schema errors once. #validation #drupal #json-schema
- Open [**Multivalue array widget and its form-state storage**](nodes/map-multivalue-array-widget.md) to learn about: Non-enum arrays render as details with per-item remove buttons and an AJAX 'Append an item' button. #drupal #form-api #arrays
- Open [**FormGeneratorDrupal: JSON Schema to Drupal Form API**](nodes/map-form-generator-drupal.md) to learn about: transform() turns draft-04 object schemas into Form API containers whose root #tree preserves nested submission paths. #drupal #json-schema #form-api
- Open [**How JSON Schema types map to Form API elements**](nodes/map-schema-type-element-mapping.md) to learn about: Type-to-element rules in FormGeneratorDrupal: textfield, email, number, checkbox, radios, details, markup, item fallback. #drupal #json-schema #form-api
- Open [**UI schema controls (ui:*) for form generation**](nodes/map-ui-schema-controls.md) to learn about: ui:* hints set titles, help, widgets and enum labels; titles and labels use Xss::filter with its default tag list. #ui-schema #drupal
