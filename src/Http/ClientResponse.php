<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

/**
 * HTTP client response wrapper.
 *
 * Provides convenient methods to access response data.
 */
class ClientResponse {

  private int $_status_code;
  private string $_body;
  private array $_headers;
  private ?string $_error;
  private mixed $_decoded = null;

  public function __construct(
    int $status_code,
    string $body,
    array $headers = [],
    ?string $error = null
  ) {
    $this->_status_code = $status_code;
    $this->_body = $body;
    $this->_headers = $headers;
    $this->_error = $error;
  }

  // =========================================================================
  // Status
  // =========================================================================

  /**
   * Get HTTP status code.
   */
  public function statusCode():int {
    return $this->_status_code;
  }

  /**
   * Get HTTP status as enum (if valid).
   */
  public function status():?HttpStatus {
    return HttpStatus::fromCode($this->_status_code);
  }

  /**
   * Get status message (e.g., "200 OK").
   */
  public function statusMessage():string {
    $status = $this->status();
    return $status ? $status->line() : "{$this->_status_code} Unknown";
  }

  /**
   * Check if response is successful (2xx).
   */
  public function isSuccess():bool {
    return $this->_status_code >= 200 && $this->_status_code < 300;
  }

  /**
   * Check if response is a redirect (3xx).
   */
  public function isRedirect():bool {
    return $this->_status_code >= 300 && $this->_status_code < 400;
  }

  /**
   * Check if response is a client error (4xx).
   */
  public function isClientError():bool {
    return $this->_status_code >= 400 && $this->_status_code < 500;
  }

  /**
   * Check if response is a server error (5xx).
   */
  public function isServerError():bool {
    return $this->_status_code >= 500 && $this->_status_code < 600;
  }

  /**
   * Check if response is an error (4xx or 5xx).
   */
  public function isError():bool {
    return $this->_status_code >= 400;
  }

  /**
   * Check if response is OK (200).
   */
  public function isOk():bool {
    return $this->_status_code === 200;
  }

  /**
   * Check if request failed (cURL error).
   */
  public function isFailed():bool {
    return $this->_error !== null;
  }

  // =========================================================================
  // Body
  // =========================================================================

  /**
   * Get raw response body.
   */
  public function body():string {
    return $this->_body;
  }

  /**
   * Get response body as text.
   */
  public function text():string {
    return $this->_body;
  }

  /**
   * Decode JSON response body.
   *
   * @param bool $assoc Return associative array (default: true)
   * @return mixed Decoded data or null on failure
   */
  public function json(bool $assoc = true):mixed {
    if ($this->_decoded === null) {
      $this->_decoded = \json_decode($this->_body, $assoc);
    }

    return $this->_decoded;
  }

  /**
   * Get a value from JSON response using dot notation.
   *
   * @param string $key Key with dot notation (e.g., "data.user.name")
   * @param mixed $default Default value if not found
   * @return mixed Value or default
   */
  public function get(string $key, mixed $default = null):mixed {
    $data = $this->json();

    if (!\is_array($data)) {
      return $default;
    }

    $keys = \explode('.', $key);
    $value = $data;

    foreach ($keys as $k) {
      if (!\is_array($value) || !\array_key_exists($k, $value)) {
        return $default;
      }
      $value = $value[$k];
    }

    return $value;
  }

  /**
   * Decode XML response body.
   *
   * @return \SimpleXMLElement|false XML object or false on failure
   */
  public function xml():\SimpleXMLElement|false {
    \libxml_use_internal_errors(true);
    $xml = \simplexml_load_string($this->_body);
    \libxml_clear_errors();

    return $xml;
  }

  // =========================================================================
  // Headers
  // =========================================================================

  /**
   * Get all response headers.
   */
  public function headers():array {
    return $this->_headers;
  }

  /**
   * Get a specific header.
   *
   * @param string $name Header name (case-insensitive)
   * @return string|null Header value or null
   */
  public function header(string $name):?string {
    // Try exact match first
    if (isset($this->_headers[$name])) {
      return $this->_headers[$name];
    }

    // Case-insensitive search
    $lower = \strtolower($name);
    foreach ($this->_headers as $key => $value) {
      if (\strtolower($key) === $lower) {
        return $value;
      }
    }

    return null;
  }

  /**
   * Check if a header exists.
   */
  public function hasHeader(string $name):bool {
    return $this->header($name) !== null;
  }

  /**
   * Get Content-Type header.
   */
  public function contentType():?string {
    return $this->header('Content-Type');
  }

  /**
   * Check if response is JSON content type.
   */
  public function isJson():bool {
    $type = $this->contentType();
    return $type !== null && \str_contains($type, 'json');
  }

  // =========================================================================
  // Error Handling
  // =========================================================================

  /**
   * Get cURL error message.
   */
  public function error():?string {
    return $this->_error;
  }

  /**
   * Throw exception if response is an error.
   *
   * @throws \RuntimeException If status code is 4xx or 5xx
   * @return self For chaining
   */
  public function throwIfError():self {
    if ($this->isFailed()) {
      throw new \RuntimeException("HTTP request failed: {$this->_error}");
    }

    if ($this->isError()) {
      $message = $this->get('message')
        ?? $this->get('error')
        ?? $this->statusMessage();

      throw new \RuntimeException("HTTP {$message}", $this->_status_code);
    }

    return $this;
  }

  // =========================================================================
  // Debug
  // =========================================================================

  /**
   * Get response as array for debugging.
   */
  public function toArray():array {
    return [
      'status_code' => $this->_status_code,
      'headers' => $this->_headers,
      'body' => $this->_body,
      'json' => $this->json(),
      'error' => $this->_error,
    ];
  }

  /**
   * Magic method for string conversion.
   */
  public function __toString():string {
    return $this->_body;
  }
}
