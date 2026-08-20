<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests\Support;

use TimeFrontiers\Http\CsrfTokenManager;

final class InMemoryCsrfManager implements CsrfTokenManager {

  /** @var array<string, array<string, int>> */
  private array $tokens = [];
  private int $counter = 0;

  public function __construct(public int $now = 1000) {}

  public function issue(string $action, int $ttl = 3600):string {
    $token = \hash('sha256', $action . ':' . ++$this->counter);
    $this->tokens[$action][\hash('sha256', $token)] = $this->now + $ttl;
    return $token;
  }

  public function verify(string $action, string $token):bool {
    $hash = \hash('sha256', $token);
    $expiry = $this->tokens[$action][$hash] ?? null;
    if ($expiry === null || $expiry < $this->now || !\hash_equals($hash, \hash('sha256', $token))) {
      return false;
    }
    unset($this->tokens[$action][$hash]);
    return true;
  }
}
