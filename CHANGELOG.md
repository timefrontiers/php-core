# Changelog

## 1.1.0 - 2026-08-20

- Require PHP 8.5, php-validator 1.1.1, php-has-errors, and the cURL/JSON/XML extensions used at runtime.
- Replace Request's silent validation fallback and pre-validation coercion with explicit `RequestValidationResult` objects backed by php-validator.
- Replace global/session-owned CSRF logic and the authenticated-user bypass with an injected `CsrfTokenManager` boundary and php-session adapter.
- Ignore forwarding headers by default; add immutable trusted-proxy and canonical-origin policies.
- Reject response-header injection, hostile redirect locations, and unsafe download filenames.
- Make outbound HTTP safe by default with a complete special-purpose network deny policy, per-hop DNS validation and pinning, one redirect-wide monotonic deadline, allowlist-based cross-origin header forwarding, bounded responses/redirects, and non-idempotent replay controls.
- Make JSON/XML parsing failure explicit, restrict XML to UTF-8 without DTD/entities, deprecate JSONP, and separate safe response projections from trusted diagnostics.
- Add PHPUnit, PHPStan level 8, parallel lint, Composer validation/audit, and PHP 8.5 CI gates.

## 1.0.3

- Last tagged 1.0 release. See the repository history for earlier changes.
