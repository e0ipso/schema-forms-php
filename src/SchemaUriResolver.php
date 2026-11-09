<?php

declare(strict_types=1);

namespace SchemaForms;

use JsonSchema\Uri\UriResolver;
use SchemaForms\Exception\UnsupportedReferenceException;

/**
 * Resolves schema URIs without touching the local filesystem.
 *
 * UriResolver runs is_file(), is_dir() and realpath() on a base without a
 * scheme. Schemas supply these values, so such a base is refused first.
 *
 * @internal
 */
final class SchemaUriResolver {

  /**
   * Resolves a URI against a base URI that has a scheme.
   *
   * @param mixed $uri
   *   The URI to resolve.
   * @param mixed $base_uri
   *   The base URI, or NULL.
   *
   * @return string
   *   The resolved URI; empty when both inputs are empty.
   *
   * @throws \SchemaForms\Exception\UnsupportedReferenceException
   *   When the base URI is not a string or has no scheme.
   */
  public static function resolve(mixed $uri, mixed $base_uri): string {
    if ($base_uri !== NULL && !is_string($base_uri)) {
      throw new UnsupportedReferenceException('External schema retrieval is disabled.');
    }
    $is_relative = is_string($base_uri)
      && preg_match('|^[^/]+://|u', $base_uri) !== 1
      && filter_var($base_uri, FILTER_VALIDATE_URL) === FALSE;
    if ($is_relative) {
      throw new UnsupportedReferenceException('External schema retrieval is disabled.');
    }
    return (string) (new UriResolver())->resolve(is_scalar($uri) ? (string) $uri : '', $base_uri);
  }

}
