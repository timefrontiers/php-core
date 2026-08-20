<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests;

use PHPUnit\Framework\TestCase;
use TimeFrontiers\Http\Client;
use TimeFrontiers\Http\TransportRequest;
use TimeFrontiers\Http\TransportResponse;
use TimeFrontiers\Http\UrlPolicy;
use TimeFrontiers\Tests\Support\ClockedClient;
use TimeFrontiers\Tests\Support\FakeClock;
use TimeFrontiers\Tests\Support\FakeDnsResolver;
use TimeFrontiers\Tests\Support\FakeTransport;

final class ClientSecurityTest extends TestCase {

  public function testPolicyRejectsLoopbackPrivateAndReservedDestinations():void {
    $blocked = [
      // Complete IPv4 special-purpose registry plus multicast representatives.
      '0.0.0.1',
      '10.0.0.1',
      '100.64.0.1',
      '127.0.0.1',
      '169.254.169.254',
      '172.16.0.1',
      '192.0.0.9',
      '192.0.2.1',
      '192.31.196.1',
      '192.52.193.1',
      '192.88.99.1',
      '192.168.0.1',
      '192.175.48.1',
      '198.18.0.1',
      '198.51.100.1',
      '203.0.113.1',
      '224.0.0.1',
      '239.255.255.250',
      '240.0.0.1',
      '255.255.255.255',
      // IPv6 non-global and IANA special-purpose representatives.
      '::',
      '::1',
      '::ffff:127.0.0.1',
      '64:ff9b::1',
      '64:ff9b:1::1',
      '100::1',
      '100:0:0:1::1',
      '2001::1',
      '2001:1::1',
      '2001:2::1',
      '2001:10::1',
      '2001:20::1',
      '2001:30::1',
      '2001:db8::1',
      '2002:7f00:1::',
      '2200::1',
      '2620:4f:8000::1',
      '2d00::1',
      '3fff::1',
      '5f00::1',
      'fc00::1',
      'fe80::1',
      'ff00::1',
      'ff02::1',
    ];

    foreach ($blocked as $address) {
      $policy = new UrlPolicy(new FakeDnsResolver(['blocked.example' => [$address]]));
      try {
        $policy->resolve('https://blocked.example/path');
        self::fail("{$address} should have been rejected.");
      } catch (\DomainException) {
        self::addToAssertionCount(1);
      }
    }
  }

  public function testPolicyAcceptsOrdinaryPublicAddresses():void {
    foreach (['8.8.8.8', '93.184.216.34', '2001:4860:4860::8888', '2606:4700:4700::1111'] as $address) {
      $policy = new UrlPolicy(new FakeDnsResolver(['public.example' => [$address]]));
      self::assertSame([$address], $policy->resolve('https://public.example/')->addresses);
    }
  }

  public function testPolicyRejectsCredentialsProtocolsAndMixedDnsAnswers():void {
    $resolver = new FakeDnsResolver([
      'mixed.example' => ['93.184.216.34', '127.0.0.1'],
      'public.example' => ['93.184.216.34'],
    ]);
    $policy = new UrlPolicy($resolver);

    foreach ([
      'https://user@public.example/path',
      'ftp://public.example/path',
      'https://mixed.example/path',
    ] as $url) {
      try {
        $policy->resolve($url);
        self::fail("{$url} should have been rejected.");
      } catch (\InvalidArgumentException|\DomainException) {
        self::addToAssertionCount(1);
      }
    }
  }

  public function testTrustedPolicyIsAnExplicitPrivateNetworkEscapeHatch():void {
    $policy = UrlPolicy::trusted(new FakeDnsResolver(['internal.example' => ['10.0.0.5']]));
    $resolved = $policy->resolve('https://internal.example/health');

    self::assertSame(['10.0.0.5'], $resolved->addresses);
  }

  public function testPublicIpv6LiteralIsAcceptedWithoutDnsAmbiguity():void {
    $address = '2606:4700:4700::1111';
    $policy = new UrlPolicy(new FakeDnsResolver([$address => [$address]]));

    $resolved = $policy->resolve("https://[{$address}]/dns-query");
    self::assertSame($address, $resolved->host);
    self::assertSame([$address], $resolved->addresses);
  }

