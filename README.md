[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/e0ipso/schema-forms-php/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/e0ipso/schema-forms-php/?branch=master) [![Code Coverage](https://scrutinizer-ci.com/g/e0ipso/schema-forms-php/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/e0ipso/schema-forms-php/?branch=master) [![Build Status](https://scrutinizer-ci.com/g/e0ipso/schema-forms-php/badges/build.png?b=master)](https://scrutinizer-ci.com/g/e0ipso/schema-forms-php/build-status/master)
# Schema Forms
Schema forms is a project that aims to generate form structures for the different PHP frameworks based on the data definitions in JSON Schema.

Frameworks supported:
  - Drupal.

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
      "description": "The key to get in.",
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
$generator = new FormGeneratorDrupal();
$actual_form = $generator->transform($schema_data, $ui_schema_data);
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
    '#description' => 'Make it strong!'
  ],
  'telephone' => [
    '#type' => 'telephone',
    '#title' => 'Telephone',
  ],
];
```
