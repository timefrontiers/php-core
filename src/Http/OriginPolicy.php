<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

/** Immutable canonical-origin and Host allowlist policy. */
final readonly class OriginPolicy {

  private string $canonicalOrigin;
  /** @var list<string> */
  private array $allowedAuthorities;

  /** @param list<string> $allowedHosts */
  public function __construct(string $canonicalOrigin, array $allowedHosts = []) {
    $this->canonicalOrigin = self::normalizeOrigin($canonicalOrigin);
    $canonicalParts = \parse_url($this->canonicalOrigin);
    $canonicalHost = $canonicalParts['host'] ?? null;
    $canonicalAuthority = \is_string($canonicalHost)
      ? \strtolower($canonicalHost) . (isset($canonicalParts['port']) ? ':' . $canonicalParts['port'] : '')
      : null;
    $normalized = [];
    foreach ([$canonicalAuthority, ...$allowedHosts] as $host) {
      $normalizedAuthority = \is_string($host) ? self::normalizeAuthority($host) : null;
      if ($normalizedAuthority === null) {
        throw new \InvalidArgumentException('Allowed hosts must be valid host or host:port authorities.');
      }
      $normalized[] = $normalizedAuthority;
    }
    $this->allowedAuthorities = \array_values(\array_unique($normalized));
  }

  /** @param array<string, mixed> $server */
  public function origin(array $server, bool $secure):string {
    $authority = $server['HTTP_HOST'] ?? null;
    if (!\is_string($authority) || \preg_match('/[\r\n]/', $authority)) {
      return $this->canonicalOrigin;
    }

    $normalizedAuthority = self::normalizeAuthority($authority);
    if (
      $normalizedAuthority === null
      || !\in_array($normalizedAuthority, $this->allowedAuthorities, true)
    ) {
      return $this->canonicalOrigin;
    }

    return ($secure ? 'https' : 'http') . '://' . $normalizedAuthority;
  }

  public function canonicalOrigin():string {
    return $this->canonicalOrigin;
  }

  private static function normalizeOrigin(string $origin):string {
    if (\preg_match('/[\r\n]/', $origin)) {
      throw new \InvalidArgumentException('Canonical origins cannot contain control characters.');
    }
    $parts = \parse_url($origin);
    if (
      $parts === false
      || !\in_array(\strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
      || !isset($parts['host'])
      || isset($parts['user'])
      || isset($parts['pass'])
      || isset($parts['query'])
      || isset($parts['fragment'])
      || (($parts['path'] ?? '') !== '' && ($parts['path'] ?? '') !== '/')
    ) {
      throw new \InvalidArgumentException('Canonical origins must contain only an HTTP(S) scheme and authority.');
    }
    $scheme = \strtolower((string) ($parts['scheme'] ?? ''));
    $host = \strtolower((string) $parts['host']);
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    return "{$scheme}://{$host}{$port}";
  }

  private static function normalizeAuthority(string $authority):?string {
    if ($authority === '' || \preg_match('/[\r\n\x00-\x20\x7f]/', $authority)) {
      return null;
    }
    $parts = \parse_url('http://' . $authority);
    if (
      $parts === false
      || !isset($parts['host'])
      || isset($parts['user'])
      || isset($parts['pass'])
      || isset($parts['query'])
      || isset($parts['fragment'])
      || (($parts['path'] ?? '') !== '')
    ) {
      return null;
    }

    $host = (string) $parts['host'];
    $address = \trim($host, '[]');
    if (\filter_var($address, FILTER_VALIDATE_IP) !== false) {
      $host = \str_contains($address, ':') ? "[{$address}]" : $address;
    } elseif (!\preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/iD', $host)) {
      return null;
    }

    return \strtolower($host) . (isset($parts['port']) ? ':' . $parts['port'] : '');
  }
}
