# Schema Forms

Schema Forms builds forms for PHP frameworks from a JSON Schema that describes the data. You give it the schema and, optionally, a UI schema with per-property display hints. It returns a form definition for the framework.

Drupal is the only supported framework. The generator returns a Drupal Form API render array.

## Installation

A Drupal project that uses this library usually requires it already. To require it yourself:

```
composer require e0ipso/schema-forms
```

The package requires PHP 8.3 or later. CI runs on PHP 8.3 and 8.4. The Drupal generator uses Drupal core classes, and Drupal core is only a dev dependency here, so run the generator inside a Drupal site.

## Usage

Take this JSON Schema for a registration form:

```json
{
  "title": "A registration form",
  "type": "object",
  "required": ["firstName", "lastName"],
  "properties": {
    "firstName": {"type": "string", "title": "First name", "default": "Chuck"},
    "lastName": {"type": "string", "title": "Last name"},
    "age": {"type": "integer", "title": "Age", "description": "(earthian year)"},
    "email": {"type": "string", "format": "email"},
    "bio": {"type": "string", "title": "Bio"},
    "password": {"type": "string", "title": "Password", "minLength": 3},
    "plan": {"type": "string", "enum": ["free", "pro"]},
    "newsletter": {"type": "boolean", "title": "Send me the newsletter"}
  }
}
```

And this UI schema:

```json
{
  "age": {"ui:title": "Age of person"},
  "email": {"ui:placeholder": "you@example.com"},
  "bio": {"ui:widget": "textarea"},
  "password": {"ui:widget": "password", "ui:help": "Hint: Make it strong!"},
  "plan": {
    "ui:widget": "select",
    "ui:enum": {"labels": {"mappings": {"pro": "Pro (paid)"}}}
  }
}
```

Pass both to the generator. Decode the schema to objects and the UI schema to arrays:

```php
use SchemaForms\Drupal\FormGeneratorDrupal;
use Shaper\Util\Context;

$schema_data = json_decode($schema_json);
$ui_schema_data = json_decode($ui_schema_json, TRUE);

$generator = new FormGeneratorDrupal();
$context = new Context(['ui_hints' => $ui_schema_data]);
$form = $generator->transform($schema_data, $context);
```

`$form` is this render array. The root container also carries `#json_schema` (the decoded schema) and `#element_validate`, left out here:

```php
[
  '#type' => 'container',
  'firstName' => [
    '#title' => 'First name',
    '#type' => 'textfield',
    '#prop_name' => 'firstName',
    '#default_value' => 'Chuck',
    '#prop_parents' => ['firstName'],
    '#disabled' => FALSE,
    '#visible' => TRUE,
    '#required' => TRUE,
  ],
  'lastName' => [
    '#title' => 'Last name',
    '#type' => 'textfield',
    '#prop_name' => 'lastName',
    '#prop_parents' => ['lastName'],
    '#disabled' => FALSE,
    '#visible' => TRUE,
    '#required' => TRUE,
  ],
  'age' => [
    '#title' => 'Age of person',
    '#type' => 'number',
    '#prop_name' => 'age',
    '#description' => '(earthian year)',
    '#step' => 1,
    '#prop_parents' => ['age'],
    '#disabled' => FALSE,
    '#visible' => TRUE,
    '#required' => FALSE,
  ],
  'email' => [
    '#title' => 'Email',
    '#type' => 'email',
    '#prop_name' => 'email',
    '#placeholder' => 'you@example.com',
    '#prop_parents' => ['email'],
    '#disabled' => FALSE,
    '#visible' => TRUE,
    '#required' => FALSE,
  ],
  'bio' => [
    '#title' => 'Bio',
    '#type' => 'textarea',
    '#prop_name' => 'bio',
    '#prop_parents' => ['bio'],
    '#disabled' => FALSE,
    '#visible' => TRUE,
    '#required' => FALSE,
  ],
  'password' => [
    '#title' => 'Password',
    '#type' => 'password',
    '#prop_name' => 'password',
    '#description' => 'Hint: Make it strong!',
    '#prop_parents' => ['password'],
    '#disabled' => FALSE,
    '#visible' => TRUE,
    '#required' => FALSE,
  ],
  'plan' => [
    '#title' => 'Plan',
    '#type' => 'select',
    '#prop_name' => 'plan',
    '#prop_parents' => ['plan'],
    '#options' => ['free' => 'Free', 'pro' => 'Pro (paid)'],
    '#disabled' => FALSE,
    '#visible' => TRUE,
    '#required' => FALSE,
  ],
  'newsletter' => [
    '#title' => 'Send me the newsletter',
    '#type' => 'checkbox',
    '#prop_name' => 'newsletter',
    '#prop_parents' => ['newsletter'],
    '#disabled' => FALSE,
    '#visible' => TRUE,
    '#required' => FALSE,
  ],
]
```

