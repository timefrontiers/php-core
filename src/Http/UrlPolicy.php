<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

use TimeFrontiers\Http\Internal\IpAddress;

/**
 * Safe-by-default outbound URL policy. Every DNS answer must be acceptable;
 * approved addresses are then pinned by the transport for that request hop.
 */
final readonly class UrlPolicy {

  private DnsResolver $resolver;

  public function __construct(
    ?DnsResolver $resolver = null,
    private bool $allowPrivateNetworks = false
  ) {
    $this->resolver = $resolver ?? new NativeDnsResolver();
  }

  /** Explicit escape hatch for server-owned, trusted internal destinations. */
  public static function trusted(?DnsResolver $resolver = null):self {
    return new self($resolver, true);
  }

  public function resolve(string $url):ResolvedUrl {
    if ($url === '' || \preg_match('/[\x00-\x20\x7f]/', $url)) {
      throw new \InvalidArgumentException('Outbound URL is malformed.');
    }

    $parts = \parse_url($url);
    $scheme = \strtolower((string) ($parts['scheme'] ?? ''));
    $parsedHost = \strtolower((string) ($parts['host'] ?? ''));
    $host = \trim($parsedHost, '[]');
    if (
      $parts === false
      || !\in_array($scheme, ['http', 'https'], true)
      || $host === ''
      || isset($parts['user'])
      || isset($parts['pass'])
      || isset($parts['fragment'])
      || (
        \filter_var($host, FILTER_VALIDATE_IP) === false
        && !\preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/iD', $host)
      )
    ) {
      throw new \InvalidArgumentException('Only absolute HTTP(S) URLs without credentials or fragments are allowed.');
    }

    $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
    if ($port < 1 || $port > 65535) {
      throw new \InvalidArgumentException('Outbound URL port is invalid.');
    }

    $addresses = $this->resolver->resolve($host);
    if ($addresses === []) {
      throw new \RuntimeException('Outbound destination could not be resolved.');
    }
    foreach ($addresses as $address) {
      if (!IpAddress::isValid($address)) {
        throw new \RuntimeException('DNS returned an invalid destination address.');
      }
      if (!$this->allowPrivateNetworks && !IpAddress::isPublic($address)) {
        throw new \DomainException('Outbound destination is not publicly routable.');
      }
    }

    return new ResolvedUrl(
      $url,
      $scheme,
      $host,
      $port,
      /** @var non-empty-list<string> */ \array_values(\array_unique($addresses))
    );
  }
}
