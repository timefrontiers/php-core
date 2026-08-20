# Upgrading TimeFrontiers PHP Core

## 1.0.x to 1.1.0

PHP 8.5 is required. Request validation now directly requires
`timefrontiers/php-validator:^1.1.1`, and Core declares its existing HasErrors
usage directly.

### Request validation

`Request::validate()` remains available and retains its `array|false` result,
but now fails closed on every configured rule error. The old `$strict = false`
behavior no longer allowed invalid optional values through. Prefer
`validateResult()` for an explicit result with `passes()`, `validated()`,
`errors()`, and `messages()`.

Every column must declare `[label, rule, ...parameters]`. Missing or unknown
rules are configuration errors. Numeric and boolean inputs are validated before
normalization, so malformed numerics are rejected and strings such as `"false"`
and `"0"` normalize correctly.

### CSRF

Core no longer reads global session state or `$_SESSION`, and authenticated
requests receive no bypass. Wrap php-session 1.1 with
`SessionCsrfAdapter` and inject it into the Request:

```php
$csrf = new SessionCsrfAdapter($session);
$request = Request::fromPost($csrf);
$request->verifyCSRF('profile-update', $submittedToken);
```

The deprecated static generation/field helpers require an explicit manager.
Calls that relied on the old implicit global state now throw until migrated.

### Inbound proxy and origin trust

`Http::clientIp()` and `Http::isSecure()` use only `REMOTE_ADDR` and direct TLS
state by default. Supply a `TrustedProxyConfig` when the host has a known proxy
range. `Http::currentUrl()` ignores `HTTP_HOST` unless an `OriginPolicy`
explicitly allowlists it; otherwise it uses the server-configured name.

Error-page redirects are relative by default. Call
`Header::configureErrorOrigin()` only when an absolute canonical redirect is
needed.

### Outbound HTTP and URL probes

Client and `Url::{exists,isAccessible,getStatusCode}` now reject private,
loopback, link-local, multicast, transition/translation, protocol-assignment,
documentation, benchmarking, and every other IANA special-purpose destination.
DNS is rechecked and pinned at every redirect. Applications deliberately
calling internal services must inject `UrlPolicy::trusted()` or a separately
constructed trusted policy; never use that escape hatch for request-derived
URLs.

`setTimeout()` is one monotonic deadline covering DNS and all redirect hops.
Only a shrinking remaining budget reaches each transport call. Synchronous
platform DNS cannot be interrupted mid-call, but its elapsed time is charged
and no connection begins if it exhausts the deadline.

Cross-origin redirects now retain only standard content-negotiation headers.
All credentials and caller-defined fields are removed by default. Use
`allowCrossOriginHeaders()` only for a reviewed, non-sensitive header that the
target origin is intended to receive.

Malformed JSON now throws `JsonException`; valid JSON `null` still returns
`null`. XML parsing accepts UTF-8 only (with an optional UTF-8 BOM) and throws
for unsupported encodings, malformed, oversized, DTD, or entity-bearing input.
`ClientResponse::toArray()` no longer includes headers, response bodies, raw
errors, or decoded payloads. Trusted diagnostic code may opt into
`toDebugArray()`.

JSONP is deprecated. Redirect/header/download helpers now throw
`InvalidArgumentException` for unsafe values.
