<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

/** Bounded HTTP response wrapper with explicit decode failures. */
class ClientResponse {

  private int $_status_code;
  private string $_body;
  /** @var array<string, string> */
  private array $_headers;
  private ?string $_error;
  /** @var array<string, int|string|float|bool|null> */
  private array $_diagnostic;
  /** @var array<string, mixed> */
  private array $_decoded = [];

  /**
   * @param array<string, string> $headers
   * @param array<string, int|string|float|bool|null> $diagnostic
   */
  public function __construct(
    int $status_code,
    string $body,
    array $headers = [],
    ?string $error = null,
    array $diagnostic = []
  ) {
    $this->_status_code = $status_code;
    $this->_body = $body;
    $this->_headers = $headers;
    $this->_error = $error;
    $this->_diagnostic = $diagnostic;
  }

  public function statusCode():int {
    return $this->_status_code;
  }

  public function status():?HttpStatus {
    return HttpStatus::fromCode($this->_status_code);
  }

  public function statusMessage():string {
    return $this->status()?->line() ?? "{$this->_status_code} Unknown";
  }

  public function isSuccess():bool {
    return $this->_status_code >= 200 && $this->_status_code < 300;
  }

  public function isRedirect():bool {
    return $this->_status_code >= 300 && $this->_status_code < 400;
  }

  public function isClientError():bool {
    return $this->_status_code >= 400 && $this->_status_code < 500;
  }

  public function isServerError():bool {
    return $this->_status_code >= 500 && $this->_status_code < 600;
  }

  public function isError():bool {
    return $this->_status_code >= 400;
  }

  public function isOk():bool {
    return $this->_status_code === 200;
  }

  public function isFailed():bool {
    return $this->_error !== null;
  }

  public function body():string {
    return $this->_body;
  }

  public function text():string {
    return $this->_body;
  }

  /**
   * Decode JSON. Valid JSON `null` returns null; malformed JSON throws.
   *
   * @throws \JsonException
   */
  public function json(bool $assoc = true):mixed {
    $key = $assoc ? 'assoc' : 'object';
    if (!\array_key_exists($key, $this->_decoded)) {
      $this->_decoded[$key] = \json_decode(
        $this->_body,
        $assoc,
        512,
        JSON_THROW_ON_ERROR
      );
    }
    return $this->_decoded[$key];
  }

  public function get(string $key, mixed $default = null):mixed {
    $data = $this->json();
    if (!\is_array($data)) {
      return $default;
    }

    $value = $data;
    foreach (\explode('.', $key) as $part) {
      if (!\is_array($value) || !\array_key_exists($part, $value)) {
        return $default;
      }
      $value = $value[$part];
    }
    return $value;
  }

  /**
   * Decode UTF-8 XML without entity expansion or network access.
   *
   * @throws \LengthException
   * @throws \UnexpectedValueException
   */
  public function xml(int $maxBytes = 1048576):\SimpleXMLElement {
    if ($maxBytes < 1 || \strlen($this->_body) > $maxBytes) {
      throw new \LengthException('XML response exceeds the configured limit.');
    }

    $body = $this->_body;
    if (\str_starts_with($body, "\xEF\xBB\xBF")) {
      $body = \substr($body, 3);
    }
    if (\str_contains($body, "\0") || \preg_match('//u', $body) !== 1) {
      throw new \UnexpectedValueException('XML responses must use UTF-8 encoding.');
    }
    if (\preg_match('/\A<\?xml\b[^?]*\bencoding\s*=\s*(["\'])([^"\']+)\1/i', $body, $matches)) {
      $encoding = \strtoupper(\trim($matches[2]));
      if (!\in_array($encoding, ['UTF-8', 'UTF8'], true)) {
        throw new \UnexpectedValueException('XML responses must declare UTF-8 encoding.');
      }
    }
    if (\preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/iu', $body)) {
      throw new \UnexpectedValueException('XML document type declarations are not allowed.');
    }

    $previous = \libxml_use_internal_errors(true);
    try {
      \libxml_clear_errors();
      $xml = \simplexml_load_string(
        $body,
        \SimpleXMLElement::class,
        LIBXML_NONET | LIBXML_COMPACT
      );
      if ($xml === false) {
        throw new \UnexpectedValueException('HTTP response contains malformed XML.');
      }
      return $xml;
    } finally {
      \libxml_clear_errors();
      \libxml_use_internal_errors($previous);
    }
  }

  /** @return array<string, string> */
  public function headers():array {
    return $this->_headers;
  }

  public function header(string $name):?string {
    foreach ($this->_headers as $key => $value) {
      if (\strcasecmp($key, $name) === 0) {
        return $value;
      }
    }
    return null;
  }

  public function hasHeader(string $name):bool {
    return $this->header($name) !== null;
  }

  public function contentType():?string {
    return $this->header('Content-Type');
  }

  public function isJson():bool {
    $type = $this->contentType();
    return $type !== null && \str_contains(\strtolower($type), 'json');
  }

  /** Safe, non-diagnostic transport failure text. */
  public function error():?string {
    return $this->_error;
  }

  public function throwIfError():self {
    if ($this->isFailed()) {
      throw new \RuntimeException($this->_error ?? 'HTTP request failed.');
    }
    if ($this->isError()) {
      throw new \RuntimeException('HTTP ' . $this->statusMessage(), $this->_status_code);
    }
    return $this;
  }

  /**
   * Safe projection for ordinary logging or API composition.
   *
   * @return array{status_code: int, success: bool, redirect: bool, failed: bool, content_type: ?string}
   */
  public function toArray():array {
    return [
      'status_code' => $this->_status_code,
      'success' => $this->isSuccess(),
      'redirect' => $this->isRedirect(),
      'failed' => $this->isFailed(),
      'content_type' => $this->contentType(),
    ];
  }

  /**
   * Explicit trusted diagnostic projection. May contain response/provider data.
   *
   * @return array<string, mixed>
   */
  public function toDebugArray():array {
    $json = null;
    $jsonError = null;
    try {
      $json = $this->json();
    } catch (\JsonException $error) {
      $jsonError = $error->getMessage();
    }

    return [
      ...$this->toArray(),
      'headers' => $this->_headers,
      'body' => $this->_body,
      'json' => $json,
      'json_error' => $jsonError,
      'error' => $this->_error,
      'diagnostic' => $this->_diagnostic,
    ];
  }

  public function __toString():string {
    return $this->_body;
  }
}
