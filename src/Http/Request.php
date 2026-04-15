<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

use TimeFrontiers\Helper\HasErrors;
use TimeFrontiers\AccessRank;

/**
 * HTTP request parameter handling with validation.
 *
 * Extracts and validates parameters from GET/POST/custom sources.
 * Uses HasErrors for error collection.
 */
class Request {

  use HasErrors;

  private array $_source;

  /**
   * Create a new Request instance.
   *
   * @param string|array $method 'get', 'post', 'json', or custom array
   */
  public function __construct(string|array $method = 'post') {
    if (\is_array($method)) {
      $this->_source = $method;
    } else {
      $this->_source = match (\strtolower($method)) {
        'get' => $_GET,
        'post' => $_POST,
        'json' => $this->_getJsonInput(),
        'request' => $_REQUEST,
        default => $_POST,
      };
    }
  }

  /**
   * Create from GET parameters.
   */
  public static function fromGet():self {
    return new self('get');
  }

  /**
   * Create from POST parameters.
   */
  public static function fromPost():self {
    return new self('post');
  }

  /**
   * Create from JSON body.
   */
  public static function fromJson():self {
    return new self('json');
  }

  /**
   * Create from custom array.
   */
  public static function fromArray(array $data):self {
    return new self($data);
  }

  /**
   * Get the raw input source.
   */
  public function all():array {
    return $this->_source;
  }

  /**
   * Get a single parameter.
   *
   * @param string $key Parameter name
   * @param mixed $default Default value
   * @return mixed Parameter value or default
   */
  public function get(string $key, mixed $default = null):mixed {
    if (!isset($this->_source[$key])) {
      return $default;
    }

    $value = $this->_source[$key];

    // Trim strings
    if (\is_string($value)) {
      return \trim($value);
    }

    return $value;
  }

  /**
   * Check if parameter exists.
   */
  public function has(string $key):bool {
    return isset($this->_source[$key]);
  }

  /**
   * Get only allowed parameters.
   *
   * @param array $allowed List of allowed parameter names
   * @return array Filtered parameters (null for missing)
   */
  public function only(array $allowed):array {
    $result = [];

    foreach ($allowed as $key) {
      $result[$key] = $this->get($key);
    }

    return $result;
  }

  /**
   * Get parameters except specified ones.
   *
   * @param array $excluded List of excluded parameter names
   * @return array Filtered parameters
   */
  public function except(array $excluded):array {
    return \array_diff_key($this->_source, \array_flip($excluded));
  }

  /**
   * Validate and extract parameters.
   *
   * @param array $columns Validation rules [key => [label, type, ...options]]
   * @param array $required Required parameter keys
   * @param bool $strict If true, return false on any error; if false, return false only if required fields fail
   * @return array|false Validated parameters or false on failure
   */
  public function validate(array $columns, array $required = [], bool $strict = false):array|false {
    $params = $this->only(\array_keys($columns));
    $req_errors = 0;

    // Check required fields
    foreach ($required as $key) {
      if (!isset($columns[$key])) {
        continue;
      }

      $type = $columns[$key][1] ?? 'text';
      $label = $columns[$key][0] ?? $key;

      if ($type !== 'boolean') {
        if (empty($params[$key])) {
          $req_errors++;
          $this->_userError('validate', "[{$label}]: is required but not present.");
        }
      } else {
        if ($params[$key] === null || $params[$key] === '') {
          $req_errors++;
          $this->_userError('validate', "[{$label}]: is required but not present.");
        }
      }
    }

    // Validate values using Validator if available
    if (\class_exists('\TimeFrontiers\Validator')) {
      $validator = new \TimeFrontiers\Validator();

      foreach ($params as $key => $value) {
        if (!isset($columns[$key])) {
          continue;
        }

        $type = $columns[$key][1] ?? 'text';
        $label = $columns[$key][0] ?? $key;

        // Cast numeric types
        if ($type === 'int') {
          $value = (int) $value;
        } elseif ($type === 'float') {
          $value = (float) $value;
        }

        if ($type !== 'boolean') {
          if (!empty($value)) {
            $validated = $validator->validate($value, $columns[$key]);

            if ($validated === false) {
              $this->_mergeValidatorErrors($validator, $type);
            } else {
              $params[$key] = $validated;
            }
          }
        } else {
          if ($value !== null && $value !== '') {
            $params[$key] = (bool) $value;
          }
        }
      }
    }

    // Return based on strict mode
    if ($strict) {
      return $this->hasErrors('validate') ? false : $params;
    }

    return ($this->hasErrors('validate') && $req_errors > 0) ? false : $params;
  }

