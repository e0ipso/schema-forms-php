---
type: map
title: 'FormGeneratorDrupal: JSON Schema to Drupal Form API'
description: >-
  Drupal entry point: transform() turns a draft-04 object schema plus Context
  hints into a Form API container.
tags:
  - drupal
  - json-schema
  - form-api
kk_schema_version: 3
kk_id: map-form-generator-drupal
kk_derived_from:
  - README.md
kk_relates_to: []
kk_depends_on: []
kk_confidence: high
---
`SchemaForms\Drupal\FormGeneratorDrupal` (`src/Drupal/FormGeneratorDrupal.php`) is the only generator. It extends Shaper's `TransformationBase`, so callers use `transform($schema, $context)`. The schema is a decoded object (`json_decode($json)`), and the UI hints are an array (`json_decode($json, TRUE)`).

`transform()` checks its input with `JsonSchemaFormValidator`. The schema must validate against `meta-schema-draft4.json` at the repo root and have `type: object`. Any other input throws a `TypeError`. The output passes through `RenderArrayValidator`.

The `Context` takes three optional keys. `ui_hints` is the UI schema keyed by property name. `form_state` is the form's state, which array properties use to keep their item list across rebuilds. `current_input` holds saved values, and a value there becomes `#default_value` ahead of the schema `default`.

The result is a `container` with one child per root property. The root also carries `#json_schema` (the schema, used later by validation) and `#element_validate => [[$generator, 'validateWithSchema']]`. Every child gets `#prop_name`, `#prop_parents`, `#disabled` and `#visible`. Only the root `required` list sets `#required`, and nested `required` lists are ignored. A property that is only a `$ref` throws a `TypeError` in `guessSchemaType()`, because nothing resolves it.

<!-- kk:citations:start -->
# Citations

[1] [README.md](../../../README.md)
<!-- kk:citations:end -->
