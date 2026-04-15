# TimeFrontiers PHP Core

Core utilities, enums, and helpers for TimeFrontiers packages.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## Installation

```bash
composer require timefrontiers/php-core
```

## Requirements

- PHP 8.1+
- ext-curl (for URL utilities)

## Package Contents

| Class | Description |
|-------|-------------|
| `AccessRank` | Enum for user access levels (int-backed) |
| `AccessGroup` | Enum for user access groups (string-backed) |
| `HttpStatus` | Enum for HTTP status codes |
| `Http` | HTTP utilities (redirect, JSON responses, headers) |
| `Header` | HTTP header utilities (status, redirects, caching) |
| `Client` | HTTP client using cURL |
| `Request` | Request parameter handling with validation |
| `Url` | URL manipulation utilities |
| `Str` | String utilities |
| `Time` | Date/time utilities |
| `Generic` | Legacy compatibility class |
| `BetaTym` | Legacy compatibility class |

---

## AccessRank Enum

User access levels for permission control and error visibility filtering.

```php
use TimeFrontiers\AccessRank;

// Get rank
$rank = AccessRank::USER;
echo $rank->value;  // 1
echo $rank->label(); // "User"

// Compare ranks
if ($user_rank->atLeast(AccessRank::MODERATOR)) {
  // User is staff or higher
}

// Check capabilities
$rank->isStaff();     // true for MODERATOR+
$rank->isTechnical(); // true for DEVELOPER+
$rank->isAdmin();     // true for ADMIN+

// Error visibility
if ($rank->canSee($error_min_rank)) {
  // User can see this error
}

// Get all options (for dropdowns)
$options = AccessRank::options();
// [0 => 'Guest', 1 => 'User', ...]
```

### Rank Values

| Rank | Value | Description |
|------|-------|-------------|
| GUEST | 0 | Public users |
| USER | 1 | Logged in users |
| ANALYST | 2 | Data analysts |
| ADVERTISER | 3 | Advertisers |
| MODERATOR | 4 | Staff (internal errors) |
| EDITOR | 5 | Content editors |
| ADMIN | 6 | Administrators |
| DEVELOPER | 7 | Developers (system errors) |
| SUPERADMIN | 8 | Super admins (debug errors) |
| OWNER | 14 | Full access |

---

## AccessGroup Enum

String-backed version of AccessRank for database storage.

```php
use TimeFrontiers\AccessGroup;
use TimeFrontiers\AccessRank;

// Get group
$group = AccessGroup::USER;
echo $group->value;      // "USER"
echo $group->label();    // "User"
echo $group->rankValue(); // 1

// Convert between rank and group
$rank = $group->toRank();              // AccessRank::USER
$group = AccessGroup::fromRank($rank); // AccessGroup::USER

// Check capabilities (same as AccessRank)
$group->isStaff();     // true for MODERATOR+
$group->isTechnical(); // true for DEVELOPER+
$group->isAdmin();     // true for ADMIN+

// Compare groups
if ($group->atLeast(AccessGroup::ADMIN)) {
  // User is admin or higher
}

// Get all options (for dropdowns)
$options = AccessGroup::options();
// ['GUEST' => 'Guest', 'USER' => 'User', ...]
```

---

## HttpStatus Enum

HTTP status codes with helper methods.

```php
use TimeFrontiers\Http\HttpStatus;

// Use status code
$status = HttpStatus::NOT_FOUND;
echo $status->value;   // 404
echo $status->phrase(); // "Not Found"
echo $status->line();   // "404 Not Found"

// Send as header
$status->send();

// Check status type
$status->isSuccess();     // 2xx
$status->isRedirect();    // 3xx
$status->isClientError(); // 4xx
$status->isServerError(); // 5xx
$status->isError();       // 4xx or 5xx

// Get from code
$status = HttpStatus::fromCode(500);
```

---

## Http Utilities

```php
use TimeFrontiers\Http\Http;
use TimeFrontiers\Http\HttpStatus;

// Redirect (exits)
Http::redirect('/dashboard');
Http::redirect('/login', HttpStatus::SEE_OTHER);

// JSON responses (exit)
Http::json(['user' => $user]);
Http::success($data, 'User created');
Http::error('Validation failed', HttpStatus::BAD_REQUEST, $errors);

// Request info
$ip = Http::clientIp();
$method = Http::method();
$url = Http::currentUrl();

// Check request type
Http::isMethod('POST');
Http::isAjax();
Http::isSecure();
Http::accepts('application/json');

// Headers
$auth = Http::header('Authorization');
$headers = Http::headers();
Http::setHeader('X-Custom', 'value');

// CORS
Http::cors('https://example.com', ['GET', 'POST']);

// Disable caching
Http::noCache();
```

