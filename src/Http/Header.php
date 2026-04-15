<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

use TimeFrontiers\Url;

/**
 * HTTP header utilities.
 *
 * Provides methods for sending HTTP status codes, redirects,
 * and common header operations.
 */
class Header {

  // =========================================================================
  // Send Status
  // =========================================================================

  /**
   * Send HTTP status header and exit.
   *
   * @param HttpStatus|int $status Status code or enum
   * @param array $custom_headers Custom X-Tym headers to add
   */
  public static function send(HttpStatus|int $status, array $custom_headers = []):never {
    self::sendCustomHeaders($custom_headers);

    if ($status instanceof HttpStatus) {
      $status->send();
    } else {
      \http_response_code($status);
    }

    exit;
  }

  /**
   * Send HTTP status without exiting.
   *
   * @param HttpStatus|int $status Status code or enum
   * @param array $custom_headers Custom X-Tym headers to add
   */
  public static function sendSoft(HttpStatus|int $status, array $custom_headers = []):void {
    self::sendCustomHeaders($custom_headers);

    if ($status instanceof HttpStatus) {
      $status->send();
    } else {
      \http_response_code($status);
    }
  }

  // =========================================================================
  // Redirects
  // =========================================================================

  /**
   * Redirect to a URL and exit.
   *
   * @param string $url Destination URL
   * @param string $message Optional message (added as query param)
   * @param array $custom_headers Custom X-Tym headers to add
   */
  public static function redirect(string $url, string $message = '', array $custom_headers = []):never {
    if (!empty($message)) {
      $url = Url::withParams($url, ['message' => \urlencode($message)]);
    }

    self::sendCustomHeaders($custom_headers);
    \header("Location: {$url}");
    exit;
  }

  /**
   * Redirect with a timed refresh.
   *
   * @param string $url Destination URL
   * @param int $seconds Delay in seconds
   * @param string $message Message to display while waiting
   */
  public static function refresh(string $url, int $seconds = 10, string $message = ''):void {
    $message = !empty($message) ? $message : "You will be redirected in {$seconds} seconds.";
    \header("Refresh: {$seconds}; url={$url}");
    echo $message;
  }

  // =========================================================================
  // Error Pages
  // =========================================================================

  /**
   * Send 400 Bad Request.
   *
   * @param bool $redirect Redirect to error page instead of just status
   * @param string $message Error message
   * @param array $custom_headers Custom X-Tym headers
   */
  public static function badRequest(bool $redirect = false, string $message = '', array $custom_headers = []):never {
    self::_errorPage(HttpStatus::BAD_REQUEST, $redirect, $message, $custom_headers);
  }

  /**
   * Send 401 Unauthorized.
   *
   * @param bool $redirect Redirect to error page instead of just status
   * @param string $message Error message
   * @param array $custom_headers Custom X-Tym headers
   */
  public static function unauthorized(bool $redirect = false, string $message = '', array $custom_headers = []):never {
    self::_errorPage(HttpStatus::UNAUTHORIZED, $redirect, $message, $custom_headers);
  }

  /**
   * Send 403 Forbidden.
   *
   * @param bool $redirect Redirect to error page instead of just status
   * @param string $message Error message
   * @param array $custom_headers Custom X-Tym headers
   */
  public static function forbidden(bool $redirect = false, string $message = '', array $custom_headers = []):never {
    self::_errorPage(HttpStatus::FORBIDDEN, $redirect, $message, $custom_headers);
  }

  /**
   * Send 404 Not Found.
   *
   * @param bool $redirect Redirect to error page instead of just status
   * @param string $message Error message
   * @param array $custom_headers Custom X-Tym headers
   */
  public static function notFound(bool $redirect = false, string $message = '', array $custom_headers = []):never {
    self::_errorPage(HttpStatus::NOT_FOUND, $redirect, $message, $custom_headers);
  }

  /**
   * Send 500 Internal Server Error.
   *
   * @param bool $redirect Redirect to error page instead of just status
   * @param string $message Error message
   * @param array $custom_headers Custom X-Tym headers
   */
  public static function internalError(bool $redirect = false, string $message = '', array $custom_headers = []):never {
    self::_errorPage(HttpStatus::INTERNAL_SERVER_ERROR, $redirect, $message, $custom_headers);
  }

  // =========================================================================
  // Authentication
  // =========================================================================

  /**
   * Send HTTP Basic Authentication dialog.
   *
   * @param string $realm Authentication realm name
   * @param string $message Message if authentication fails
   */
  public static function authDialog(string $realm = 'Restricted Area', string $message = 'Authentication required.'):never {
    \header('HTTP/1.1 401 Unauthorized');
    \header("WWW-Authenticate: Basic realm=\"{$realm}\"");
    echo $message;
    exit;
  }

  // =========================================================================
  // Caching
  // =========================================================================

  /**
   * Send no-cache headers.
   */
  public static function noCache():void {
    \header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
    \header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    \header('Pragma: no-cache');
  }

