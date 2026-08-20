<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

final class NativeDnsResolver implements DnsResolver {

  public function resolve(string $host):array {
    if (\filter_var($host, FILTER_VALIDATE_IP) !== false) {
      return [$host];
    }

    $addresses = [];
    $records = @\dns_get_record($host, DNS_A | DNS_AAAA);
    if (\is_array($records)) {
      foreach ($records as $record) {
        $address = $record['ip'] ?? $record['ipv6'] ?? null;
        if (\is_string($address) && \filter_var($address, FILTER_VALIDATE_IP) !== false) {
          $addresses[] = $address;
        }
      }
    }

    return \array_values(\array_unique($addresses));
  }
}