  public function testRedirectTargetIsReResolvedAndSsrfIsBlocked():void {
    $resolver = new FakeDnsResolver([
      'public.example' => ['93.184.216.34'],
      'metadata.example' => ['169.254.169.254'],
    ]);
    $transport = new FakeTransport([
      new TransportResponse(302, '', ['Location' => 'http://metadata.example/latest']),
    ]);
    $response = (new Client())
      ->setUrlPolicy(new UrlPolicy($resolver))
      ->setTransport($transport)
      ->get('https://public.example/start');

    self::assertTrue($response->isFailed());
    self::assertSame('HTTP request blocked by URL policy.', $response->error());
    self::assertCount(1, $transport->requests, 'The blocked redirect must never reach transport.');
  }

  public function testSensitiveHeadersAreRemovedAcrossOrigins():void {
    $resolver = new FakeDnsResolver([
      'one.example' => ['93.184.216.34'],
      'two.example' => ['142.250.72.14'],
    ]);
    $transport = new FakeTransport([
      new TransportResponse(302, '', ['Location' => 'https://two.example/final']),
      new TransportResponse(200, '{}', ['Content-Type' => 'application/json']),
    ]);
    $response = (new Client())
      ->setUrlPolicy(new UrlPolicy($resolver))
      ->setTransport($transport)
      ->setHeaders([
        'Accept-Language' => 'en',
        'Authorization' => 'Bearer secret',
        'Cookie' => 'session=secret',
        'X-Amz-Security-Token' => 'aws-secret',
        'X-Client-Secret' => 'client-secret',
        'Api-Key' => 'api-secret',
        'X-Caller-Defined-Credential' => 'custom-secret',
        'X-Trace-Id' => 'trace',
      ])
      ->get('https://one.example/start');

    self::assertTrue($response->isSuccess());
    self::assertCount(2, $transport->requests);
    $second = $transport->requests[1];
    self::assertSame([
      'Accept-Language' => 'en',
      'Accept' => Client::CONTENT_JSON,
    ], $second->headers);
  }

  public function testNonstandardHeaderRequiresExplicitCrossOriginOptIn():void {
    $resolver = new FakeDnsResolver([
      'one.example' => ['93.184.216.34'],
      'two.example' => ['142.250.72.14'],
    ]);
    $transport = new FakeTransport([
      new TransportResponse(302, '', ['Location' => 'https://two.example/final']),
      new TransportResponse(200, '{}'),
    ]);

    $response = (new Client())
      ->setUrlPolicy(new UrlPolicy($resolver))
      ->setTransport($transport)
      ->setHeaders([
        'X-Trace-Id' => 'safe-to-forward',
        'X-Client-Secret' => 'must-still-be-removed',
      ])
      ->allowCrossOriginHeaders(['X-Trace-Id'])
      ->get('https://one.example/start');

    self::assertTrue($response->isSuccess());
    self::assertSame('safe-to-forward', $transport->requests[1]->headers['X-Trace-Id']);
    self::assertArrayNotHasKey('X-Client-Secret', $transport->requests[1]->headers);
  }

  public function testCredentialHeaderCannotBeExplicitlyAllowedAcrossOrigins():void {
    $this->expectException(\InvalidArgumentException::class);
    (new Client())->allowCrossOriginHeaders(['Authorization']);
  }

  public function testNonIdempotentBodyIsNotReplayedWithoutPermission():void {
    $resolver = new FakeDnsResolver(['one.example' => ['93.184.216.34']]);
    $transport = new FakeTransport([
      new TransportResponse(307, '', ['Location' => '/again']),
    ]);
    $response = (new Client())
      ->setUrlPolicy(new UrlPolicy($resolver))
      ->setTransport($transport)
      ->post('https://one.example/start', ['value' => 'secret']);

    self::assertTrue($response->isFailed());
    self::assertCount(1, $transport->requests);
    self::assertSame('HTTP redirect requires explicit replay permission.', $response->error());
  }