---

## Request Handling

```php
use TimeFrontiers\Http\Request;

// Create from different sources
$request = Request::fromPost();
$request = Request::fromGet();
$request = Request::fromJson();
$request = Request::fromArray($data);

// Get parameters
$email = $request->get('email');
$page = $request->get('page', 1);
$request->has('email'); // true/false

// Filter parameters
$allowed = $request->only(['name', 'email', 'phone']);
$filtered = $request->except(['password', 'token']);

// Validate parameters
$columns = [
  'email' => ['Email', 'email'],
  'name' => ['Name', 'text', 2, 100],
  'age' => ['Age', 'int', 18, 120],
];

$required = ['email', 'name'];

$params = $request->validate($columns, $required);

if ($params === false) {
  $errors = $request->getErrors();
}

// CSRF protection
Request::csrfField('contact_form'); // Returns HTML input

// In form handler:
$token = $request->get('csrf_token');
if (!$request->verifyCSRF('contact_form', $token)) {
  $errors = $request->getErrors();
}
```

---

## Http Client

cURL-based HTTP client for making API requests.

```php
use TimeFrontiers\Http\Client;

// Create client
$client = new Client('https://api.example.com');

// Or configure manually
$client = Client::create()
  ->setBaseUrl('https://api.example.com')
  ->setHeaders(['Authorization' => 'Bearer token'])
  ->setTimeout(30)
  ->verifySsl(true);

// GET request
$response = $client->get('/users', ['page' => 1]);

// POST with form data
$response = $client->post('/users', ['name' => 'John', 'email' => 'john@example.com']);

// POST with JSON body
$response = $client->postJson('/users', ['name' => 'John']);

// PUT/PATCH/DELETE
$response = $client->putJson('/users/1', ['name' => 'Jane']);
$response = $client->patchJson('/users/1', ['status' => 'active']);
$response = $client->delete('/users/1');

// Handle response
if ($response->isSuccess()) {
  $data = $response->json();
  $name = $response->get('data.user.name');
} else {
  $error = $response->get('error');
  $code = $response->statusCode();
}

// Response methods
$response->isOk();          // true if 200
$response->isSuccess();     // true if 2xx
$response->isError();       // true if 4xx or 5xx
$response->isJson();        // true if JSON content type
$response->body();          // raw body string
$response->json();          // decoded JSON
$response->header('Content-Type');
$response->throwIfError();  // throws on error
```

---

## Http Header

HTTP header utilities for responses.

```php
use TimeFrontiers\Http\Header;
use TimeFrontiers\Http\HttpStatus;

// Send status and exit
Header::send(HttpStatus::OK);
Header::send(404);

// Redirects
Header::redirect('/dashboard');
Header::redirect('/login', 'Please log in first');
Header::refresh('/dashboard', 5, 'Redirecting in 5 seconds...');

// Error pages (exit)
Header::notFound();
Header::badRequest();
Header::unauthorized();
Header::forbidden();
Header::internalError();

// Error pages with redirect to /app/{code}
Header::notFound(redirect: true, message: 'Page not found');

// Authentication
Header::authDialog('Admin Area', 'Access denied');

// Caching
Header::noCache();
Header::cache(3600);          // 1 hour private cache
Header::cache(86400, true);   // 1 day public cache

// Content headers
Header::contentType('application/pdf');
Header::contentLength(1024);
Header::download('report.pdf');
Header::language('en');

// Security headers
Header::security();  // Common security headers
Header::hsts();      // Strict-Transport-Security
Header::csp([
  'default-src' => "'self'",
  'script-src' => "'self' 'unsafe-inline'",
]);

// Custom headers
Header::set('X-Custom', 'value');
Header::setCustom('Request-Id', '123');  // X-Tym-Request-Id
Header::poweredBy('TimeFrontiers');

// Get custom X-Tym headers from response
$custom = Header::getCustom();           // All X-Tym headers
$value = Header::getCustom('Request-Id');
```

---

## Time (Date/Time Utilities)

Date and time formatting and manipulation.

