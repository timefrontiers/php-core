<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

use TimeFrontiers\Helper\HasErrors;

/**
 * Redirect-aware, bounded HTTP client with safe network policy by default.
 *
 * @phpstan-type HeaderInput array<array-key, string|int|float>
 * @phpstan-type HeaderMap array<string, string>
 * @phpstan-type Payload array<array-key, mixed>
 */
class Client {

  use HasErrors;

  public const VERSION = '1.1';
  public const GET = 'GET';
  public const POST = 'POST';
  public const PUT = 'PUT';
  public const PATCH = 'PATCH';
  public const DELETE = 'DELETE';
  public const HEAD = 'HEAD';
  public const OPTIONS = 'OPTIONS';

  public const CONTENT_JSON = 'application/json';
  public const CONTENT_FORM = 'application/x-www-form-urlencoded';
  public const CONTENT_MULTIPART = 'multipart/form-data';
  public const CONTENT_TEXT = 'text/plain';
  public const CONTENT_XML = 'application/xml';

  /** @var list<string> */
  private const FORBIDDEN_CROSS_ORIGIN_HEADERS = [
    'api-key',
    'authorization',
    'cookie',
    'cookie2',
    'host',
    'proxy-authorization',
    'x-amz-security-token',
    'x-api-key',
    'x-auth-token',
    'x-client-secret',
  ];

  protected string $_base_url = '';
  /** @var array<array-key, mixed> */
  protected array $_default_headers = [];
  protected int $_connect_timeout_ms = 5000;
  protected int $_timeout_ms = 30000;
  protected bool $_verify_ssl = true;
  protected bool $_follow_redirects = true;
  protected int $_max_redirects = 5;
  protected int $_max_header_bytes = 65536;
  protected int $_max_body_bytes = 2097152;
  protected bool $_allow_non_idempotent_redirects = false;
  /** @var array<string, true> */
  protected array $_cross_origin_header_allowlist = [];
  /** @var non-empty-string */
  protected string $_user_agent;
  protected UrlPolicy $_url_policy;
  protected Transport $_transport;

  public function __construct(string $base_url = '') {
    $this->_base_url = \rtrim($base_url, '/');
    $this->_user_agent = 'TimeFrontiers-Core/' . self::VERSION;
    $this->_url_policy = new UrlPolicy();
    $this->_transport = new CurlTransport();
  }

  public static function create(string $base_url = ''):self {
    return new self($base_url);
  }

  public function setBaseUrl(string $url):self {
    $this->_base_url = \rtrim($url, '/');
    return $this;
  }

  /** @param array<array-key, mixed> $headers */
  public function setHeaders(array $headers):self {
    $this->_default_headers = $headers;
    return $this;
  }

  public function addHeader(string $name, string $value):self {
    self::_assertHeader($name, $value);
    $this->_default_headers[$name] = $value;
    return $this;
  }

  public function setTimeout(int $seconds):self {
    if ($seconds < 1) {
      throw new \InvalidArgumentException('The total timeout must be positive.');
    }
    $this->_timeout_ms = $seconds * 1000;
    return $this;
  }

  public function setConnectTimeout(int $seconds):self {
    if ($seconds < 1) {
      throw new \InvalidArgumentException('The connection timeout must be positive.');
    }
    $this->_connect_timeout_ms = $seconds * 1000;
    return $this;
  }

  public function setResponseLimits(int $maxHeaderBytes, int $maxBodyBytes):self {
    if ($maxHeaderBytes < 1024 || $maxBodyBytes < 1) {
      throw new \InvalidArgumentException('HTTP response limits are invalid.');
    }
    $this->_max_header_bytes = $maxHeaderBytes;
    $this->_max_body_bytes = $maxBodyBytes;
    return $this;
  }

  public function verifySsl(bool $verify):self {
    $this->_verify_ssl = $verify;
    return $this;
  }

  public function followRedirects(bool $follow, int $max = 5):self {
    if ($max < 0 || $max > 20) {
      throw new \InvalidArgumentException('Redirect limits must be between 0 and 20.');
    }
    $this->_follow_redirects = $follow;
    $this->_max_redirects = $max;
    return $this;
  }

