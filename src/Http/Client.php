<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

use TimeFrontiers\Helper\HasErrors;

/**
 * HTTP client using cURL.
 *
 * Supports GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS methods.
 *
 * Usage:
 *   $client = new Client();
 *   $response = $client->get('https://api.example.com/users');
 *
 *   if ($response->isSuccess()) {
 *     $data = $response->json();
 *   }
 */
class Client {

  use HasErrors;

  public const VERSION = '2.0';

  // HTTP Methods
  public const GET = 'GET';
  public const POST = 'POST';
  public const PUT = 'PUT';
  public const PATCH = 'PATCH';
  public const DELETE = 'DELETE';
  public const HEAD = 'HEAD';
  public const OPTIONS = 'OPTIONS';

  // Content types
  public const CONTENT_JSON = 'application/json';
  public const CONTENT_FORM = 'application/x-www-form-urlencoded';
  public const CONTENT_MULTIPART = 'multipart/form-data';
  public const CONTENT_TEXT = 'text/plain';
  public const CONTENT_XML = 'application/xml';

  // Options
  protected string $_base_url = '';
  protected array $_default_headers = [];
  protected int $_timeout = 30;
  protected bool $_verify_ssl = true;
  protected bool $_follow_redirects = true;
  protected int $_max_redirects = 5;
  protected ?string $_user_agent = null;

  // =========================================================================
  // Constructor & Configuration
  // =========================================================================

  public function __construct(string $base_url = '') {
    $this->_base_url = \rtrim($base_url, '/');
    $this->_user_agent = $this->_buildUserAgent();
  }

  /**
   * Create a new client with a base URL.
   */
  public static function create(string $base_url = ''):self {
    return new self($base_url);
  }

  /**
   * Set base URL.
   */
  public function setBaseUrl(string $url):self {
    $this->_base_url = \rtrim($url, '/');
    return $this;
  }

  /**
   * Set default headers.
   */
  public function setHeaders(array $headers):self {
    $this->_default_headers = $headers;
    return $this;
  }

  /**
   * Add a default header.
   */
  public function addHeader(string $name, string $value):self {
    $this->_default_headers[$name] = $value;
    return $this;
  }

  /**
   * Set timeout in seconds.
   */
  public function setTimeout(int $seconds):self {
    $this->_timeout = $seconds;
    return $this;
  }

  /**
   * Enable/disable SSL verification.
   */
  public function verifySsl(bool $verify):self {
    $this->_verify_ssl = $verify;
    return $this;
  }

  /**
   * Enable/disable following redirects.
   */
  public function followRedirects(bool $follow, int $max = 5):self {
    $this->_follow_redirects = $follow;
    $this->_max_redirects = $max;
    return $this;
  }

  /**
   * Set user agent.
   */
  public function setUserAgent(string $agent):self {
    $this->_user_agent = $agent;
    return $this;
  }

  // =========================================================================
  // HTTP Methods
  // =========================================================================

  /**
   * Make a GET request.
   */
  public function get(string $url, array $params = [], array $headers = []):ClientResponse {
    if (!empty($params)) {
      $url = $this->_appendQuery($url, $params);
    }

    return $this->_request(self::GET, $url, [], $headers);
  }

  /**
   * Make a POST request.
   */
  public function post(string $url, array $data = [], array $headers = []):ClientResponse {
    return $this->_request(self::POST, $url, $data, $headers);
  }

  /**
   * Make a POST request with JSON body.
   */
  public function postJson(string $url, array $data = [], array $headers = []):ClientResponse {
    $headers['Content-Type'] = self::CONTENT_JSON;
    return $this->_request(self::POST, $url, $data, $headers, true);
  }

  /**
   * Make a PUT request.
   */
  public function put(string $url, array $data = [], array $headers = []):ClientResponse {
    return $this->_request(self::PUT, $url, $data, $headers);
  }

  /**
   * Make a PUT request with JSON body.
   */
  public function putJson(string $url, array $data = [], array $headers = []):ClientResponse {
    $headers['Content-Type'] = self::CONTENT_JSON;
    return $this->_request(self::PUT, $url, $data, $headers, true);
  }

  /**
   * Make a PATCH request.
   */
  public function patch(string $url, array $data = [], array $headers = []):ClientResponse {
    return $this->_request(self::PATCH, $url, $data, $headers);
  }

  /**
   * Make a PATCH request with JSON body.
   */
  public function patchJson(string $url, array $data = [], array $headers = []):ClientResponse {
    $headers['Content-Type'] = self::CONTENT_JSON;
    return $this->_request(self::PATCH, $url, $data, $headers, true);
  }

  /**
   * Make a DELETE request.
   */
  public function delete(string $url, array $params = [], array $headers = []):ClientResponse {
    if (!empty($params)) {
      $url = $this->_appendQuery($url, $params);
    }

    return $this->_request(self::DELETE, $url, [], $headers);
  }

  /**
   * Make a HEAD request.
   */
  public function head(string $url, array $headers = []):ClientResponse {
    return $this->_request(self::HEAD, $url, [], $headers);
  }

