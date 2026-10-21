---
type: map
title: 'FormGeneratorDrupal: JSON Schema to Drupal Form API'
description: >-
  transform() turns draft-04 object schemas into Form API containers whose root #tree preserves nested submission paths.
tags:
  - drupal
  - json-schema
  - form-api
kk_schema_version: 3
kk_id: map-form-generator-drupal
kk_derived_from:
  - README.md
kk_relates_to:
  - map-submission-validation-pipeline
  - map-multivalue-array-widget
  - practice-keep-elements-in-supported-list
kk_depends_on: []
kk_confidence: high
---
`SchemaForms\Drupal\FormGeneratorDrupal` (`src/Drupal/FormGeneratorDrupal.php`) is the only generator. It extends Shaper's `TransformationBase`, so callers use `transform($schema, $context)`. The schema is a decoded object (`json_decode($json)`), and the UI hints are an array (`json_decode($json, TRUE)`).

`transform()` checks its input with `JsonSchemaFormValidator`. The schema must validate against `meta-schema-draft4.json` at the repo root and have `type: object`. Any other input throws a `TypeError`. The output passes through `RenderArrayValidator`.

The `Context` takes three optional keys. `ui_hints` is the UI schema keyed by property name. `form_state` is the form's state, which array properties use to keep their item list across rebuilds. `current_input` holds saved values, and a value there becomes `#default_value` ahead of the schema `default`.

The result is a `container` with `#tree => TRUE` and one child per root property. Preserve that root flag even under a key in a parent form whose `#tree` is false: nested objects and multivalue wrappers need schema-shaped paths to avoid flattening and key collisions (`src/Drupal/FormGeneratorDrupal.php:64`; `tests/src/Kernel/ValidationPlacementTest.php:132`). The root also carries `#json_schema` (the schema, used later by validation) and `#element_validate => [[$generator, 'validateWithSchema']]`. Children carry `#prop_name` and `#prop_parents`; ordinary input elements also carry `#disabled` and `#visible`. The root `required` list sets Form API `#required` on input children. Nested required lists govern schema refinement and validation, while non-input fixed values skip Form API required flags (`src/Drupal/FormGeneratorDrupal.php:165`, `:526`). The root also registers the form-level `#validate` bridge for placement as the form itself. `getSchema()` dereferences schema references before building controls (`src/Drupal/FormGeneratorDrupal.php:595`).

<!-- kk:citations:start -->
# Citations

[1] [README.md](../../../README.md)
<!-- kk:citations:end -->
