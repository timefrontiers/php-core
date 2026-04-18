<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

/**
 * HTTP utility functions.
 */
class Http {

  /**
   * Redirect to a URL and exit.
   */
  public static function redirect(string $location, HttpStatus $status = HttpStatus::FOUND):never {
    $status->send();
    \header("Location: {$location}");
    exit;
  }

  /**
   * Redirect to a URL without exiting (for testing).
   */
  public static function redirectSoft(string $location, HttpStatus $status = HttpStatus::FOUND):void {
    $status->send();
    \header("Location: {$location}");
  }

  /**
   * Send a JSON response.
   */
  public static function json(
    mixed $data,
    HttpStatus $status = HttpStatus::OK,
    int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
  ):never {
    $status->send();
    \header('Content-Type: application/json; charset=utf-8');
    echo \json_encode($data, $flags);
    exit;
  }

  /**
   * Send a success JSON response.
   */
  public static function success(mixed $data = null, string $message = 'Success'):never {
    self::json([
      'success' => true,
      'message' => $message,
      'data' => $data,
    ]);
  }

  /**
   * Send an error JSON response.
   */
  public static function error(
    string $message,
    HttpStatus $status = HttpStatus::BAD_REQUEST,
    array $errors = []
  ):never {
    self::json([
      'success' => false,
      'message' => $message,
      'errors' => $errors,
    ], $status);
  }

  /**
   * Send a JSONP response (callback-wrapped JSON). Exits after sending.
   *
   * @throws \InvalidArgumentException When the callback name is unsafe.
   */
  public static function jsonp(
    mixed $data,
    string $callback,
    HttpStatus $status = HttpStatus::OK,
    int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
  ):never {
    if (!\preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $callback)) {
      throw new \InvalidArgumentException('Invalid JSONP callback name');
    }

    $status->send();
    \header('Content-Type: application/javascript; charset=utf-8');
    echo $callback . '(' . \json_encode($data, $flags) . ');';
    exit;
  }

  /**
   * Build a standardized response array without sending it. Useful when you
   * want to return a body from a controller, log it, or pass it through
   * middleware before emitting.
   *
   * @return array{success: bool, message: string, data?: mixed, errors?: array, meta?: array}
   */
  public static function buildResponse(
    bool $success,
    string $message = '',
    mixed $data = null,
    array $errors = [],
    array $meta = []
  ):array {
    $response = [
      'success' => $success,
      'message' => $message,
    ];

    if ($data !== null) {
      $response['data'] = $data;
    }

    if (!empty($errors)) {
      $response['errors'] = $errors;
    }

    if (!empty($meta)) {
      $response['meta'] = $meta;
    }

    return $response;
  }

  /**
   * Get the client's IP address.
   */
  public static function clientIp():string {
    $headers = [
      'HTTP_CF_CONNECTING_IP',     // Cloudflare
      'HTTP_X_FORWARDED_FOR',      // Proxy
      'HTTP_X_REAL_IP',            // Nginx
      'HTTP_CLIENT_IP',            // Shared internet
      'REMOTE_ADDR',               // Standard
    ];

    foreach ($headers as $header) {
      if (!empty($_SERVER[$header])) {
        $ips = \explode(',', $_SERVER[$header]);
        $ip = \trim($ips[0]);

        if (\filter_var($ip, FILTER_VALIDATE_IP)) {
          return $ip;
        }
      }
    }

    return '0.0.0.0';
  }

  /**
   * Get the request method.
   */
  public static function method():string {
    return \strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
  }

  /**
   * Check if request is a specific method.
   */
  public static function isMethod(string $method):bool {
    return self::method() === \strtoupper($method);
  }

  /**
   * Check if request is AJAX/XHR.
   */
  public static function isAjax():bool {
    return (\strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest')
      || (self::accepts('application/json'));
  }

  /**
   * Check if request accepts a content type.
   */
  public static function accepts(string $content_type):bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return \str_contains($accept, $content_type);
  }

  /**
   * Check if request is secure (HTTPS).
   */
  public static function isSecure():bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
      return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
      return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
      return true;
    }

    return ($_SERVER['SERVER_PORT'] ?? 80) == 443;
  }

  /**
   * Get the full current URL.
   */
  public static function currentUrl():string {
    $scheme = self::isSecure() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    return "{$scheme}://{$host}{$uri}";
  }

  /**
   * Get request header.
   */
  public static function header(string $name):?string {
    // Try apache_request_headers first
    if (\function_exists('apache_request_headers')) {
      $headers = \apache_request_headers();
      if (isset($headers[$name])) {
        return $headers[$name];
      }
      // Try case-insensitive
      foreach ($headers as $key => $value) {
        if (\strtolower($key) === \strtolower($name)) {
          return $value;
        }
      }
    }

    // Fallback to $_SERVER
    $server_key = 'HTTP_' . \strtoupper(\str_replace('-', '_', $name));
    return $_SERVER[$server_key] ?? null;
  }

  /**
   * Get all request headers.
   */
  public static function headers():array {
    if (\function_exists('apache_request_headers')) {
      return \apache_request_headers();
    }

    $headers = [];
    foreach ($_SERVER as $key => $value) {
      if (\str_starts_with($key, 'HTTP_')) {
        $name = \str_replace('_', '-', \substr($key, 5));
        $name = \ucwords(\strtolower($name), '-');
        $headers[$name] = $value;
      }
    }

    return $headers;
  }

  /**
   * Set response header.
   */
  public static function setHeader(string $name, string $value):void {
    \header("{$name}: {$value}");
  }

  /**
   * Set CORS headers.
   */
  public static function cors(
    string $origin = '*',
    array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    array $headers = ['Content-Type', 'Authorization'],
    int $max_age = 86400
  ):void {
    self::setHeader('Access-Control-Allow-Origin', $origin);
    self::setHeader('Access-Control-Allow-Methods', \implode(', ', $methods));
    self::setHeader('Access-Control-Allow-Headers', \implode(', ', $headers));
    self::setHeader('Access-Control-Max-Age', (string) $max_age);

    // Handle preflight
    if (self::isMethod('OPTIONS')) {
      HttpStatus::NO_CONTENT->send();
      exit;
    }
  }

  /**
   * Set no-cache headers.
   */
  public static function noCache():void {
    self::setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    self::setHeader('Pragma', 'no-cache');
    self::setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
  }
}