  /**
   * Verify CSRF token.
   *
   * @param string $form Form identifier
   * @param string $token Token from request
   * @return bool True if valid
   */
  public function verifyCSRF(string $form, string $token):bool {
    global $session;

    // Check session exists
    if (!isset($session) || !\is_object($session)) {
      $this->_internalError('verifyCSRF', 'Session not available for CSRF validation.');
      return false;
    }

    // Skip for logged in users (optional - depends on your security model)
    if (\method_exists($session, 'isLoggedIn') && $session->isLoggedIn()) {
      return true;
    }

    // Get stored token
    $sess_token = $_SESSION['CSRF_token'][$form] ?? null;

    if (empty($sess_token)) {
      $this->_userError(
        'verifyCSRF',
        'No matching security token found. This might be an unauthorized or timed out request. Please reload the page and try again.'
      );
      return false;
    }

    // Parse token and expiry
    $parts = \explode('::', $sess_token);
    $stored_token = $parts[0];
    $expiry = (int) ($parts[1] ?? 0);

    // Check expiry
    if (\time() > $expiry) {
      unset($_SESSION['CSRF_token'][$form]);
      $this->_userError('verifyCSRF', 'Security token expired. Please reload the page and try again.');
      return false;
    }

    // Verify token (timing-safe comparison)
    if (!\hash_equals($stored_token, $token)) {
      $this->_userError('verifyCSRF', 'Security validation failed. Please reload the page or contact admin.');
      return false;
    }

    return true;
  }

  /**
   * Generate a CSRF token.
   *
   * @param string $form Form identifier
   * @param int $ttl Token lifetime in seconds
   * @return string The token
   */
  public static function generateCSRF(string $form, int $ttl = 3600):string {
    $token = \bin2hex(\random_bytes(32));
    $expiry = \time() + $ttl;

    $_SESSION['CSRF_token'][$form] = "{$token}::{$expiry}";

    return $token;
  }

  /**
   * Get a CSRF hidden input field.
   *
   * @param string $form Form identifier
   * @param string $name Input field name
   * @param int $ttl Token lifetime
   * @return string HTML input element
   */
  public static function csrfField(string $form, string $name = 'csrf_token', int $ttl = 3600):string {
    $token = self::generateCSRF($form, $ttl);
    return "<input type=\"hidden\" name=\"{$name}\" value=\"{$token}\">";
  }

  // =========================================================================
  // Private Methods
  // =========================================================================

  /**
   * Get JSON input from request body.
   */
  private function _getJsonInput():array {
    $json = \file_get_contents('php://input');

    if (empty($json)) {
      return [];
    }

    $data = \json_decode($json, true);

    return \is_array($data) ? $data : [];
  }

  /**
   * Merge errors from Validator.
   */
  private function _mergeValidatorErrors(object $validator, string $type):void {
    if (\class_exists('\TimeFrontiers\InstanceError')) {
      $errors = (new \TimeFrontiers\InstanceError($validator, true))->get($type, true);

      if (!empty($errors)) {
        foreach ($errors as $err) {
          $this->_userError('validate', $err);
        }
      }

      // Clear validator errors
      if (isset($validator->errors[$type])) {
        unset($validator->errors[$type]);
      }
    }
  }
}