  /**
   * Set cache expiration.
   *
   * @param int $seconds Seconds until expiration
   * @param bool $public Public cache (default: private)
   */
  public static function cache(int $seconds, bool $public = false):void {
    $type = $public ? 'public' : 'private';
    \header("Cache-Control: {$type}, max-age={$seconds}");
    \header('Expires: ' . \gmdate('D, d M Y H:i:s', \time() + $seconds) . ' GMT');
  }

  // =========================================================================
  // Content Headers
  // =========================================================================

  /**
   * Set Content-Type header.
   */
  public static function contentType(string $type, string $charset = 'utf-8'):void {
    if (!empty($charset)) {
      \header("Content-Type: {$type}; charset={$charset}");
    } else {
      \header("Content-Type: {$type}");
    }
  }

  /**
   * Set Content-Length header.
   */
  public static function contentLength(int $length):void {
    \header("Content-Length: {$length}");
  }

  /**
   * Set Content-Language header.
   */
  public static function language(string $lang):void {
    \header("Content-language: {$lang}");
  }

  /**
   * Set Content-Disposition for file downloads.
   *
   * @param string $filename Download filename
   * @param bool $inline Display inline instead of download
   */
  public static function download(string $filename, bool $inline = false):void {
    $disposition = $inline ? 'inline' : 'attachment';
    \header("Content-Disposition: {$disposition}; filename=\"{$filename}\"");
  }

  // =========================================================================
  // Custom Headers
  // =========================================================================

  /**
   * Set X-Powered-By header.
   */
  public static function poweredBy(string $by):void {
    \header("X-Powered-By: {$by}");
  }

  /**
   * Set a custom header.
   */
  public static function set(string $name, string $value):void {
    \header("{$name}: {$value}");
  }

  /**
   * Set a custom X-Tym header.
   */
  public static function setCustom(string $name, string $value):void {
    \header("X-Tym-{$name}: {$value}");
  }

  /**
   * Send multiple custom X-Tym headers.
   */
  public static function sendCustomHeaders(array $headers):void {
    foreach ($headers as $name => $value) {
      if (!\is_int($name)) {
        \header("X-Tym-{$name}: {$value}");
      }
    }
  }

  /**
   * Get custom X-Tym headers from response.
   *
   * @param string $find_key Specific key to find (optional)
   * @return array|string|null Headers array, specific value, or null
   */
  public static function getCustom(string $find_key = ''):array|string|null {
    if (!\function_exists('apache_response_headers')) {
      return empty($find_key) ? [] : null;
    }

    $prefix = 'X-Tym-';
    $out = [];
    $headers = \apache_response_headers();

    foreach ($headers as $key => $val) {
      if (\str_starts_with($key, $prefix)) {
        $clean_key = \substr($key, \strlen($prefix));
        $out[$clean_key] = $val;
      }
    }

    if (!empty($find_key)) {
      return $out[$find_key] ?? null;
    }

    return $out;
  }

  // =========================================================================
  // Security Headers
  // =========================================================================

  /**
   * Set common security headers.
   */
  public static function security():void {
    \header('X-Content-Type-Options: nosniff');
    \header('X-Frame-Options: SAMEORIGIN');
    \header('X-XSS-Protection: 1; mode=block');
    \header('Referrer-Policy: strict-origin-when-cross-origin');
  }

  /**
   * Set Content-Security-Policy header.
   *
   * @param array $directives CSP directives [directive => value]
   */
  public static function csp(array $directives):void {
    $parts = [];
    foreach ($directives as $directive => $value) {
      $parts[] = "{$directive} {$value}";
    }

    \header('Content-Security-Policy: ' . \implode('; ', $parts));
  }

  /**
   * Set Strict-Transport-Security header.
   *
   * @param int $max_age Max age in seconds
   * @param bool $include_subdomains Include subdomains
   */
  public static function hsts(int $max_age = 31536000, bool $include_subdomains = true):void {
    $value = "max-age={$max_age}";
    if ($include_subdomains) {
      $value .= '; includeSubDomains';
    }

    \header("Strict-Transport-Security: {$value}");
  }

  // =========================================================================
  // Utility
  // =========================================================================

  /**
   * Get status message for a code.
   *
   * @param int $code HTTP status code
   * @return string|null Status message (e.g., "200 OK") or null
   */
  public static function message(int $code):?string {
    $status = HttpStatus::fromCode($code);
    return $status?->line();
  }

  // =========================================================================
  // Private Methods
  // =========================================================================

  /**
   * Handle error page response.
   */
  private static function _errorPage(
    HttpStatus $status,
    bool $redirect,
    string $message,
    array $custom_headers
  ):never {
    if ($redirect) {
      $scheme = \defined('REQUEST_SCHEME') ? REQUEST_SCHEME : 'http://';
      $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
      $url = "{$scheme}{$host}/app/{$status->value}";

      $query = [];
      if (!empty($_SERVER['REQUEST_URI'])) {
        $query['request'] = $_SERVER['REQUEST_URI'];
      }
      if (!empty($message)) {
        $query['message'] = $message;
      }
      if (!empty($query)) {
        $url = Url::withParams($url, $query);
      }

      self::redirect($url, '', $custom_headers);
    }

    self::send($status, $custom_headers);
  }
}
