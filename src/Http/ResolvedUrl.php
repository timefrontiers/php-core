<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

final readonly class ResolvedUrl {

  /**
   * @param non-empty-string $url
   * @param non-empty-string $host
   * @param non-empty-list<string> $addresses
   */
  public function __construct(
    public string $url,
    public string $scheme,
    public string $host,
    public int $port,
    public array $addresses
  ) {}
}
