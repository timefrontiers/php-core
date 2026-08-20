<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

use TimeFrontiers\Helper\HasErrors;
use TimeFrontiers\Validation\Validator;

/** HTTP request extraction with explicit validation and CSRF boundaries. */
class Request {

  use HasErrors;

  /** @var array<string, mixed> */
  private array $_source;
  private ?CsrfTokenManager $_csrf;
  private static ?CsrfTokenManager $_legacyCsrf = null;

  /** @param string|array<string, mixed> $method */
  public function __construct(string|array $method = 'post', ?CsrfTokenManager $csrf = null) {
    $this->_csrf = $csrf;
    if (\is_array($method)) {
      $this->_source = $method;
      return;
    }

    $this->_source = match (\strtolower($method)) {
      'get' => $_GET,
      'post' => $_POST,
      'json' => $this->_getJsonInput(),
      'request' => $_REQUEST,
      default => $_POST,
    };
  }

  public static function fromGet(?CsrfTokenManager $csrf = null):self {
    return new self('get', $csrf);
  }

  public static function fromPost(?CsrfTokenManager $csrf = null):self {
    return new self('post', $csrf);
  }

  public static function fromJson(?CsrfTokenManager $csrf = null):self {
    return new self('json', $csrf);
  }

  /** @param array<string, mixed> $data */
  public static function fromArray(array $data, ?CsrfTokenManager $csrf = null):self {
    return new self($data, $csrf);
  }

  public function withCsrfTokenManager(CsrfTokenManager $csrf):self {
    $clone = clone $this;
    $clone->_csrf = $csrf;
    return $clone;
  }

  /** @return array<string, mixed> */
  public function all():array {
    return $this->_source;
  }

  public function get(string $key, mixed $default = null):mixed {
    if (!\array_key_exists($key, $this->_source)) {
      return $default;
    }
    $value = $this->_source[$key];
    return \is_string($value) ? \trim($value) : $value;
  }

  public function has(string $key):bool {
    return \array_key_exists($key, $this->_source);
  }

  /**
   * @param list<string> $allowed
   * @return array<string, mixed>
   */
  public function only(array $allowed):array {
    $result = [];
    foreach ($allowed as $key) {
      $result[$key] = $this->get($key);
    }
    return $result;
  }

  /**
   * @param list<string> $excluded
   * @return array<string, mixed>
   */
  public function except(array $excluded):array {
    return \array_diff_key($this->_source, \array_flip($excluded));
  }

  /**
   * Validate legacy column declarations and return a first-class result.
   * Each declaration remains `[label, rule, ...ruleParameters]`.
   *
   * @param array<string, array<array-key, mixed>> $columns
   * @param list<string> $required
   */
  public function validateResult(array $columns, array $required = []):RequestValidationResult {
    if (!\class_exists(Validator::class)) {
      $this->_internalError('validate', 'The required validation service is unavailable.');
      return new RequestValidationResult(false, [], [
        '_request' => ['Request validation is unavailable.'],
      ]);
    }

    $requiredLookup = \array_fill_keys($required, true);
    foreach ($required as $field) {
      if (!\array_key_exists($field, $columns)) {
        throw new \InvalidArgumentException("Required field '{$field}' has no validation rule.");
      }
    }

    $rules = [];
    $labels = [];
    foreach ($columns as $field => $definition) {
      if (!\is_string($field) || $field === '' || !\is_array($definition)) {
        throw new \InvalidArgumentException('Request validation columns must use non-empty field names and rule arrays.');
      }
      $label = $definition[0] ?? $field;
      $type = $definition[1] ?? null;
      if (!\is_string($label) || $label === '' || !\is_string($type) || $type === '') {
        throw new \InvalidArgumentException("Request field '{$field}' must declare a label and validation rule.");
      }

      $params = \array_values(\array_slice($definition, 2));
      $rules[$field] = isset($requiredLookup[$field]) ? ['required'] : ['nullable'];
      $rules[$field][] = $params === [] ? $type : [$type, ...$params];
      $labels[$field] = $label;
    }

    $result = Validator::make($this->only(\array_keys($columns)), $rules);
    $errors = [];
    foreach ($result->errors() as $field => $messages) {
      $label = $labels[$field] ?? $field;
      foreach ($messages as $message) {
        $safe = "[{$label}]: {$message}";
        $errors[$field][] = $safe;
        $this->_userError('validate', $safe);
      }
    }

    return new RequestValidationResult(
      $result->passes(),
      $result->validated(),
      $errors
    );
  }

  /**
   * Compatibility facade. `$strict` remains accepted; v1.1 fails closed on
   * every configured validation error.
   *
   * @param array<string, array<array-key, mixed>> $columns
   * @param list<string> $required
   * @return array<string, mixed>|false
   */
  public function validate(array $columns, array $required = [], bool $strict = false):array|false {
    unset($strict);
    $result = $this->validateResult($columns, $required);
    return $result->passes() ? $result->validated() : false;
  }

  public function verifyCSRF(string $form, string $token):bool {
    if ($this->_csrf === null) {
      $this->_internalError('verifyCSRF', 'No CSRF token manager was supplied.');
      return false;
    }

    try {
      $valid = $this->_csrf->verify($form, $token);
    } catch (\Throwable $error) {
      $this->_systemError('verifyCSRF', 'The CSRF token manager failed: ' . $error::class);
      return false;
    }

    if (!$valid) {
      $this->_userError(
        'verifyCSRF',
        'Security validation failed. Reload the page and try again.'
      );
    }
    return $valid;
  }

  /** Configure only the deprecated static helpers. */
  public static function configureLegacyCsrf(CsrfTokenManager $csrf):void {
    self::$_legacyCsrf = $csrf;
  }

  /** @deprecated Inject a CsrfTokenManager and call issue() directly. */
  public static function generateCSRF(
    string $form,
    int $ttl = 3600,
    ?CsrfTokenManager $csrf = null
  ):string {
    $manager = $csrf ?? self::$_legacyCsrf;
    if ($manager === null) {
      throw new \LogicException('A CSRF token manager must be explicitly configured.');
    }
    return $manager->issue($form, $ttl);
  }

  /** @deprecated Prefer the view layer or php-session's escaped csrfField(). */
  public static function csrfField(
    string $form,
    string $name = 'csrf_token',
    int $ttl = 3600,
    ?CsrfTokenManager $csrf = null
  ):string {
    $token = self::generateCSRF($form, $ttl, $csrf);
    return '<input type="hidden" name="'
      . \htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
      . '" value="'
      . \htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
      . '">';
  }

  /** @return array<string, mixed> */
  private function _getJsonInput():array {
    $json = \file_get_contents('php://input');
    if ($json === false || $json === '') {
      return [];
    }

    try {
      $data = \json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
      $this->_userError('json', 'The request body contains malformed JSON.');
      return [];
    }

    if (!\is_array($data) || \array_is_list($data)) {
      $this->_userError('json', 'The JSON request body must be an object.');
      return [];
    }
    return $data;
  }
}