  public function allowNonIdempotentRedirects(bool $allow = true):self {
    $this->_allow_non_idempotent_redirects = $allow;
    return $this;
  }

  /**
   * Explicitly allow caller-owned nonstandard headers across origins.
   *
   * Standard content-negotiation headers are retained automatically. All
   * other headers, including credentials and caller-defined fields, are
   * removed unless their names are opted in here.
   *
   * @param list<string> $names
   */
  public function allowCrossOriginHeaders(array $names):self {
    foreach ($names as $name) {
      self::_assertHeader($name, '');
      $normalized = \strtolower(\trim($name));
      if (\in_array($normalized, self::FORBIDDEN_CROSS_ORIGIN_HEADERS, true)) {
        throw new \InvalidArgumentException('Credential and authority headers cannot be allowed across origins.');
      }
      $this->_cross_origin_header_allowlist[$normalized] = true;
    }
    return $this;
  }

  public function setUserAgent(string $agent):self {
    if ($agent === '') {
      throw new \InvalidArgumentException('HTTP user agent cannot be empty.');
    }
    self::_assertHeaderValue($agent);
    $this->_user_agent = $agent;
    return $this;
  }

  public function setUrlPolicy(UrlPolicy $policy):self {
    $this->_url_policy = $policy;
    return $this;
  }

  /** Low-level transport injection for testing or host-owned integrations. */
  public function setTransport(Transport $transport):self {
    $this->_transport = $transport;
    return $this;
  }

  /**
   * @param Payload $params
   * @param HeaderInput $headers
   */
  public function get(string $url, array $params = [], array $headers = []):ClientResponse {
    if ($params !== []) {
      $url = $this->_appendQuery($url, $params);
    }
    return $this->_request(self::GET, $url, [], $headers);
  }

  /**
   * @param Payload $data
   * @param HeaderInput $headers
   */
  public function post(string $url, array $data = [], array $headers = []):ClientResponse {
    return $this->_request(self::POST, $url, $data, $headers);
  }

  /**
   * @param Payload $data
   * @param HeaderInput $headers
   */
  public function postJson(string $url, array $data = [], array $headers = []):ClientResponse {
    $headers['Content-Type'] = self::CONTENT_JSON;
    return $this->_request(self::POST, $url, $data, $headers, true);
  }

  /**
   * @param Payload $data
   * @param HeaderInput $headers
   */
  public function put(string $url, array $data = [], array $headers = []):ClientResponse {
    return $this->_request(self::PUT, $url, $data, $headers);
  }

  /**
   * @param Payload $data
   * @param HeaderInput $headers
   */
  public function putJson(string $url, array $data = [], array $headers = []):ClientResponse {
    $headers['Content-Type'] = self::CONTENT_JSON;
    return $this->_request(self::PUT, $url, $data, $headers, true);
  }

  /**
   * @param Payload $data
   * @param HeaderInput $headers
   */
  public function patch(string $url, array $data = [], array $headers = []):ClientResponse {
    return $this->_request(self::PATCH, $url, $data, $headers);
  }

  /**
   * @param Payload $data
   * @param HeaderInput $headers
   */
  public function patchJson(string $url, array $data = [], array $headers = []):ClientResponse {
    $headers['Content-Type'] = self::CONTENT_JSON;
    return $this->_request(self::PATCH, $url, $data, $headers, true);
  }

  /**
   * @param Payload $params
   * @param HeaderInput $headers
   */
  public function delete(string $url, array $params = [], array $headers = []):ClientResponse {
    if ($params !== []) {
      $url = $this->_appendQuery($url, $params);
    }
    return $this->_request(self::DELETE, $url, [], $headers);
  }

  /** @param HeaderInput $headers */
  public function head(string $url, array $headers = []):ClientResponse {
    return $this->_request(self::HEAD, $url, [], $headers);
  }

  /** @param HeaderInput $headers */
  public function options(string $url, array $headers = []):ClientResponse {
    return $this->_request(self::OPTIONS, $url, [], $headers);
  }

