<?php

namespace SchemaForms\Drupal;

/**
 * Stands in for a saved NULL while submitted values are cleaned.
 *
 * UserInputCleaner drops NULL under named keys, so an untouched control that
 * held a saved NULL gets this placeholder until cleaning has finished.
 *
 * @internal
 */
final class SavedNull {

}