```php
use TimeFrontiers\Time;

// Current time
Time::now();        // "2024-01-15 14:30:00" (MySQL format)
Time::today();      // "2024-01-15"
Time::timestamp();  // Unix timestamp

// Formatting
Time::format('Y-m-d', '2024-01-15 14:30:00');
Time::day('2024-01-15');      // "15th"
Time::month('2024-01-15');    // "January"
Time::month('2024-01-15', true); // "Jan"
Time::year('2024-01-15');     // "2024"
Time::weekday('2024-01-15');  // "Monday"
Time::week('2024-01-15');     // 3 (week of year)

// Combined formats
Time::monthDay('2024-01-15');      // "January 15th"
Time::mdy('2024-01-15');           // "January 15th, 2024"
Time::hms('2024-01-15 14:30:00');  // "14:30:00"
Time::hm('2024-01-15 14:30:00');   // "14:30"
Time::hm('...', true);             // "2:30 PM" (12-hour)
Time::dateTym('2024-01-15 14:30:00'); // "January 15th, 2024 at 14:30:00"
Time::weekDateTym('2024-01-15');  // "Monday, 15th January 2024"

// Relative time
Time::relative('2024-01-14');     // "1 day ago"
Time::relative('2024-01-20');     // "in 5 days"

// Calculations
Time::add('P1D');                 // Add 1 day
Time::add('PT2H', '2024-01-15');  // Add 2 hours
Time::sub('P1M');                 // Subtract 1 month
Time::diff('2024-01-01', '2024-01-15'); // DateInterval
Time::diffSeconds('2024-01-01', '2024-01-15'); // Seconds

// Comparisons
Time::isPast('2024-01-01');       // true
Time::isFuture('2025-01-01');     // true
Time::isToday('2024-01-15');      // true/false
Time::isSameDay('...', '...');    // true/false

// Conversion
Time::toTimestamp('2024-01-15');  // Unix timestamp
Time::toDateTime('2024-01-15');   // DateTime object
Time::toMysql(1705320600);        // MySQL datetime
Time::toIso('2024-01-15');        // ISO 8601 format
```

---

## URL Utilities

```php
use TimeFrontiers\Url;

// Add/update query parameters
$url = Url::withParams('/search', ['q' => 'test', 'page' => 2]);
// "/search?q=test&page=2"

// Remove parameters
$url = Url::withoutParams($url, ['page']);
// "/search?q=test"

// Get parameters
$query = Url::getParam($url, 'q');       // "test"
$params = Url::getParams($url);          // ['q' => 'test']

// Check URL status
Url::exists('https://example.com');      // true/false (200 only)
Url::isAccessible('https://example.com'); // true/false (2xx/3xx)
$code = Url::getStatusCode('https://example.com');

// Parse and build
$parts = Url::parse('https://user:pass@example.com:8080/path?q=1#hash');
$url = Url::build($parts);

// Validation
Url::isValid('https://example.com'); // true
Url::getDomain('https://example.com/path'); // "example.com"
Url::normalize('example.com'); // "https://example.com"
```

---

## String Utilities

```php
use TimeFrontiers\Str;

// Parse email with name
$parsed = Str::parseEmailName('John Doe <john@example.com>');
// ['name' => 'John', 'surname' => 'Doe', 'email' => 'john@example.com']

// File extension
Str::fileExtension('document.pdf');           // "pdf"
Str::fileExtension('image.jpg?v=123');        // "jpg"

// Base64
Str::isBase64('SGVsbG8gV29ybGQ=');             // true
Str::base64Decode('SGVsbG8gV29ybGQ=');         // "Hello World"

// Slugify
Str::slug('Hello World!');                     // "hello-world"
Str::slug('Hello World!', '_');                // "hello_world"

// Truncate
Str::truncate('Long text here', 10);           // "Long te..."
Str::truncateWords('Long text here', 10);      // "Long..."
Str::limitWords('One two three four', 2);      // "One two..."

// Case conversion
Str::toCamelCase('hello_world');               // "helloWorld"
Str::toPascalCase('hello_world');              // "HelloWorld"
Str::toSnakeCase('helloWorld');                // "hello_world"
Str::toKebabCase('helloWorld');                // "hello-world"

// Masking
Str::mask('4111111111111111', 4, 4);           // "4111********1111"

// String checks
Str::contains('Hello World', 'World');         // true
Str::startsWith('Hello World', 'Hello');       // true
Str::endsWith('Hello World', 'World');         // true

// Word count
Str::wordCount('Hello World');                 // 2

// Excerpt
Str::excerpt('Long text with keyword here', 'keyword', 10);
// "...with keyword her..."

// Pattern replacement
Str::patternReplace(
  ['{{name}}' => '{{name}}', '{{date}}' => '{{date}}'],
  ['{{name}}' => 'John', '{{date}}' => '2024-01-15'],
  'Hello {{name}}, today is {{date}}'
);
// "Hello John, today is 2024-01-15"

// Random string
Str::random(16);                               // "aB3xKm9pQr2sT1uV"
```