  /**
   * @param Payload $data
   * @param HeaderInput $headers
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

  /**
   * @param Payload $data
   * @param HeaderInput $headers
   */
  protected function _request(
    string $method,
    string $url,
    array $data,
    array $headers,
    bool $json_body = false
  ):ClientResponse {
    $method = \strtoupper(\trim($method));
    if (!\preg_match('/^[A-Z]+$/D', $method)) {
      throw new \InvalidArgumentException('HTTP method is invalid.');
    }

    $currentUrl = $this->_buildUrl($url);
    $allHeaders = self::_mergeHeaders(
      self::_normalizeHeaders($this->_default_headers),
      self::_normalizeHeaders($headers)
    );
    if (!self::_hasHeader($allHeaders, 'Accept')) {
      $allHeaders['Accept'] = self::CONTENT_JSON;
    }

    $body = null;
    if ($data !== [] && !\in_array($method, [self::GET, self::HEAD], true)) {
      if ($json_body) {
        $body = \json_encode($data, JSON_THROW_ON_ERROR);
        $allHeaders = self::_setHeader($allHeaders, 'Content-Type', self::CONTENT_JSON);
      } else {
        $body = \http_build_query($data);
        if (!self::_hasHeader($allHeaders, 'Content-Type')) {
          $allHeaders['Content-Type'] = self::CONTENT_FORM;
        }
      }
    }
    if ($body === '') {
      $body = null;
    }

    $deadlineNs = $this->_monotonicNowNs() + ($this->_timeout_ms * 1000000);

    for ($redirects = 0; ; $redirects++) {
      if ($this->_remainingTimeoutMs($deadlineNs) === 0) {
        return $this->_timeoutResponse();
      }

      try {
        $destination = $this->_url_policy->resolve($currentUrl);
      } catch (\Throwable $error) {
        $this->_systemError('request', 'Outbound URL policy rejected a request: ' . $error::class . ': ' . $error->getMessage());
        return new ClientResponse(0, '', [], 'HTTP request blocked by URL policy.', [
          'policy_exception' => $error::class,
        ]);
      }

      $remainingTimeoutMs = $this->_remainingTimeoutMs($deadlineNs);
      if ($remainingTimeoutMs === 0) {
        return $this->_timeoutResponse();
      }

      $transportResponse = $this->_transport->send(new TransportRequest(
        $method,
        $destination,
        $allHeaders,
        $body,
        \min($this->_connect_timeout_ms, $remainingTimeoutMs),
        $remainingTimeoutMs,
        $this->_max_header_bytes,
        $this->_max_body_bytes,
        $this->_verify_ssl,
        $this->_user_agent
      ));

      if ($this->_remainingTimeoutMs($deadlineNs) === 0) {
        return $this->_timeoutResponse();
      }

      if ($transportResponse->error !== null) {
        $context = $transportResponse->diagnostic;
        $this->_systemError('request', $transportResponse->error . ' ' . \json_encode($context, JSON_THROW_ON_ERROR));
        return $this->_clientResponse($transportResponse);
      }

      $location = self::_header($transportResponse->headers, 'Location');
      if (
        !$this->_follow_redirects
        || $location === null
        || $transportResponse->statusCode < 300
        || $transportResponse->statusCode >= 400
      ) {
        return $this->_clientResponse($transportResponse);
      }

      if ($redirects >= $this->_max_redirects) {
        $this->_systemError('request', 'The outbound redirect limit was exceeded.');
        return new ClientResponse(
          $transportResponse->statusCode,
          $transportResponse->body,
          $transportResponse->headers,
          'HTTP redirect limit exceeded.',
          $transportResponse->diagnostic
        );
      }

      try {
        $nextUrl = self::_resolveRedirect($currentUrl, $location);
      } catch (\InvalidArgumentException $error) {
        $this->_systemError('request', 'An invalid redirect target was rejected: ' . $error->getMessage());
        return new ClientResponse(
          $transportResponse->statusCode,
          $transportResponse->body,
          $transportResponse->headers,
          'HTTP redirect was blocked.',
          $transportResponse->diagnostic
        );
      }

      if (!self::_sameOrigin($currentUrl, $nextUrl)) {
        $allHeaders = $this->_crossOriginHeaders($allHeaders);
      }

      $status = $transportResponse->statusCode;
      if ($status === 303 || (($status === 301 || $status === 302) && $method === self::POST)) {
        $method = self::GET;
        $body = null;
        $allHeaders = self::_withoutHeaders($allHeaders, ['Content-Type', 'Content-Length']);
      } elseif (
        $body !== null
        && !\in_array($method, [self::GET, self::HEAD, self::OPTIONS, self::PUT, self::DELETE], true)
        && !$this->_allow_non_idempotent_redirects
      ) {
        $this->_systemError('request', 'A non-idempotent request body was not replayed across a redirect.');
        return new ClientResponse(
          $status,
          $transportResponse->body,
          $transportResponse->headers,
          'HTTP redirect requires explicit replay permission.',
          $transportResponse->diagnostic
        );
      }

      $currentUrl = $nextUrl;
    }
  }

