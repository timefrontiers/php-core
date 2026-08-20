<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

final readonly class TransportRequest {

  /**
   * @param non-empty-string $method
   * @param array<string, string> $headers
   * @param non-empty-string|null $body
   * @param non-empty-string $userAgent
   */
  public function __construct(
    public string $method,
    public ResolvedUrl $destination,
    public array $headers,
    public ?string $body,
    public int $connectTimeoutMs,
    public int $timeoutMs,
    public int $maxHeaderBytes,
    public int $maxBodyBytes,
    public bool $verifySsl,
    public string $userAgent
  ) {}
}
