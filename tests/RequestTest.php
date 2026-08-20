<?php

declare(strict_types=1);

namespace TimeFrontiers\Tests;

use PHPUnit\Framework\TestCase;
use TimeFrontiers\Http\Request;
use TimeFrontiers\Http\SessionCsrfAdapter;
use TimeFrontiers\Tests\Support\InMemoryCsrfManager;

final class RequestTest extends TestCase {

  public function testValidationPreservesZeroAndValidatesBeforeCoercion():void {
    $request = Request::fromArray([
      'text' => '0',
      'enabled' => 'false',
      'count' => '12oops',
      'amount' => '2.5oops',
    ]);

    $result = $request->validateResult([
      'text' => ['Text', 'text'],
      'enabled' => ['Enabled', 'boolean'],
      'count' => ['Count', 'int'],
      'amount' => ['Amount', 'float'],
    ], ['text', 'enabled', 'count', 'amount']);

    self::assertTrue($request->has('text'));
    self::assertTrue($request->has('enabled'));
    self::assertTrue($result->fails());
    self::assertSame('0', $result->validated()['text']);
    self::assertFalse($result->validated()['enabled']);
    self::assertArrayHasKey('count', $result->errors());
    self::assertArrayHasKey('amount', $result->errors());
    self::assertFalse($request->validate([
      'count' => ['Count', 'int'],
    ], ['count']));
  }

  public function testNativeFalseIsAValidRequiredBoolean():void {
    $result = Request::fromArray(['enabled' => false])->validateResult([
      'enabled' => ['Enabled', 'boolean'],
    ], ['enabled']);

    self::assertTrue($result->passes());
    self::assertSame(['enabled' => false], $result->validated());
  }

  public function testAbsentRuleIsAConfigurationFailure():void {
    $this->expectException(\InvalidArgumentException::class);
    Request::fromArray(['name' => 'Ada'])->validateResult([
      'name' => ['Name'],
    ]);
  }

  public function testRequiredFieldWithoutAColumnIsAConfigurationFailure():void {
    $this->expectException(\InvalidArgumentException::class);
    Request::fromArray([])->validateResult([], ['missing']);
  }

  public function testCsrfRequiresAnExplicitAdapter():void {
    $request = Request::fromArray([]);

    self::assertFalse($request->verifyCSRF('profile', 'token'));
    self::assertTrue($request->hasErrors('verifyCSRF'));
  }

  public function testCsrfHasNoAuthenticatedBypassAndEnforcesActionExpiryAndReplay():void {
    $csrf = new InMemoryCsrfManager();
    $request = Request::fromArray([], $csrf);
    $token = $csrf->issue('profile', 10);

    self::assertFalse($request->verifyCSRF('other-action', $token));
    self::assertTrue($request->verifyCSRF('profile', $token));
    self::assertFalse($request->verifyCSRF('profile', $token), 'Tokens must be consumed.');

    $expired = $csrf->issue('profile', 10);
    $csrf->now += 11;
    self::assertFalse($request->verifyCSRF('profile', $expired));
    self::assertFalse($request->verifyCSRF('profile', "bad\0token"));
  }

  public function testSessionAdapterCallsValidationEvenForALoggedInSession():void {
    $session = new class {
      public int $validations = 0;
      public function isLoggedIn():bool {
        return true;
      }
      public function generateCSRFToken(string $action, int $ttl):string {
        return "{$action}:{$ttl}";
      }
      public function validateCSRFToken(string $action, string $token):bool {
        $this->validations++;
        return false;
      }
    };
    $request = Request::fromArray([], new SessionCsrfAdapter($session));

    self::assertFalse($request->verifyCSRF('profile', 'bad'));
    self::assertSame(1, $session->validations);
  }

  public function testCsrfFieldEscapesNameAndToken():void {
    $csrf = new class implements \TimeFrontiers\Http\CsrfTokenManager {
      public function issue(string $action, int $ttl = 3600):string {
        return 'token" onfocus="bad';
      }
      public function verify(string $action, string $token):bool {
        return false;
      }
    };

    $html = Request::csrfField('form', 'field" autofocus="bad', 10, $csrf);
    self::assertStringNotContainsString('autofocus="bad', $html);
    self::assertStringContainsString('&quot;', $html);
  }

  public function testLegacyCsrfHelperFailsExplicitlyWithoutConfiguration():void {
    $this->expectException(\LogicException::class);
    Request::generateCSRF('form');
  }
}
