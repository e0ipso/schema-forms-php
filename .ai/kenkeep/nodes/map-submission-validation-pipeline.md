---
type: map
title: 'Submission validation: clean, type-cast, validate, map errors'
description: >-
  validateWithSchema() cleans widget cruft, casts strings to schema types,
  validates, then pins errors to elements.
tags:
  - validation
  - drupal
  - json-schema
kk_schema_version: 3
kk_id: map-submission-validation-pipeline
kk_derived_from: []
kk_relates_to: []
kk_depends_on: []
kk_confidence: medium
---
The root container's `#element_validate` calls `FormGeneratorDrupal::validateWithSchema()`, which reads `#json_schema` and runs two steps in `FormValidatorDrupal` (`src/Drupal/FormValidatorDrupal.php`):

1. `typeCastRecursive()` runs `UserInputCleaner::cleanUserInput()` to strip the multivalue button values and nesting. It then runs `RecursiveTypeCaster::recursiveTypeRefinements()` to turn form strings into schema types, and writes the result back with `setValueForElement()`.
2. `validateWithSchema()` turns the value into `stdClass` with `ArrayToStdClass` and validates it with `justinrainbow/json-schema`. An empty array becomes `[]` when the schema allows `array` and `{}` otherwise.

`buildMappingsElementPaths()` maps each JSON pointer to form array parents. It skips the `removable_element` level and the `remove_one`/`add_more` buttons, so `/root/0/foo` finds `['root', 0, 'removable_element', 'foo']`. An error whose pointer matches an element is set on that element as `%title: message [JSON Schema violation of "<constraint>"]`. The rest are joined into one error on the container.

The caster tries number, boolean, null, then string. A numeric string becomes `int` when it has no `.` and `float` when it does. `'0'`/`'1'`/`0`/`1` become booleans only on a `boolean` type.
