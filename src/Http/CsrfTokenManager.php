<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

/**
 * Host-supplied CSRF boundary. Session implementations own token storage,
 * expiry, action binding, constant-time comparison, and consumption.
 */
interface CsrfTokenManager {

  public function issue(string $action, int $ttl = 3600):string;

  public function verify(string $action, string $token):bool;
}
