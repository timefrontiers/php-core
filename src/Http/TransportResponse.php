<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

final readonly class TransportResponse {

  /**
   * @param array<string, string> $headers
   * @param array<string, int|string|float|bool|null> $diagnostic
   */
  public function __construct(
    public int $statusCode,
    public string $body,
    public array $headers = [],
    public ?string $error = null,
    public array $diagnostic = []
  ) {}
}
