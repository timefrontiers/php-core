<?php

declare(strict_types=1);

namespace TimeFrontiers\Http\Internal;

final class IpAddress {

  /**
   * Every IPv4 special-purpose block in the IANA registry, collapsed where a
   * parent entry covers its children, plus multicast. The safe outbound policy
   * deliberately rejects even the registry entries marked globally reachable;
   * callers that own such a destination must opt into UrlPolicy::trusted().
   *
   * Registry reviewed 2025-10-09.
   *
   * @var list<string>
   */
  private const NON_PUBLIC_IPV4_RANGES = [
    '0.0.0.0/8',
    '10.0.0.0/8',
    '100.64.0.0/10',
    '127.0.0.0/8',
    '169.254.0.0/16',
    '172.16.0.0/12',
    '192.0.0.0/24',
    '192.0.2.0/24',
    '192.31.196.0/24',
    '192.52.193.0/24',
    '192.88.99.0/24',
    '192.168.0.0/16',
    '192.175.48.0/24',
    '198.18.0.0/15',
    '198.51.100.0/24',
    '203.0.113.0/24',
    '224.0.0.0/4',
    '240.0.0.0/4',
  ];

  /**
   * Currently allocated IPv6 global-unicast blocks. Space in 2000::/3 that is
   * absent from this IANA registry is reserved for future allocation and must
   * fail closed until this list is deliberately reviewed.
   *
   * Registry reviewed 2025-10-10.
   *
   * @var list<string>
   */
  private const PUBLIC_IPV6_RANGES = [
    '2001:200::/23',
    '2001:400::/23',
    '2001:600::/23',
    '2001:800::/22',
    '2001:c00::/23',
    '2001:e00::/23',
    '2001:1200::/23',
    '2001:1400::/22',
    '2001:1800::/23',
    '2001:1a00::/23',
    '2001:1c00::/22',
    '2001:2000::/19',
    '2001:4000::/23',
    '2001:4200::/23',
    '2001:4400::/23',
    '2001:4600::/23',
    '2001:4800::/23',
    '2001:4a00::/23',
    '2001:4c00::/23',
    '2001:5000::/20',
    '2001:8000::/19',
    '2001:a000::/20',
    '2001:b000::/20',
    '2003::/18',
    '2400::/12',
    '2410::/12',
    '2600::/12',
    '2610::/23',
    '2620::/23',
    '2630::/12',
    '2800::/12',
    '2a00::/12',
    '2a10::/12',
    '2c00::/12',
  ];

  /**
   * IANA special-purpose entries that overlap allocated IPv6 global-unicast
   * space. Entries outside the allowlist above (translation, discard, dummy,
   * SRv6, ULA, link-local, multicast, mapped, and unspecified addresses) are
   * already rejected by allocation status.
   *
   * Registry reviewed 2025-10-09.
   *
   * @var list<string>
   */
  private const NON_PUBLIC_IPV6_RANGES = [
    '2001::/23',
    '2001:db8::/32',
    '2002::/16',
    '2620:4f:8000::/48',
    '3fff::/20',
  ];

  public static function isValid(string $ip):bool {
    return \filter_var($ip, FILTER_VALIDATE_IP) !== false;
  }

  public static function isRange(string $range):bool {
    $parts = \explode('/', $range);
    if (\count($parts) > 2 || !self::isValid($parts[0])) {
      return false;
    }
    if (!isset($parts[1])) {
      return true;
    }
    if ($parts[1] === '' || !\ctype_digit($parts[1])) {
      return false;
    }
    $max = \filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? 32 : 128;
    return (int) $parts[1] <= $max;
  }

  public static function isPublic(string $ip):bool {
    if (\filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
      return !self::inAnyRange($ip, self::NON_PUBLIC_IPV4_RANGES);
    }

    if (\filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
      return false;
    }

    if (!self::inAnyRange($ip, self::PUBLIC_IPV6_RANGES)) {
      return false;
    }

    return !self::inAnyRange($ip, self::NON_PUBLIC_IPV6_RANGES);
  }

  public static function inRange(string $ip, string $range):bool {
    if (!self::isValid($ip)) {
      return false;
    }

    if (!\str_contains($range, '/')) {
      $packedIp = \inet_pton($ip);
      $packedRange = \inet_pton($range);
      return $packedIp !== false
        && $packedRange !== false
        && \hash_equals($packedRange, $packedIp);
    }

    [$network, $prefixText] = \explode('/', $range, 2);
    if (!self::isValid($network) || !\ctype_digit($prefixText)) {
      return false;
    }

    $packedIp = \inet_pton($ip);
    $packedNetwork = \inet_pton($network);
    if ($packedIp === false || $packedNetwork === false || \strlen($packedIp) !== \strlen($packedNetwork)) {
      return false;
    }

    $prefix = (int) $prefixText;
    $maxBits = \strlen($packedIp) * 8;
    if ($prefix < 0 || $prefix > $maxBits) {
      return false;
    }

    $wholeBytes = intdiv($prefix, 8);
    $remainingBits = $prefix % 8;
    if ($wholeBytes > 0 && \substr($packedIp, 0, $wholeBytes) !== \substr($packedNetwork, 0, $wholeBytes)) {
      return false;
    }

    if ($remainingBits === 0) {
      return true;
    }

    $mask = (0xff << (8 - $remainingBits)) & 0xff;
    return (\ord($packedIp[$wholeBytes]) & $mask) === (\ord($packedNetwork[$wholeBytes]) & $mask);
  }

  /** @param list<string> $ranges */
  private static function inAnyRange(string $ip, array $ranges):bool {
    foreach ($ranges as $range) {
      if (self::inRange($ip, $range)) {
        return true;
      }
    }
    return false;
  }
}