  public function testRedirectMayDropRatherThanReplayPostBody():void {
    $resolver = new FakeDnsResolver(['one.example' => ['93.184.216.34']]);
    $transport = new FakeTransport([
      new TransportResponse(303, '', ['Location' => '/result']),
      new TransportResponse(200, '{}'),
    ]);
    $response = (new Client())
      ->setUrlPolicy(new UrlPolicy($resolver))
      ->setTransport($transport)
      ->post('https://one.example/start', ['value' => 'secret']);

    self::assertTrue($response->isSuccess());
    self::assertSame(Client::GET, $transport->requests[1]->method);
    self::assertNull($transport->requests[1]->body);
    self::assertArrayNotHasKey('Content-Type', $transport->requests[1]->headers);
  }

  public function testTimeoutAndResponseBoundsReachEveryTransportHop():void {
    $resolver = new FakeDnsResolver(['one.example' => ['93.184.216.34']]);
    $transport = new FakeTransport([new TransportResponse(200, '')]);
    (new Client())
      ->setUrlPolicy(new UrlPolicy($resolver))
      ->setTransport($transport)
      ->setConnectTimeout(2)
      ->setTimeout(7)
      ->setResponseLimits(4096, 8192)
      ->get('https://one.example/');

    $request = $transport->requests[0];
    self::assertSame(2000, $request->connectTimeoutMs);
    self::assertGreaterThan(0, $request->timeoutMs);
    self::assertLessThanOrEqual(7000, $request->timeoutMs);
    self::assertSame(4096, $request->maxHeaderBytes);
    self::assertSame(8192, $request->maxBodyBytes);
    self::assertSame(['93.184.216.34'], $request->destination->addresses);
  }

  public function testTotalTimeoutBudgetShrinksAcrossRedirectHops():void {
    $clock = new FakeClock();
    $resolver = new FakeDnsResolver(['one.example' => ['93.184.216.34']]);
    $transport = new FakeTransport([
      new TransportResponse(302, '', ['Location' => '/2']),
      new TransportResponse(302, '', ['Location' => '/3']),
      new TransportResponse(302, '', ['Location' => '/4']),
      new TransportResponse(200, '{}'),
    ], static function () use ($clock):void {
      $clock->advanceMs(300);
    });

    $response = (new ClockedClient($clock))
      ->setUrlPolicy(new UrlPolicy($resolver))
      ->setTransport($transport)
      ->setTimeout(1)
      ->get('https://one.example/1');

    self::assertTrue($response->isFailed());
    self::assertSame('HTTP request exceeded the total timeout.', $response->error());
    self::assertCount(4, $transport->requests);
    self::assertSame([1000, 700, 400, 100], \array_map(
      static fn (TransportRequest $request):int => $request->timeoutMs,
      $transport->requests
    ));
  }

  public function testDnsResolutionConsumesTheTotalTimeoutBudget():void {
    $clock = new FakeClock();
    $resolver = new FakeDnsResolver(
      ['one.example' => ['93.184.216.34']],
      static function () use ($clock):void {
        $clock->advanceMs(1001);
      }
    );
    $transport = new FakeTransport([new TransportResponse(200, '{}')]);

    $response = (new ClockedClient($clock))
      ->setUrlPolicy(new UrlPolicy($resolver))
      ->setTransport($transport)
      ->setTimeout(1)
      ->get('https://one.example/');

    self::assertTrue($response->isFailed());
    self::assertSame('HTTP request exceeded the total timeout.', $response->error());
    self::assertCount(0, $transport->requests);
  }

  public function testHeaderInjectionIsRejectedBeforeTransport():void {
    $this->expectException(\InvalidArgumentException::class);
    (new Client())->addHeader('X-Test', "safe\r\nInjected: bad");
  }

  public function testJsonEncodingFailureIsNotSilentlySent():void {
    $recursive = [];
    $recursive['self'] = &$recursive;

    $this->expectException(\JsonException::class);
    (new Client())->postJson('https://example.com', $recursive);
  }
}