  protected function _buildUrl(string $url):string {
    if (\preg_match('#^https?://#i', $url)) {
      return $url;
    }
    if ($this->_base_url !== '') {
      return $this->_base_url . '/' . \ltrim($url, '/');
    }
    return $url;
  }

  /** @param Payload $params */
  protected function _appendQuery(string $url, array $params):string {
    $query = \http_build_query($params);
    return $url . (\str_contains($url, '?') ? '&' : '?') . $query;
  }

  private function _clientResponse(TransportResponse $response):ClientResponse {
    return new ClientResponse(
      $response->statusCode,
      $response->body,
      $response->headers,
      $response->error,
      $response->diagnostic
    );
  }

  /**
   * Monotonic clock seam for deterministic deadline tests.
   *
   * @phpstan-impure
   */
  protected function _monotonicNowNs():int {
    return (int) \hrtime(true);
  }

  /** @phpstan-impure */
  private function _remainingTimeoutMs(int $deadlineNs):int {
    $remainingNs = $deadlineNs - $this->_monotonicNowNs();
    return $remainingNs <= 0 ? 0 : (int) \ceil($remainingNs / 1000000);
  }

  private function _timeoutResponse():ClientResponse {
    $this->_systemError('request', 'The outbound HTTP total timeout was exceeded.');
    return new ClientResponse(0, '', [], 'HTTP request exceeded the total timeout.', [
      'timeout_ms' => $this->_timeout_ms,
    ]);
  }

  /**
   * @param HeaderMap $headers
   * @return HeaderMap
   */
  private function _crossOriginHeaders(array $headers):array {
    $safe = [
      'accept' => true,
      'accept-charset' => true,
      'accept-encoding' => true,
      'accept-language' => true,
      'cache-control' => true,
      'content-language' => true,
      'content-type' => true,
      'expires' => true,
      'if-modified-since' => true,
      'if-none-match' => true,
      'pragma' => true,
      'range' => true,
      ...$this->_cross_origin_header_allowlist,
    ];

    foreach (\array_keys($headers) as $name) {
      if (!isset($safe[\strtolower($name)])) {
        unset($headers[$name]);
      }
    }
    return $headers;
  }

  /**
   * @param array<array-key, mixed> $headers
   * @return HeaderMap
   */
  private static function _normalizeHeaders(array $headers):array {
    $normalized = [];
    foreach ($headers as $name => $value) {
      if (\is_int($name)) {
        if (!\is_string($value) || !\str_contains($value, ':')) {
          throw new \InvalidArgumentException('Numeric HTTP headers must be complete field lines.');
        }
        [$name, $value] = \explode(':', $value, 2);
      }
      if (!\is_string($value) && !\is_numeric($value)) {
        throw new \InvalidArgumentException('HTTP headers must contain string names and scalar values.');
      }
      $value = (string) $value;
      self::_assertHeader($name, $value);
      $normalized[\trim($name)] = \trim($value);
    }
    return $normalized;
  }

