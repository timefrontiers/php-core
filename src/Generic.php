<?php

declare(strict_types=1);

namespace TimeFrontiers;

use TimeFrontiers\Helper\HasErrors;
use TimeFrontiers\Http\Http;
use TimeFrontiers\Http\HttpStatus;
use TimeFrontiers\Http\Request;

/**
 * Legacy Generic class for backward compatibility.
 *
 * @deprecated Use specific utility classes instead:
 *   - Http::redirect() instead of Generic::redirect()
 *   - Url::withParams() instead of Generic::setGet()
 *   - Request::only() instead of Generic::allowedParam()
 *   - Str::parseEmailName() instead of Generic::splitEmailName()
 *   - Str::fileExtension() instead of Generic::fileExt()
 *   - Url::exists() instead of Generic::urlExist()
 *   - Str::patternReplace() instead of Generic::patternReplace()
 *   - Str::isBase64() instead of Generic::isBase64()
 */
class Generic {

  use HasErrors;

  /**
   * @deprecated Use Http::redirect()
   */
  public static function redirect(string $location):never {
    Http::redirect($location);
  }

  /**
   * @deprecated Use Url::withParams()
   */
  public static function setGet(string $url, array $param_val):string {
    return Url::withParams($url, $param_val);
  }

  /**
   * @deprecated Use Request::only() or Request::fromGet()->only()
   */
  public static function allowedParam(array $params, string|array $method = 'get'):array {
    if (\is_array($method)) {
      $source = $method;
    } else {
      $source = match (\strtolower($method)) {
        'get' => $_GET,
        'post' => $_POST,
        default => $_GET,
      };
    }

    $allowed = [];

    foreach ($params as $key) {
      $allowed[$key] = null;
    }

    foreach ($params as $param) {
      if (isset($source[$param])) {
        $value = $source[$param];
        $allowed[$param] = \is_string($value) ? \trim($value) : $value;
      }
    }

    return $allowed;
  }

  /**
   * @deprecated Use Str::parseEmailName()
   */
  public static function splitEmailName(string $string):array {
    return Str::parseEmailName($string);
  }

  /**
   * @deprecated Use Str::fileExtension()
   */
  public static function fileExt(string $filename):string {
    return Str::fileExtension($filename);
  }

  /**
   * @deprecated Use Url::exists()
   */
  public static function urlExist(string $url):bool {
    return Url::exists($url);
  }

  /**
   * @deprecated Use Str::patternReplace()
   */
  public static function patternReplace(array $pattern, array $replace, string $value):string {
    return Str::patternReplace($pattern, $replace, $value);
  }

  /**
   * @deprecated Use Str::isBase64()
   */
  public static function isBase64(string $data):bool {
    return Str::isBase64($data);
  }

  /**
   * Validate request parameters.
   *
   * @deprecated Use Request->validate()
   */
  public function requestParam(array $columns, string|array $method, array $required, bool $strict = false):array|false {
    $request = new Request($method);
    $result = $request->validate($columns, $required, $strict);

    // Merge errors
    if ($request->hasErrors()) {
      foreach ($request->getErrors() as $context => $errors) {
        foreach ($errors as $error) {
          $this->_errors['requestParam'][] = $error;
        }
      }
    }

    return $result;
  }

  /**
   * Verify CSRF token.
   *
   * @deprecated Use Request->verifyCSRF()
   */
  public function checkCSRF(string $form, string $token):bool {
    $request = new Request([]);
    $result = $request->verifyCSRF($form, $token);

    // Merge errors
    if ($request->hasErrors()) {
      foreach ($request->getErrors() as $context => $errors) {
        foreach ($errors as $error) {
          $this->_errors['checkCSRF'][] = $error;
        }
      }
    }

    return $result;
  }

  /**
   * Format authentication errors.
   *
   * @param object $auth Authentication instance
   * @param string $message Main message
   * @param string $errname Error context name
   * @param bool $override Bypass rank filter
   * @return array Formatted errors
   */
  public static function authErrors(object $auth, string $message, string $errname, bool $override = true):array {
    $out_errors = [
      'Message' => $message,
    ];

    if (\class_exists('\TimeFrontiers\InstanceError')) {
      $auth_errors = (new \TimeFrontiers\InstanceError($auth, $override))->get($errname, true);

      if (!empty($auth_errors)) {
        $i = 0;
        foreach ($auth_errors as $err) {
          $out_errors["Error-{$i}"] = $err;
          $i++;
        }
      }
    }

    $out_errors['Status'] = '1.' . (\count($out_errors) - 1);

    return $out_errors;
  }
}

// ============================================================================
// Legacy HTTP status constants for backward compatibility
// ============================================================================

if (!\defined('HTTP_OK')) {
  \define('HTTP_OK', HttpStatus::OK->value);
}
if (!\defined('HTTP_CREATED')) {
  \define('HTTP_CREATED', HttpStatus::CREATED->value);
}
if (!\defined('HTTP_BAD_REQUEST')) {
  \define('HTTP_BAD_REQUEST', HttpStatus::BAD_REQUEST->value);
}
if (!\defined('HTTP_UNAUTHORIZED')) {
  \define('HTTP_UNAUTHORIZED', HttpStatus::UNAUTHORIZED->value);
}
if (!\defined('HTTP_FORBIDDEN')) {
  \define('HTTP_FORBIDDEN', HttpStatus::FORBIDDEN->value);
}
if (!\defined('HTTP_NOT_FOUND')) {
  \define('HTTP_NOT_FOUND', HttpStatus::NOT_FOUND->value);
}
if (!\defined('HTTP_INTERNAL_ERROR')) {
  \define('HTTP_INTERNAL_ERROR', HttpStatus::INTERNAL_SERVER_ERROR->value);
}
