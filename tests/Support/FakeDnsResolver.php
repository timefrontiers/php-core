<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests\Support;

use TimeFrontiers\Http\DnsResolver;

final readonly class FakeDnsResolver implements DnsResolver {

  /** @param array<string, list<string>> $answers */
  public function __construct(
    private array $answers,
    private ?\Closure $beforeResolve = null
  ) {}

  public function resolve(string $host):array {
    if ($this->beforeResolve !== null) {
      ($this->beforeResolve)();
    }
    return $this->answers[$host] ?? [];
  }
}
