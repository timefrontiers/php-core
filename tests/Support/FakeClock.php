<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests\Support;

final class FakeClock {

  private int $nowNs = 0;

  public function nowNs():int {
    return $this->nowNs;
  }

  public function advanceMs(int $milliseconds):void {
    $this->nowNs += $milliseconds * 1000000;
  }
}
