<?php

declare(strict_types=1);

namespace TimeFrontiers;

/**
 * URL utility functions.
 */
class Url {

  /**
   * Add or update query parameters in a URL.
   *
   * @param string $url The base URL
   * @param array $params Key-value pairs to add/update
   * @return string URL with updated query string
   */
  public static function withParams(string $url, array $params):string {
    $url_query = \parse_url($url, PHP_URL_QUERY);

    if (!empty($url_query)) {
      \parse_str($url_query, $existing);
    } else {
      $existing = [];
    }

    $merged = \array_merge($existing, $params);

    // Remove null values
    $merged = \array_filter($merged, fn($v) => $v !== null);

    $base = \explode('?', $url)[0];

    if (empty($merged)) {
      return $base;
    }

    return $base . '?' . \http_build_query($merged);
  }

  /**
   * Remove query parameters from a URL.
   *
   * @param string $url The URL
   * @param array $keys Keys to remove
   * @return string URL with parameters removed
   */
  public static function withoutParams(string $url, array $keys):string {
    $url_query = \parse_url($url, PHP_URL_QUERY);

    if (empty($url_query)) {
      return $url;
    }

    \parse_str($url_query, $params);

    foreach ($keys as $key) {
      unset($params[$key]);
    }

    $base = \explode('?', $url)[0];

    if (empty($params)) {
      return $base;
    }

    return $base . '?' . \http_build_query($params);
  }

  /**
   * Get a specific query parameter from a URL.
   *
   * @param string $url The URL
   * @param string $key The parameter key
   * @param mixed $default Default value if not found
   * @return mixed Parameter value or default
   */
  public static function getParam(string $url, string $key, mixed $default = null):mixed {
    $url_query = \parse_url($url, PHP_URL_QUERY);

    if (empty($url_query)) {
      return $default;
    }

    \parse_str($url_query, $params);

    return $params[$key] ?? $default;
  }

  /**
   * Get all query parameters from a URL.
   *
   * @param string $url The URL
   * @return array Associative array of parameters
   */
  public static function getParams(string $url):array {
    $url_query = \parse_url($url, PHP_URL_QUERY);

    if (empty($url_query)) {
      return [];
    }

    \parse_str($url_query, $params);

    return $params;
  }

  /**
   * Check if a URL exists (returns 200).
   *
   * @param string $url The URL to check
   * @param int $timeout Timeout in seconds
   * @return bool True if URL returns 200
   */
  public static function exists(string $url, int $timeout = 10):bool {
    $ch = \curl_init($url);

    \curl_setopt_array($ch, [
      CURLOPT_NOBODY => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_TIMEOUT => $timeout,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_USERAGENT => 'TimeFrontiers/1.0',
    ]);

    \curl_exec($ch);
    $code = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
    \curl_close($ch);

    return $code === 200;
  }

  /**
   * Check if a URL is accessible (returns 2xx or 3xx).
   *
   * @param string $url The URL to check
   * @param int $timeout Timeout in seconds
   * @return bool True if URL is accessible
   */
  public static function isAccessible(string $url, int $timeout = 10):bool {
    $ch = \curl_init($url);

    \curl_setopt_array($ch, [
      CURLOPT_NOBODY => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_TIMEOUT => $timeout,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_USERAGENT => 'TimeFrontiers/1.0',
    ]);

    \curl_exec($ch);
    $code = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
    \curl_close($ch);

    return $code >= 200 && $code < 400;
  }

  /**
   * Get HTTP status code for a URL.
   *
   * @param string $url The URL to check
   * @param int $timeout Timeout in seconds
   * @return int HTTP status code (0 on failure)
   */
  public static function getStatusCode(string $url, int $timeout = 10):int {
    $ch = \curl_init($url);

    \curl_setopt_array($ch, [
      CURLOPT_NOBODY => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_TIMEOUT => $timeout,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_USERAGENT => 'TimeFrontiers/1.0',
    ]);

    \curl_exec($ch);

    if (\curl_errno($ch)) {
      \curl_close($ch);
      return 0;
    }

    $code = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
    \curl_close($ch);

    return $code;
  }

  /**
   * Parse a URL into components.
   *
   * @param string $url The URL to parse
   * @return array URL components
   */
  public static function parse(string $url):array {
    $parts = \parse_url($url);

    return [
      'scheme' => $parts['scheme'] ?? 'http',
      'host' => $parts['host'] ?? '',
      'port' => $parts['port'] ?? null,
      'user' => $parts['user'] ?? null,
      'pass' => $parts['pass'] ?? null,
      'path' => $parts['path'] ?? '/',
      'query' => $parts['query'] ?? null,
      'fragment' => $parts['fragment'] ?? null,
    ];
  }

  /**
   * Build a URL from components.
   *
   * @param array $parts URL components
   * @return string The built URL
   */
  public static function build(array $parts):string {
    $url = '';

    if (!empty($parts['scheme'])) {
      $url .= $parts['scheme'] . '://';
    }

    if (!empty($parts['user'])) {
      $url .= $parts['user'];
      if (!empty($parts['pass'])) {
        $url .= ':' . $parts['pass'];
      }
      $url .= '@';
    }

    if (!empty($parts['host'])) {
      $url .= $parts['host'];
    }

    if (!empty($parts['port'])) {
      $url .= ':' . $parts['port'];
    }

    $url .= $parts['path'] ?? '/';

    if (!empty($parts['query'])) {
      $url .= '?' . $parts['query'];
    }

    if (!empty($parts['fragment'])) {
      $url .= '#' . $parts['fragment'];
    }

    return $url;
  }

  /**
   * Check if a string is a valid URL.
   *
   * @param string $url The string to validate
   * @return bool True if valid URL
   */
  public static function isValid(string $url):bool {
    return \filter_var($url, FILTER_VALIDATE_URL) !== false;
  }

  /**
   * Get the domain from a URL.
   *
   * @param string $url The URL
   * @return string|null The domain or null
   */
  public static function getDomain(string $url):?string {
    $host = \parse_url($url, PHP_URL_HOST);
    return $host ?: null;
  }

  /**
   * Normalize a URL (add scheme if missing, remove trailing slash).
   *
   * @param string $url The URL to normalize
   * @param string $default_scheme Default scheme if missing
   * @return string Normalized URL
   */
  public static function normalize(string $url, string $default_scheme = 'https'):string {
    $url = \trim($url);

    // Add scheme if missing
    if (!\preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
      $url = $default_scheme . '://' . $url;
    }

    // Remove trailing slash (except for root)
    $path = \parse_url($url, PHP_URL_PATH);
    if ($path && $path !== '/' && \str_ends_with($url, '/')) {
      $url = \rtrim($url, '/');
    }

    return $url;
  }
}
