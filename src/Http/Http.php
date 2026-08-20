<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

/** HTTP response and inbound request utilities. */
class Http {

  public static function redirect(string $location, HttpStatus $status = HttpStatus::FOUND):never {
    self::assertRedirectLocation($location);
    $status->send();
    \header("Location: {$location}");
    exit;
  }

  public static function redirectSoft(string $location, HttpStatus $status = HttpStatus::FOUND):void {
    self::assertRedirectLocation($location);
    $status->send();
    \header("Location: {$location}");
  }

  public static function json(
    mixed $data,
    HttpStatus $status = HttpStatus::OK,
    int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
  ):never {
    $body = \json_encode($data, $flags | JSON_THROW_ON_ERROR);
    $status->send();
    \header('Content-Type: application/json; charset=utf-8');
    echo $body;
    exit;
  }

  public static function success(mixed $data = null, string $message = 'Success'):never {
    self::json(['success' => true, 'message' => $message, 'data' => $data]);
  }

  /** @param array<array-key, mixed> $errors */
  public static function error(
    string $message,
    HttpStatus $status = HttpStatus::BAD_REQUEST,
    array $errors = []
  ):never {
    self::json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
  }

  /** @deprecated Prefer JSON with CORS; JSONP remains only for 1.x compatibility. */
  public static function jsonp(
    mixed $data,
    string $callback,
    HttpStatus $status = HttpStatus::OK,
    int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
  ):never {
    if (!\preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$]*$/D', $callback)) {
      throw new \InvalidArgumentException('Invalid JSONP callback name.');
    }
    $body = \json_encode($data, $flags | JSON_THROW_ON_ERROR);
    $status->send();
    \header('Content-Type: application/javascript; charset=utf-8');
    echo $callback . '(' . $body . ');';
    exit;
  }

  /**
   * @param array<array-key, mixed> $errors
   * @param array<array-key, mixed> $meta
   * @return array{success: bool, message: string, data?: mixed, errors?: array<array-key, mixed>, meta?: array<array-key, mixed>}
   */
  public static function buildResponse(
    bool $success,
    string $message = '',
    mixed $data = null,
    array $errors = [],
    array $meta = []
  ):array {
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) $response['data'] = $data;
    if ($errors !== []) $response['errors'] = $errors;
    if ($meta !== []) $response['meta'] = $meta;
    return $response;
  }

  /**
   * Resolve a client address. Forwarding data is ignored unless an immutable
   * trusted-proxy policy is supplied and trusts the immediate peer.
   *
   * @param array<string, mixed>|null $server
   */
  public static function clientIp(?TrustedProxyConfig $proxies = null, ?array $server = null):string {
    $server ??= $_SERVER;
    if ($proxies !== null) {
      return $proxies->clientIp($server);
    }
    $peer = $server['REMOTE_ADDR'] ?? null;
    return \is_string($peer) && \filter_var($peer, FILTER_VALIDATE_IP) !== false
      ? $peer
      : '0.0.0.0';
  }

  public static function method():string {
    return \strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
  }

  public static function isMethod(string $method):bool {
    return self::method() === \strtoupper($method);
  }

  public static function isAjax():bool {
    return \strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
      || self::accepts('application/json');
  }

  public static function accepts(string $content_type):bool {
    return \str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), $content_type);
  }

  /** @param array<string, mixed>|null $server */
  public static function isSecure(?TrustedProxyConfig $proxies = null, ?array $server = null):bool {
    $server ??= $_SERVER;
    return $proxies?->isSecure($server) ?? self::isDirectlySecure($server);
  }

  /** @param array<string, mixed> $server */
  public static function isDirectlySecure(array $server):bool {
    $https = $server['HTTPS'] ?? null;
    if (\is_string($https) && $https !== '' && \strtolower($https) !== 'off') {
      return true;
    }
    return (string) ($server['SERVER_PORT'] ?? '') === '443';
  }

  /**
   * Build the current URL from a canonical origin policy. Without one, the
   * server-configured SERVER_NAME is used and HTTP_HOST is ignored.
   *
   * @param array<string, mixed>|null $server
   */
  public static function currentUrl(
    ?OriginPolicy $originPolicy = null,
    ?TrustedProxyConfig $proxies = null,
    ?array $server = null
  ):string {
    $server ??= $_SERVER;
    $secure = self::isSecure($proxies, $server);
    if ($originPolicy !== null) {
      $origin = $originPolicy->origin($server, $secure);
    } else {
      $serverName = $server['SERVER_NAME'] ?? 'localhost';
      if (!\is_string($serverName) || !\preg_match('/^[a-z0-9.:-]+$/iD', $serverName)) {
        $serverName = 'localhost';
      }
      $origin = ($secure ? 'https' : 'http') . '://' . $serverName;
    }

    $uri = $server['REQUEST_URI'] ?? '/';
    if (!\is_string($uri) || !\str_starts_with($uri, '/') || \preg_match('/[\r\n]/', $uri)) {
      $uri = '/';
    }
    return $origin . $uri;
  }

  public static function header(string $name):?string {
    self::assertHeaderName($name);
    if (\function_exists('apache_request_headers')) {
      $headers = \apache_request_headers();
      foreach ($headers as $key => $value) {
        if (\strcasecmp((string) $key, $name) === 0 && \is_string($value) && !\preg_match('/[\r\n]/', $value)) {
          return $value;
        }
      }
    }

    $key = 'HTTP_' . \strtoupper(\str_replace('-', '_', $name));
    $value = $_SERVER[$key] ?? null;
    return \is_string($value) && !\preg_match('/[\r\n]/', $value) ? $value : null;
  }

  /** @return array<string, string> */
  public static function headers():array {
    $source = \function_exists('apache_request_headers') ? \apache_request_headers() : $_SERVER;
    $headers = [];
    foreach ($source as $key => $value) {
      if (!\is_string($value) || \preg_match('/[\r\n]/', $value)) continue;
      if (\function_exists('apache_request_headers')) {
        $name = (string) $key;
      } elseif (\str_starts_with((string) $key, 'HTTP_')) {
        $name = \ucwords(\strtolower(\str_replace('_', '-', \substr((string) $key, 5))), '-');
      } else {
        continue;
      }
      if (\preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/D", $name)) {
        $headers[$name] = $value;
      }
    }
    return $headers;
  }

  public static function setHeader(string $name, string $value):void {
    self::assertHeaderName($name);
    self::assertHeaderValue($value);
    \header("{$name}: {$value}");
  }

  /**
   * @param list<string> $methods
   * @param list<string> $headers
   */
  public static function cors(
    string $origin = '*',
    array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    array $headers = ['Content-Type', 'Authorization'],
    int $max_age = 86400
  ):void {
    if ($max_age < 0) throw new \InvalidArgumentException('CORS max age cannot be negative.');
    self::setHeader('Access-Control-Allow-Origin', $origin);
    self::setHeader('Access-Control-Allow-Methods', \implode(', ', $methods));
    self::setHeader('Access-Control-Allow-Headers', \implode(', ', $headers));
    self::setHeader('Access-Control-Max-Age', (string) $max_age);
    if (self::isMethod('OPTIONS')) {
      HttpStatus::NO_CONTENT->send();
      exit;
    }
  }

  public static function noCache():void {
    self::setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    self::setHeader('Pragma', 'no-cache');
    self::setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
  }

  public static function assertRedirectLocation(string $location):void {
    self::assertHeaderValue($location);
    if (
      $location === ''
      || \preg_match('/[\\\\\x00-\x20\x7f]/', $location)
      || \str_starts_with($location, '//')
    ) {
      throw new \InvalidArgumentException('Redirect location is invalid.');
    }
    $parts = \parse_url($location);
    if ($parts === false) {
      throw new \InvalidArgumentException('Redirect location is malformed.');
    }
    $scheme = $parts['scheme'] ?? null;
    if ($scheme !== null && !\in_array(\strtolower((string) $scheme), ['http', 'https'], true)) {
      throw new \InvalidArgumentException('Redirects support only relative or HTTP(S) locations.');
    }
    if ($scheme !== null && (!isset($parts['host']) || isset($parts['user']) || isset($parts['pass']))) {
      throw new \InvalidArgumentException('Absolute redirect locations require a safe authority.');
    }
  }

  public static function assertHeaderName(string $name):void {
    if (!\preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/D", $name)) {
      throw new \InvalidArgumentException('HTTP header name is invalid.');
    }
  }

  public static function assertHeaderValue(string $value):void {
    if (\preg_match('/[\r\n\x00]/', $value)) {
      throw new \InvalidArgumentException('HTTP header value contains control characters.');
    }
  }
}
