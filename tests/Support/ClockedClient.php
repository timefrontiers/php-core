<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests\Support;

use TimeFrontiers\Http\Client;

final class ClockedClient extends Client {

  public function __construct(private readonly FakeClock $clock) {
    parent::__construct();
  }

  protected function _monotonicNowNs():int {
    return $this->clock->nowNs();
  }
}
