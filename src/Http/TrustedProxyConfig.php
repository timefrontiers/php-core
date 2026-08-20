<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

use TimeFrontiers\Http\Internal\IpAddress;

/** Immutable host-owned trust configuration for inbound proxy metadata. */
final readonly class TrustedProxyConfig {

  /**
   * @param list<string> $trustedProxies IP addresses or CIDR ranges
   */
  public function __construct(
    private array $trustedProxies,
    private string $clientIpHeader = 'X-Forwarded-For',
    private string $schemeHeader = 'X-Forwarded-Proto'
  ) {
    foreach ($trustedProxies as $range) {
      if (!IpAddress::isRange($range)) {
        throw new \InvalidArgumentException('Trusted proxy entries must be IP addresses or CIDR ranges.');
      }
    }
    self::assertHeaderName($clientIpHeader);
    self::assertHeaderName($schemeHeader);
  }

  public function trusts(string $ip):bool {
    foreach ($this->trustedProxies as $range) {
      if (IpAddress::inRange($ip, $range)) {
        return true;
      }
    }
    return false;
  }

  /** @param array<string, mixed> $server */
  public function clientIp(array $server):string {
    $peer = self::serverIp($server['REMOTE_ADDR'] ?? null);
    if (!$this->trusts($peer)) {
      return $peer;
    }

    $value = self::serverHeader($server, $this->clientIpHeader);
    if ($value === null) {
      return $peer;
    }

    $chain = \array_map('trim', \explode(',', $value));
    if ($chain === [] || \in_array('', $chain, true)) {
      return $peer;
    }
    foreach ($chain as $ip) {
      if (!IpAddress::isValid($ip)) {
        return $peer;
      }
    }

    $chain[] = $peer;
    for ($index = \count($chain) - 1; $index >= 0; $index--) {
      if (!$this->trusts($chain[$index])) {
        return $chain[$index];
      }
    }
    return $chain[0];
  }

  /** @param array<string, mixed> $server */
  public function isSecure(array $server):bool {
    $direct = Http::isDirectlySecure($server);
    $peer = self::serverIp($server['REMOTE_ADDR'] ?? null);
    if (!$this->trusts($peer)) {
      return $direct;
    }

    $value = self::serverHeader($server, $this->schemeHeader);
    if ($value === null || \str_contains($value, ',')) {
      return $direct;
    }
    $scheme = \strtolower(\trim($value));
    return $scheme === 'https' ? true : ($scheme === 'http' ? false : $direct);
  }

  private static function serverIp(mixed $value):string {
    return \is_string($value) && IpAddress::isValid($value) ? $value : '0.0.0.0';
  }

  /** @param array<string, mixed> $server */
  private static function serverHeader(array $server, string $name):?string {
    $key = 'HTTP_' . \strtoupper(\str_replace('-', '_', $name));
    $value = $server[$key] ?? null;
    return \is_string($value) && !\preg_match('/[\r\n]/', $value) ? $value : null;
  }

  private static function assertHeaderName(string $name):void {
    if (!\preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/D", $name)) {
      throw new \InvalidArgumentException('Proxy header names must be valid HTTP field names.');
    }
  }
}
