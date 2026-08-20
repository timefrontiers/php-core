<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests;

use PHPUnit\Framework\TestCase;
use TimeFrontiers\Http\Http;
use TimeFrontiers\Http\OriginPolicy;
use TimeFrontiers\Http\TrustedProxyConfig;

final class HttpTrustTest extends TestCase {

  public function testMalformedTrustedProxyRangeIsRejected():void {
    $this->expectException(\InvalidArgumentException::class);
    new TrustedProxyConfig(['10.0.0.0/not-a-prefix']);
  }

  public function testCanonicalOriginRejectsEmbeddedCredentials():void {
    $this->expectException(\InvalidArgumentException::class);
    new OriginPolicy('https://user@app.example.com');
  }

  public function testForwardingHeadersAreIgnoredByDefault():void {
    $server = [
      'REMOTE_ADDR' => '198.51.100.10',
      'HTTP_X_FORWARDED_FOR' => '203.0.113.8',
      'HTTP_X_FORWARDED_PROTO' => 'https',
      'SERVER_PORT' => '80',
    ];

    self::assertSame('198.51.100.10', Http::clientIp(null, $server));
    self::assertFalse(Http::isSecure(null, $server));
  }

  public function testTrustedProxyChainResolvesFromTheImmediatePeerBackwards():void {
    $proxies = new TrustedProxyConfig(['10.0.0.0/8']);
    $server = [
      'REMOTE_ADDR' => '10.0.0.2',
      'HTTP_X_FORWARDED_FOR' => '203.0.113.8, 10.0.0.1',
      'HTTP_X_FORWARDED_PROTO' => 'https',
      'SERVER_PORT' => '80',
    ];

    self::assertSame('203.0.113.8', Http::clientIp($proxies, $server));
    self::assertTrue(Http::isSecure($proxies, $server));
  }

  public function testMalformedProxyChainFallsBackToDirectPeer():void {
    $proxies = new TrustedProxyConfig(['10.0.0.0/8']);
    $server = [
      'REMOTE_ADDR' => '10.0.0.2',
      'HTTP_X_FORWARDED_FOR' => '203.0.113.8, not-an-ip',
      'HTTP_X_FORWARDED_PROTO' => 'https,http',
      'SERVER_PORT' => '80',
    ];

    self::assertSame('10.0.0.2', Http::clientIp($proxies, $server));
    self::assertFalse(Http::isSecure($proxies, $server));
  }

  public function testCurrentUrlUsesCanonicalOriginAgainstHostInjection():void {
    $policy = new OriginPolicy('https://app.example.com', ['tenant.example.com']);
    $server = [
      'REMOTE_ADDR' => '198.51.100.10',
      'HTTP_HOST' => "evil.example\r\nLocation: https://evil.example",
      'REQUEST_URI' => '/account?tab=profile',
      'HTTPS' => 'on',
    ];
    self::assertSame(
      'https://app.example.com/account?tab=profile',
      Http::currentUrl($policy, null, $server)
    );

    $server['HTTP_HOST'] = 'tenant.example.com';
    self::assertSame(
      'https://tenant.example.com/account?tab=profile',
      Http::currentUrl($policy, null, $server)
    );

    $server['HTTP_HOST'] = 'tenant.example.com:4444';
    self::assertSame(
      'https://app.example.com/account?tab=profile',
      Http::currentUrl($policy, null, $server),
      'An allowlisted host must not authorize an arbitrary port.'
    );
  }

  public function testCurrentUrlNeverUsesHttpHostWithoutPolicy():void {
    $server = [
      'SERVER_NAME' => 'configured.example.com',
      'HTTP_HOST' => 'evil.example',
      'REQUEST_URI' => '/',
      'SERVER_PORT' => '80',
    ];
    self::assertSame('http://configured.example.com/', Http::currentUrl(null, null, $server));
  }
}
