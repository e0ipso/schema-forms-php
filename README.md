# Schema Forms
Schema forms is a project that aims to generate form structures for the different PHP frameworks based on the data definitions in JSON Schema.

Frameworks supported:
  - Drupal.

# Installation

Generally this should be already required by the Drupal project that needs to use it.

If you need to require this library independently, you can do so with the following command:

```
composer require e0ipso/schema-forms
```

# Usage
Given the following JSON Schema defining the different properties of a user object.
```json
{
  "title": "A registration form",
  "description": "A simple form example.",
  "type": "object",
  "required": [
    "firstName",
    "lastName"
  ],
  "properties": {
    "firstName": {
      "type": "string",
      "title": "First name",
      "default": "Chuck"
    },
    "lastName": {
      "type": "string",
      "title": "Last name"
    },
    "age": {
      "type": "integer",
      "title": "Age",
      "description": "(earthian year)"
    },
    "bio": {
      "type": "string",
      "title": "Bio"
    },
    "password": {
      "type": "string",
      "title": "Password",
      "minLength": 3,
      "description": "The key to get in."
    },
    "telephone": {
      "type": "string",
      "title": "Telephone",
      "minLength": 10
    }
  }
```

And the following UI JSON Schema refining the form generation process:
```json
{
  "firstName": {
    "ui:autofocus": true,
    "ui:emptyValue": ""
  },
  "age": {
    "ui:widget": "updown",
    "ui:title": "Age of person"
  },
  "bio": {
    "ui:widget": "textarea"
  },
  "password": {
    "ui:widget": "password",
    "ui:help": "Hint: Make it strong!"
  },
  "date": {
    "ui:widget": "alt-datetime"
  },
  "telephone": {
    "ui:options": {
      "inputType": "tel"
    }
  }
}
```

Execute this PHP code:
```php
use SchemaForms\Drupal\FormGeneratorDrupal;
use Shaper\Util\Context;
$generator = new FormGeneratorDrupal();
$context = new Context(['ui_hints => $ui_schema_data]);
$actual_form = $generator->transform($schema_data, $context);
// It generates the following Drupal Form API form:
[
  'firstName' => [
    '#type' => 'textfield',
    '#title' => 'First name',
    '#required' => TRUE,
  ],
  'lastName' => [
    '#type' => 'textfield',
    '#title' => 'Last name',
    '#required' => TRUE,
  ],
  'age' => [
    '#type' => 'number',
    '#title' => 'Age of person',
    '#description' => '(earthian year)'
  ],
  'bio' => [
    '#type' => 'textarea',
    '#title' => 'Bio',
  ],
  'password' => [
    '#type' => 'password',
    '#title' => 'Password',
    '#description' => 'Hint: Make it strong!'
  ],
  'telephone' => [
    '#type' => 'telephone',
    '#title' => 'Telephone',
  ],
];
```

### UI Schema Data
Based on the shape of the data described by the JSON Schema, this library can generate a form.
However, there are multiple ways to generate a form for the same shape of data. The UI schema data
allows you to control the form elements and inputs that will collect the data in the appropriate way.

Supported UI controls are:

  - `$ui_form_data['ui:title']`

    Controls the label associated to the input element. Defaults to the element's `title` property in the JSON Schema. 
  - `$ui_form_data['ui:help']`

    Adds a hint to the input element. Defaults to the element's `description` property in the JSON Schema.
  - `$ui_form_data['ui:placeholder']`

    Adds a placeholder text to the input.
  - `$ui_form_data['ui:widget']`

    Lets you use al alternative input element. For instance, it lets you use `<select>` instead of
    `<input type="radio">`, or use `<input type="hidden">`, among others.
  - `$ui_form_data['ui:enabled']`

    If 0 the form element will be rendered as non-interactive.
  - `$ui_form_data['ui:visible']`

    If 0 the form element will not be rendered.
  - `$ui_form_data['ui:enum']`

    Lets you define how the options for selects and radios are populated. By default, the enum information in the schema
    defines the options. This might not be enough, or even possible.
    - `$ui_form_data['ui:enum']['labels']['mappings']`

    An object defining the label for each key. Ex: `{"uuid1": "Super duper product"}`.
