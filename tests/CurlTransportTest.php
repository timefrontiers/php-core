<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests;

use PHPUnit\Framework\TestCase;
use TimeFrontiers\Http\CurlTransport;
use TimeFrontiers\Http\ResolvedUrl;
use TimeFrontiers\Http\TransportRequest;

final class CurlTransportTest extends TestCase {

  /** @var resource|null */
  private static $process = null;
  private static int $port;

  public static function setUpBeforeClass():void {
    $socket = \stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if ($socket === false) {
      self::fail("Could not reserve a fixture port: {$errorCode} {$errorMessage}");
    }
    $address = \stream_socket_get_name($socket, false);
    \fclose($socket);
    if (!\is_string($address) || !\str_contains($address, ':')) {
      self::fail('Could not determine the fixture port.');
    }
    self::$port = (int) \substr($address, (int) \strrpos($address, ':') + 1);

    $fixture = __DIR__ . '/fixtures/http-server.php';
    $command = [PHP_BINARY, '-S', '127.0.0.1:' . self::$port, $fixture];
    $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w'],
    ];
    $process = \proc_open($command, $descriptors, $pipes, __DIR__);
    if (!\is_resource($process)) {
      self::fail('Could not start the local HTTP fixture.');
    }
    self::$process = $process;
    foreach ($pipes as $pipe) {
      \fclose($pipe);
    }

    $started = false;
    for ($attempt = 0; $attempt < 40; $attempt++) {
      $connection = @\fsockopen('127.0.0.1', self::$port, $errno, $error, 0.05);
      if (\is_resource($connection)) {
        \fclose($connection);
        $started = true;
        break;
      }
      \usleep(25000);
    }
    if (!$started) {
      self::tearDownAfterClass();
      self::fail('The local HTTP fixture did not start.');
    }
  }

  public static function tearDownAfterClass():void {
    if (\is_resource(self::$process)) {
      \proc_terminate(self::$process);
      \proc_close(self::$process);
      self::$process = null;
    }
  }

  public function testBodyLimitAbortsCurlTransfer():void {
    $response = (new CurlTransport())->send($this->request('/large-body', 4096, 32, 1000));

    self::assertSame('HTTP response body exceeded the configured limit.', $response->error);
    self::assertLessThanOrEqual(32, \strlen($response->body));
  }

  public function testHeaderLimitAbortsCurlTransfer():void {
    $response = (new CurlTransport())->send($this->request('/large-header', 1024, 4096, 1000));

    self::assertSame('HTTP response headers exceeded the configured limit.', $response->error);
  }

  public function testTotalTimeoutAbortsCurlTransfer():void {
    $response = (new CurlTransport())->send($this->request('/slow', 4096, 4096, 100));

    self::assertSame('HTTP transport failed.', $response->error);
    self::assertSame(CURLE_OPERATION_TIMEDOUT, $response->diagnostic['curl_errno']);
  }

  private function request(
    string $path,
    int $maxHeaderBytes,
    int $maxBodyBytes,
    int $timeoutMs
  ):TransportRequest {
    $url = 'http://fixture.test:' . self::$port . $path;
    return new TransportRequest(
      'GET',
      new ResolvedUrl($url, 'http', 'fixture.test', self::$port, ['127.0.0.1']),
      [],
      null,
      500,
      $timeoutMs,
      $maxHeaderBytes,
      $maxBodyBytes,
      true,
      'php-core-test'
    );
  }
}
