---
type: map
title: 'UI schema controls (ui:*) for form generation'
description: >-
  ui:* hints set titles, help, widgets and enum labels; titles and labels use Xss::filter with its default tag list.
tags:
  - ui-schema
  - drupal
kk_schema_version: 3
kk_id: map-ui-schema-controls
kk_derived_from:
  - README.md
kk_relates_to:
  - practice-enum-options-assign-keys-directly
  - map-form-generator-drupal
kk_depends_on: []
kk_confidence: high
---
The UI schema is an array keyed by property name. For an `object` property the sub-property hints nest under the property's key. An `array` property passes its own hints to every item.

The generator reads these keys and ignores the rest:

- `ui:title` sets `#title`. The fallback order is `ui:title`, then the schema `title`, then the machine name with `_`/`-` turned into spaces and each word capitalized.
- `ui:help` sets `#description` and falls back to the schema `description`.
- `ui:placeholder` sets `#placeholder`.
- `ui:widget` becomes `#type` unchanged. It is a Drupal element type (`select`, `textarea`, `password`, `hidden`), never a react-jsonschema-form widget name. Nothing checks it, so an unknown name such as `updown` renders nothing. A widget also wins over the schema type, so an `array` with a widget never becomes the multivalue widget.
- `ui:enabled: 0` sets `#disabled`.
- `ui:visible: 0` sets `#visible => FALSE`. Drupal core never reads `#visible`, so this hides nothing unless the consuming code acts on it.
- `ui:enum.labels.mappings` maps enum values to option labels.

Titles and enum labels pass through `Xss::filter()` with its default tag list, then `Markup::create()`. This strips images and tables while preserving allowed emphasis and links. Keep the filter before marking the text safe. This contract covers schema/UI titles and radio/checkbox enum labels, including mapped labels; const markup, descriptions and placeholders keep their separate output behavior (`src/Drupal/FormGeneratorDrupal.php:438`, `:736`; `tests/src/Kernel/SchemaLabelRenderingTest.php:28`, `:66`).

<!-- kk:citations:start -->
# Citations

[1] [README.md](../../../README.md)
<!-- kk:citations:end -->
