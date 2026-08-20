<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests\Support;

use TimeFrontiers\Http\Transport;
use TimeFrontiers\Http\TransportRequest;
use TimeFrontiers\Http\TransportResponse;

final class FakeTransport implements Transport {

  /** @var list<TransportRequest> */
  public array $requests = [];
  /** @var list<TransportResponse> */
  private array $responses;
  private ?\Closure $beforeSend;

  /** @param list<TransportResponse> $responses */
  public function __construct(array $responses, ?\Closure $beforeSend = null) {
    $this->responses = $responses;
    $this->beforeSend = $beforeSend;
  }

  public function send(TransportRequest $request):TransportResponse {
    $this->requests[] = $request;
    if ($this->beforeSend !== null) {
      ($this->beforeSend)();
    }
    return \array_shift($this->responses)
      ?? new TransportResponse(500, '', [], 'Fake transport has no response.');
  }
}
