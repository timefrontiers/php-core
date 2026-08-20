<?php

declare(strict_types=1);

namespace TimeFrontiers\Http;

/** Bounded cURL transport. Redirects are deliberately owned by Client. */
final class CurlTransport implements Transport {

  public function send(TransportRequest $request):TransportResponse {
    if (!\function_exists('curl_init')) {
      return new TransportResponse(0, '', [], 'HTTP transport is unavailable.');
    }

    $handle = \curl_init();
    if ($handle === false) {
      return new TransportResponse(0, '', [], 'HTTP transport could not be initialized.');
    }

    $headers = [];
    $body = '';
    $headerBytes = 0;
    $bodyExceeded = false;
    $headersExceeded = false;
    $headerLines = [];
    foreach ($request->headers as $name => $value) {
      $headerLines[] = "{$name}: {$value}";
    }

    $resolve = [];
    if (\filter_var($request->destination->host, FILTER_VALIDATE_IP) === false) {
      foreach ($request->destination->addresses as $address) {
        $formatted = \str_contains($address, ':') ? "[{$address}]" : $address;
        $resolve[] = "{$request->destination->host}:{$request->destination->port}:{$formatted}";
      }
    }

    \curl_setopt_array($handle, [
      CURLOPT_URL => $request->destination->url,
      CURLOPT_RETURNTRANSFER => false,
      CURLOPT_FOLLOWLOCATION => false,
      CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
      CURLOPT_CONNECTTIMEOUT_MS => $request->connectTimeoutMs,
      CURLOPT_TIMEOUT_MS => $request->timeoutMs,
      CURLOPT_SSL_VERIFYPEER => $request->verifySsl,
      CURLOPT_SSL_VERIFYHOST => $request->verifySsl ? 2 : 0,
      CURLOPT_USERAGENT => $request->userAgent,
      CURLOPT_HTTPHEADER => $headerLines,
      CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers, &$headerBytes, &$headersExceeded, $request):int {
        unset($handle);
        $length = \strlen($line);
        $headerBytes += $length;
        if ($headerBytes > $request->maxHeaderBytes) {
          $headersExceeded = true;
          return 0;
        }

        if (\str_starts_with($line, 'HTTP/')) {
          $headers = [];
          return $length;
        }
        if (\str_contains($line, ':')) {
          [$name, $value] = \explode(':', $line, 2);
          $name = \trim($name);
          $value = \trim($value);
          if ($name !== '') {
            $headers[$name] = isset($headers[$name]) ? $headers[$name] . ', ' . $value : $value;
          }
        }
        return $length;
      },
      CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$bodyExceeded, $request):int {
        unset($handle);
        if (\strlen($body) + \strlen($chunk) > $request->maxBodyBytes) {
          $bodyExceeded = true;
          return 0;
        }
        $body .= $chunk;
        return \strlen($chunk);
      },
    ]);
    if ($resolve !== []) {
      \curl_setopt($handle, CURLOPT_RESOLVE, $resolve);
    }

    $method = \strtoupper($request->method);
    if ($method === Client::GET) {
      \curl_setopt($handle, CURLOPT_HTTPGET, true);
    } elseif ($method === Client::POST) {
      \curl_setopt($handle, CURLOPT_POST, true);
    } elseif ($method === Client::HEAD) {
      \curl_setopt($handle, CURLOPT_NOBODY, true);
    } else {
      \curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
    }
    if ($request->body !== null) {
      \curl_setopt($handle, CURLOPT_POSTFIELDS, $request->body);
    }

    $success = \curl_exec($handle);
    $status = (int) \curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $errno = \curl_errno($handle);
    $curlError = \curl_error($handle);
    $duration = (float) \curl_getinfo($handle, CURLINFO_TOTAL_TIME);
    unset($handle);

    $error = null;
    if ($headersExceeded) {
      $error = 'HTTP response headers exceeded the configured limit.';
    } elseif ($bodyExceeded) {
      $error = 'HTTP response body exceeded the configured limit.';
    } elseif ($success === false || $errno !== 0) {
      $error = 'HTTP transport failed.';
    }

    return new TransportResponse($status, $body, $headers, $error, [
      'curl_errno' => $errno,
      'curl_error' => $curlError,
      'total_time' => $duration,
      'header_bytes' => $headerBytes,
      'body_bytes' => \strlen($body),
    ]);
  }
}
