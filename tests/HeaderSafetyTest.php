<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TimeFrontiers\Http\Header;
use TimeFrontiers\Http\Http;

final class HeaderSafetyTest extends TestCase {

  /** @return iterable<string, array{string}> */
  public static function unsafeRedirects():iterable {
    yield 'line break' => ["/safe\r\nX-Bad: yes"];
    yield 'protocol relative' => ['//evil.example/path'];
    yield 'backslash authority' => ['\\\\evil.example/path'];
    yield 'javascript' => ['javascript:alert(1)'];
  }

  #[DataProvider('unsafeRedirects')]
  public function testUnsafeRedirectsAreRejected(string $location):void {
    $this->expectException(\InvalidArgumentException::class);
    Http::assertRedirectLocation($location);
  }

  public function testUnsafeDownloadFilenameIsRejected():void {
    $this->expectException(\InvalidArgumentException::class);
    Header::download("report.pdf\r\nX-Bad: yes");
  }

  public function testDownloadPathIsRejected():void {
    $this->expectException(\InvalidArgumentException::class);
    Header::download('../report.pdf');
  }

  public function testHeaderNameAndValueInjectionAreRejected():void {
    try {
      Header::set("Bad\r\nName", 'value');
      self::fail('Invalid header name should fail.');
    } catch (\InvalidArgumentException) {
      self::addToAssertionCount(1);
    }

    $this->expectException(\InvalidArgumentException::class);
    Header::set('X-Test', "value\nInjected: yes");
  }

  public function testJsonEncodingFailureOccursBeforeEmission():void {
    $recursive = [];
    $recursive['self'] = &$recursive;

    $this->expectException(\JsonException::class);
    Http::json($recursive);
  }

  public function testJsonpCallbackIsStrictlyValidated():void {
    $this->expectException(\InvalidArgumentException::class);
    Http::jsonp(['ok' => true], 'callback.alert(1)');
  }
}