---

## Legacy Compatibility

For backward compatibility, legacy classes map old methods to new utilities:

### Generic Class

```php
use TimeFrontiers\Generic;

// These still work but are deprecated:
Generic::redirect('/path');          // Use Http::redirect()
Generic::setGet($url, $params);      // Use Url::withParams()
Generic::allowedParam($keys, 'get'); // Use Request::only()
Generic::splitEmailName($string);    // Use Str::parseEmailName()
Generic::fileExt($filename);         // Use Str::fileExtension()
Generic::urlExist($url);             // Use Url::exists()
Generic::patternReplace(...);        // Use Str::patternReplace()
Generic::isBase64($data);            // Use Str::isBase64()

// Instance methods
$generic = new Generic();
$generic->requestParam(...);         // Use Request->validate()
$generic->checkCSRF(...);            // Use Request->verifyCSRF()
```

### BetaTym Class

```php
use TimeFrontiers\BetaTym;

// These still work but are deprecated:
BetaTym::now();           // Use Time::now()
BetaTym::day($date);      // Use Time::day()
BetaTym::month($date);    // Use Time::month()
BetaTym::year($date);     // Use Time::year()
BetaTym::MDY($date);      // Use Time::mdy()
BetaTym::HMS($date);      // Use Time::hms()
BetaTym::dateTym($date);  // Use Time::dateTym()
BetaTym::seconds($date);  // Use Time::toTimestamp()
```

### Legacy Constants

```php
HTTP_OK            // 200
HTTP_CREATED       // 201
HTTP_BAD_REQUEST   // 400
HTTP_UNAUTHORIZED  // 401
HTTP_FORBIDDEN     // 403
HTTP_NOT_FOUND     // 404
HTTP_INTERNAL_ERROR // 500
```

---

## Migration Guide

### From Generic to New Classes

| Old | New |
|-----|-----|
| `Generic::redirect($url)` | `Http::redirect($url)` |
| `Generic::setGet($url, $params)` | `Url::withParams($url, $params)` |
| `Generic::allowedParam($keys, 'post')` | `Request::fromPost()->only($keys)` |
| `Generic::splitEmailName($str)` | `Str::parseEmailName($str)` |
| `Generic::fileExt($file)` | `Str::fileExtension($file)` |
| `Generic::urlExist($url)` | `Url::exists($url)` |
| `Generic::isBase64($data)` | `Str::isBase64($data)` |
| `$gen->requestParam(...)` | `$req->validate(...)` |
| `$gen->checkCSRF(...)` | `$req->verifyCSRF(...)` |

### From BetaTym to Time

| Old | New |
|-----|-----|
| `BetaTym::now()` | `Time::now()` |
| `BetaTym::day($d)` | `Time::day($d)` |
| `BetaTym::month($d)` | `Time::month($d)` |
| `BetaTym::year($d)` | `Time::year($d)` |
| `BetaTym::MDY($d)` | `Time::mdy($d)` |
| `BetaTym::HMS($d)` | `Time::hms($d)` |
| `BetaTym::dateTym($d)` | `Time::dateTym($d)` |
| `BetaTym::weekDay($d)` | `Time::weekday($d)` |
| `BetaTym::seconds($d)` | `Time::toTimestamp($d)` |

### From HTTP\Client to Http\Client

| Old | New |
|-----|-----|
| `new Client(Client::GET, $url)` | `(new Client())->get($url)` |
| `$client->body()` | `$response->body()` |
| `$client->statusCode()` | `$response->statusCode()` |
| `$client->setOpt('raw_param', 'json')` | `$client->postJson(...)` |

### From HTTP\Header to Http\Header

| Old | New |
|-----|-----|
| `Header::send(404)` | `Header::send(HttpStatus::NOT_FOUND)` |
| `Header::notFound(true)` | `Header::notFound(redirect: true)` |
| `Header::getSpecial()` | `Header::getCustom()` |

### From Constants to Enum

```php
// Old
http_response_code(HTTP_NOT_FOUND);

// New
use TimeFrontiers\Http\HttpStatus;
HttpStatus::NOT_FOUND->send();
```

---

## License

MIT License