  /**
   * @param HeaderMap $base
   * @param HeaderMap $new
   * @return HeaderMap
   */
  private static function _mergeHeaders(array $base, array $new):array {
    foreach ($new as $name => $value) {
      $base = self::_setHeader($base, $name, $value);
    }
    return $base;
  }

  /**
   * @param HeaderMap $headers
   * @return HeaderMap
   */
  private static function _setHeader(array $headers, string $name, string $value):array {
    foreach (\array_keys($headers) as $existing) {
      if (\strcasecmp($existing, $name) === 0) {
        unset($headers[$existing]);
      }
    }
    $headers[$name] = $value;
    return $headers;
  }

  /** @param HeaderMap $headers */
  private static function _hasHeader(array $headers, string $name):bool {
    return self::_header($headers, $name) !== null;
  }

  /** @param HeaderMap $headers */
  private static function _header(array $headers, string $name):?string {
    foreach ($headers as $key => $value) {
      if (\strcasecmp($key, $name) === 0) {
        return $value;
      }
    }
    return null;
  }

  /**
   * @param HeaderMap $headers
   * @param list<string> $names
   * @return HeaderMap
   */
  private static function _withoutHeaders(array $headers, array $names):array {
    foreach (\array_keys($headers) as $existing) {
      foreach ($names as $name) {
        if (\strcasecmp($existing, $name) === 0) {
          unset($headers[$existing]);
          break;
        }
      }
    }
    return $headers;
  }

  private static function _assertHeader(string $name, string $value):void {
    if (!\preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/D", \trim($name))) {
      throw new \InvalidArgumentException('HTTP header name is invalid.');
    }
    self::_assertHeaderValue($value);
  }

  private static function _assertHeaderValue(string $value):void {
    if (\preg_match('/[\r\n\x00]/', $value)) {
      throw new \InvalidArgumentException('HTTP header values cannot contain control characters.');
    }
  }

  private static function _resolveRedirect(string $base, string $location):string {
    self::_assertHeaderValue($location);
    $location = \trim($location);
    if ($location === '') {
      throw new \InvalidArgumentException('Redirect location is empty.');
    }
    if (\preg_match('#^https?://#i', $location)) {
      return $location;
    }

    $baseParts = \parse_url($base);
    if ($baseParts === false || !isset($baseParts['scheme'], $baseParts['host'])) {
      throw new \InvalidArgumentException('Redirect base URL is invalid.');
    }
    if (\str_starts_with($location, '//')) {
      return $baseParts['scheme'] . ':' . $location;
    }

    $authority = $baseParts['scheme'] . '://' . $baseParts['host'];
    if (isset($baseParts['port'])) {
      $authority .= ':' . $baseParts['port'];
    }
    if (\str_starts_with($location, '/')) {
      return $authority . $location;
    }
    if (\str_starts_with($location, '?')) {
      return $authority . ($baseParts['path'] ?? '/') . $location;
    }

    $basePath = $baseParts['path'] ?? '/';
    $path = \rtrim(\str_replace('\\', '/', \dirname($basePath)), '/') . '/' . $location;
    $segments = [];
    foreach (\explode('/', $path) as $segment) {
      if ($segment === '' || $segment === '.') {
        continue;
      }
      if ($segment === '..') {
        \array_pop($segments);
      } else {
        $segments[] = $segment;
      }
    }
    return $authority . '/' . \implode('/', $segments);
  }

  private static function _sameOrigin(string $left, string $right):bool {
    $a = \parse_url($left);
    $b = \parse_url($right);
    if ($a === false || $b === false) {
      return false;
    }
    $aScheme = \strtolower((string) ($a['scheme'] ?? ''));
    $bScheme = \strtolower((string) ($b['scheme'] ?? ''));
    $aPort = (int) ($a['port'] ?? ($aScheme === 'https' ? 443 : 80));
    $bPort = (int) ($b['port'] ?? ($bScheme === 'https' ? 443 : 80));
    return $aScheme === $bScheme
      && \strtolower((string) ($a['host'] ?? '')) === \strtolower((string) ($b['host'] ?? ''))
      && $aPort === $bPort;
  }
}
