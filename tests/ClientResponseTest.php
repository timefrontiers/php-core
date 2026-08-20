<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TimeFrontiers\Http\ClientResponse;

final class ClientResponseTest extends TestCase {

  public function testValidJsonNullIsDistinctFromInvalidJson():void {
    self::assertNull((new ClientResponse(200, 'null'))->json());

    $this->expectException(\JsonException::class);
    (new ClientResponse(200, '{invalid'))->json();
  }

  public function testJsonCacheRespectsAssociativeMode():void {
    $response = new ClientResponse(200, '{"value":1}');
    self::assertSame(['value' => 1], $response->json(true));
    self::assertInstanceOf(\stdClass::class, $response->json(false));
  }

  public function testXmlRejectsEntitiesAndOversizedInput():void {
    $entity = '<!DOCTYPE x [<!ENTITY file SYSTEM "file:///etc/passwd">]><x>&file;</x>';
    try {
      (new ClientResponse(200, $entity))->xml();
      self::fail('Entity-bearing XML should be rejected.');
    } catch (\UnexpectedValueException) {
      self::addToAssertionCount(1);
    }

    $this->expectException(\LengthException::class);
    (new ClientResponse(200, '<x>oversized</x>'))->xml(5);
  }

  /** @return iterable<string, array{string}> */
  public static function unsafeXmlDocuments():iterable {
    $documents = [
      'internal entity' => '<!DOCTYPE x [<!ENTITY value "EXPANDED">]><x>&value;</x>',
      'external entity' => '<!DOCTYPE x [<!ENTITY value SYSTEM "file:///etc/passwd">]><x>&value;</x>',
      'malformed document' => '<root><child></root>',
      'entity amplification' => '<!DOCTYPE lolz [<!ENTITY a "1234567890"><!ENTITY b "&a;&a;&a;&a;&a;&a;&a;&a;&a;&a;">]><lolz>&b;&b;&b;&b;&b;&b;&b;&b;&b;&b;</lolz>',
    ];

    foreach ($documents as $threat => $document) {
      yield "{$threat}, UTF-8" => [$document];
      yield "{$threat}, UTF-8 BOM" => ["\xEF\xBB\xBF{$document}"];
      yield "{$threat}, UTF-16LE" => [self::asciiUtf16($document, true, false)];
      yield "{$threat}, UTF-16LE BOM" => [self::asciiUtf16($document, true, true)];
      yield "{$threat}, UTF-16BE" => [self::asciiUtf16($document, false, false)];
      yield "{$threat}, UTF-16BE BOM" => [self::asciiUtf16($document, false, true)];
    }
  }

  #[DataProvider('unsafeXmlDocuments')]
  public function testXmlRejectsUnsafeDocumentsInEveryEncoding(string $document):void {
    $this->expectException(\UnexpectedValueException::class);
    (new ClientResponse(200, $document))->xml();
  }

  public function testXmlAcceptsUtf8WithOptionalBom():void {
    self::assertSame('safe', (string) (new ClientResponse(200, '<root>safe</root>'))->xml());
    self::assertSame('safe', (string) (new ClientResponse(200, "\xEF\xBB\xBF<root>safe</root>"))->xml());
    self::assertSame('safe', (string) (new ClientResponse(
      200,
      '<?xml version="1.0" encoding="UTF-8"?><root>safe</root>'
    ))->xml());
  }

  public function testXmlRejectsNonUtf8EncodingDeclaration():void {
    $this->expectException(\UnexpectedValueException::class);
    (new ClientResponse(
      200,
      '<?xml version="1.0" encoding="ISO-8859-1"?><root>safe</root>'
    ))->xml();
  }

  public function testXmlRestoresLibxmlErrorMode():void {
    $original = \libxml_use_internal_errors(false);
    try {
      (new ClientResponse(200, '<root/>'))->xml();
      self::assertFalse(\libxml_use_internal_errors());

      \libxml_use_internal_errors(true);
      try {
        (new ClientResponse(200, '<root>'))->xml();
      } catch (\UnexpectedValueException) {
        self::addToAssertionCount(1);
      }
      self::assertTrue(\libxml_use_internal_errors());
    } finally {
      \libxml_use_internal_errors($original);
    }
  }

  public function testOrdinaryArrayProjectionDoesNotExposePayloadOrDiagnostics():void {
    $response = new ClientResponse(
      500,
      'provider secret',
      ['Authorization' => 'secret'],
      'HTTP transport failed.',
      ['curl_error' => 'raw diagnostic']
    );

    $safe = $response->toArray();
    self::assertArrayNotHasKey('body', $safe);
    self::assertArrayNotHasKey('headers', $safe);
    self::assertArrayNotHasKey('error', $safe);
    self::assertArrayNotHasKey('diagnostic', $safe);
    self::assertSame('provider secret', $response->toDebugArray()['body']);
  }

  private static function asciiUtf16(string $value, bool $littleEndian, bool $bom):string {
    $encoded = '';
    foreach (\str_split($value) as $byte) {
      $encoded .= $littleEndian ? $byte . "\0" : "\0" . $byte;
    }
    if (!$bom) {
      return $encoded;
    }
    return ($littleEndian ? "\xFF\xFE" : "\xFE\xFF") . $encoded;
  }
}
