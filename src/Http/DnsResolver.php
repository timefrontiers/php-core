<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

interface DnsResolver {

  /** @return list<string> */
  public function resolve(string $host):array;
}