  /**
   * Make an OPTIONS request.
   */
  public function options(string $url, array $headers = []):ClientResponse {
    return $this->_request(self::OPTIONS, $url, [], $headers);
  }

  /**
   * Make a custom request.
   */
  public function request(
    string $method,
    string $url,
    array $data = [],
    array $headers = [],
    bool $json_body = false
  ):ClientResponse {
    return $this->_request($method, $url, $data, $headers, $json_body);
  }

  // =========================================================================
  // Protected Methods
  // =========================================================================

  protected function _request(
    string $method,
    string $url,
    array $data,
    array $headers,
    bool $json_body = false
  ):ClientResponse {
    // Check cURL availability
    if (!\function_exists('curl_init')) {
      $this->_systemError('request', 'cURL extension is not available.');
      return new ClientResponse(0, '', [], 'cURL not available');
    }

    // Build full URL
    $full_url = $this->_buildUrl($url);

    // Merge headers
    $all_headers = \array_merge($this->_default_headers, $headers);

    // Set default Accept header if not set
    if (!isset($all_headers['Accept'])) {
      $all_headers['Accept'] = self::CONTENT_JSON;
    }

    // Initialize cURL
    $ch = \curl_init();

    \curl_setopt_array($ch, [
      CURLOPT_URL => $full_url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER => true,
      CURLOPT_TIMEOUT => $this->_timeout,
      CURLOPT_SSL_VERIFYPEER => $this->_verify_ssl,
      CURLOPT_SSL_VERIFYHOST => $this->_verify_ssl ? 2 : 0,
      CURLOPT_FOLLOWLOCATION => $this->_follow_redirects,
      CURLOPT_MAXREDIRS => $this->_max_redirects,
      CURLOPT_USERAGENT => $this->_user_agent,
    ]);

    // Set method
    switch (\strtoupper($method)) {
      case self::GET:
        \curl_setopt($ch, CURLOPT_HTTPGET, true);
        break;

      case self::POST:
        \curl_setopt($ch, CURLOPT_POST, true);
        break;

      case self::PUT:
        \curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        break;

      case self::PATCH:
        \curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        break;

      case self::DELETE:
        \curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        break;

      case self::HEAD:
        \curl_setopt($ch, CURLOPT_NOBODY, true);
        break;

      case self::OPTIONS:
        \curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
        break;

      default:
        \curl_setopt($ch, CURLOPT_CUSTOMREQUEST, \strtoupper($method));
        break;
    }

    // Set body for POST/PUT/PATCH
    if (!empty($data) && \in_array(\strtoupper($method), [self::POST, self::PUT, self::PATCH])) {
      if ($json_body) {
        $body = \json_encode($data);
        $all_headers['Content-Type'] = self::CONTENT_JSON;
      } else {
        $body = \http_build_query($data);
      }

      \curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    // Set headers
    $header_lines = [];
    foreach ($all_headers as $name => $value) {
      if (\is_int($name)) {
        $header_lines[] = $value;
      } else {
        $header_lines[] = "{$name}: {$value}";
      }
    }
    \curl_setopt($ch, CURLOPT_HTTPHEADER, $header_lines);

    // Execute request
    $response = \curl_exec($ch);

    // Check for errors
    if (\curl_errno($ch)) {
      $error = \curl_error($ch);
      $this->_systemError('request', $error);
      \curl_close($ch);

      return new ClientResponse(0, '', [], $error);
    }

    // Parse response
    $status_code = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = \curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $response_headers = \substr($response, 0, $header_size);
    $body = \substr($response, $header_size);

    \curl_close($ch);

    // Parse response headers
    $parsed_headers = $this->_parseHeaders($response_headers);

    return new ClientResponse($status_code, $body, $parsed_headers);
  }

  protected function _buildUrl(string $url):string {
    // If URL is already absolute, use as-is
    if (\preg_match('#^https?://#i', $url)) {
      return $url;
    }

    // Prepend base URL
    if (!empty($this->_base_url)) {
      return $this->_base_url . '/' . \ltrim($url, '/');
    }

    return $url;
  }

  protected function _appendQuery(string $url, array $params):string {
    $query = \http_build_query($params);
    $separator = \str_contains($url, '?') ? '&' : '?';

    return $url . $separator . $query;
  }

  protected function _parseHeaders(string $header_string):array {
    $headers = [];
    $lines = \explode("\r\n", $header_string);

    foreach ($lines as $line) {
      if (\str_contains($line, ':')) {
        [$name, $value] = \explode(':', $line, 2);
        $headers[\trim($name)] = \trim($value);
      }
    }

    return $headers;
  }

  protected function _buildUserAgent():string {
    $domain = \defined('PRJ_DOMAIN')
      ? PRJ_DOMAIN
      : ($_SERVER['HTTP_HOST'] ?? 'localhost');

    return "[{$domain}]: TimeFrontiers HTTP Client/" . self::VERSION;
  }
}
