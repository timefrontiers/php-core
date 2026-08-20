<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

/**
 * Explicit adapter for php-session 1.1 without creating a Core -> Session
 * package dependency cycle.
 */
final readonly class SessionCsrfAdapter implements CsrfTokenManager {

  /** @var \Closure(string, int): mixed */
  private \Closure $issueToken;
  /** @var \Closure(string, string): mixed */
  private \Closure $validateToken;

  public function __construct(object $session) {
    if (
      !\is_callable([$session, 'generateCSRFToken'])
      || !\is_callable([$session, 'validateCSRFToken'])
    ) {
      throw new \InvalidArgumentException(
        'The session must provide generateCSRFToken() and validateCSRFToken().'
      );
    }
    $this->issueToken = \Closure::fromCallable([$session, 'generateCSRFToken']);
    $this->validateToken = \Closure::fromCallable([$session, 'validateCSRFToken']);
  }

  public function issue(string $action, int $ttl = 3600):string {
    $token = ($this->issueToken)($action, $ttl);
    if (!\is_string($token) || $token === '') {
      throw new \UnexpectedValueException('The CSRF service returned an invalid token.');
    }
    return $token;
  }

  public function verify(string $action, string $token):bool {
    return ($this->validateToken)($action, $token) === true;
  }
}