The input schema must be a valid draft-04 JSON Schema whose root `type` is `object`. Anything else makes `transform()` throw a `TypeError`. A property name that starts with `#`, at any depth, makes `transform()` throw an `\InvalidArgumentException` naming the property path, because Drupal would read the name as a render property.

### Context keys

The `Context` accepts three keys, all optional:

- `ui_hints`: the UI schema, as an array keyed by property name.
- `form_state`: the form's `FormStateInterface`. Array properties keep their item list in the form state storage, so pass the real form state when the schema has arrays. Without it the generator uses a fresh `FormState`, and appended items are lost on rebuild.
- `current_input`: previously saved values, keyed like the schema. A value here becomes the element's `#default_value` and wins over the schema's `default`.

### How schema types become elements

| JSON Schema | Form API element |
|---|---|
| `string` | `textfield` |
| `string` with `"format": "email"` | `email` |
| `integer` | `number` with `#step` 1 |
| `number` | `number` |
| `boolean` | `checkbox` |
| any type with `enum` | `radios`, options from the enum |
| `array` whose `items` has an `enum` | `checkboxes`, options from the enum |
| any other `array` | an open `details` element with one control per item, an "Append an item" button and a remove button per item. Both buttons use AJAX. |
| `object` with `properties` | an open `details` element with one control per sub-property |
| a property with `const` | the constant as `#markup`, with no input |

A type with no element in this list, such as `null`, renders as a disabled item that says the property cannot be edited. It does not disappear from the form.

When `type` is a list such as `["string", "null"]`, the generator uses the first entry. Put `null` last: `["null", "string"]` renders as the "cannot be edited" item.

The element title comes from `ui:title`, then the schema `title`, then the property name with `_` and `-` turned into spaces and each word capitalized. Enum option labels come from `ui:enum` mappings, or the enum value formatted the same way.

### UI schema data

The same data shape can be collected with different form controls. The UI schema picks them. Its keys are property names. For an `object` property, nest the sub-property hints under the property's key. An `array` property passes its hints on to every item.

Supported controls:

- `ui:title`: the element label. Defaults to the property's `title`.
- `ui:help`: the element description. Defaults to the property's `description`.
- `ui:placeholder`: placeholder text for the input.
- `ui:widget`: a Drupal Form API element type, such as `select`, `textarea`, `password` or `hidden`. The generator uses the value as `#type` unchanged and does not check it. A name Drupal does not know, such as the react-jsonschema-form widget `updown`, renders nothing.
- `ui:enabled`: `0` sets `#disabled` on the element and on every element nested in it, including array items and their sub-properties. A disabled parent wins over a child's own `ui:enabled: 1`.
- `ui:visible`: `0` sets `#visible` to `FALSE`. Drupal core does not read `#visible`, so the element still renders unless your code acts on that key.
- `ui:enum`: controls the labels for select, radio and checkbox options. `ui:enum.labels.mappings` maps each enum value to a label, e.g. `{"uuid1": "Super duper product"}`.

Other keys are ignored.

### Validating submissions

The root container has an `#element_validate` callback. When Drupal validates the form, it:

1. Removes the add and remove button values that array properties leave in the submission.
2. Casts the submitted strings to the schema types. Numeric strings become `int` or `float`, `"0"` and `"1"` become booleans on a `boolean` property, and an empty string becomes `NULL` on a nullable property that cannot hold it.
3. Validates the result against the schema with `justinrainbow/json-schema`. Each error is set on the element it belongs to. Errors that match no element are joined into one error on the container.

### Limitations

- `$ref` is not resolved. A property that is only a `$ref` throws a `TypeError`.
- Only the root `required` list sets `#required`. A `required` list inside a nested object does not mark its sub-properties.

## Development

`composer test` runs GrumPHP. It runs PHPUnit, PHP_CodeSniffer with the Drupal and DrupalPractice standards, PHP lint, phpmnd, composer normalize, and YAML and JSON lint.

GrumPHP also installs git hooks that check commit messages and branch names. Commits follow Conventional Commits (`fix(drupal): ...`, with a subject of 50 characters or fewer). Branches are named `<type>/<kebab-case>`, for example `fix/enum-options`. semantic-release tags a release from the commit messages on every push to `master`.
