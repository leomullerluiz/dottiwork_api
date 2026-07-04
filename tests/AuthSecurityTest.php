<?php

use PHPUnit\Framework\TestCase;

class AuthSecurityTest extends TestCase
{
    public function testGenerateSessionTokenIsOpaqueAndHashIsNotPlainToken(): void
    {
        $token = Auth::generateSessionToken();
        $hash = Auth::hashSessionToken($token);

        $this->assertIsString($token);
        $this->assertGreaterThanOrEqual(40, strlen($token));
        $this->assertNotSame($token, $hash);
        $this->assertSame(64, strlen($hash));
    }

    public function testOAuthReturnToRejectsExternalUrls(): void
    {
        $this->assertSame('/matches', GitHubOAuth::sanitizeReturnTo('https://evil.example/callback'));
        $this->assertSame('/matches', GitHubOAuth::sanitizeReturnTo('//evil.example/callback'));
        $this->assertSame('/matches', GitHubOAuth::sanitizeReturnTo('javascript:alert(1)'));
        $this->assertSame('/matches', GitHubOAuth::sanitizeReturnTo("/onboarding\r\nLocation: https://evil.example"));
        $this->assertSame('/matches', GitHubOAuth::sanitizeReturnTo('/\evil'));
        $this->assertSame('/onboarding', GitHubOAuth::sanitizeReturnTo('/onboarding'));
    }

    public function testRequestReadsAuthorizationBearerToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/auth/me';
        $_SERVER['SCRIPT_NAME'] = '/api/index.php';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc.def.token';
        $_GET = [];
        $_COOKIE = [];

        $request = new Request();

        $this->assertSame('abc.def.token', $request->getAuthorizationBearerToken());
        $this->assertNull($request->getHeader('key'));
    }

    public function testSessionCookieParamsUseSecureHttpOnlySameSiteAndDomain(): void
    {
        $_ENV['SESSION_COOKIE_DOMAIN'] = '.dottiwork.com';
        $_ENV['SESSION_COOKIE_SECURE'] = 'true';
        $_ENV['SESSION_COOKIE_SAMESITE'] = 'Strict';

        $params = Auth::sessionCookieParams(1234567890);

        $this->assertSame(1234567890, $params['expires']);
        $this->assertSame('/', $params['path']);
        $this->assertTrue($params['secure']);
        $this->assertTrue($params['httponly']);
        $this->assertSame('Strict', $params['samesite']);
        $this->assertSame('.dottiwork.com', $params['domain']);
    }

    public function testSameSiteNoneFallsBackToLaxWhenCookieIsNotSecure(): void
    {
        $_ENV['SESSION_COOKIE_DOMAIN'] = '';
        $_ENV['SESSION_COOKIE_SECURE'] = 'false';
        $_ENV['SESSION_COOKIE_SAMESITE'] = 'None';

        $params = Auth::sessionCookieParams(1234567890);

        $this->assertFalse($params['secure']);
        $this->assertSame('Lax', $params['samesite']);
        $this->assertArrayNotHasKey('domain', $params);
    }

    public function testCookieAuthRequestRequiresCookieAndNoBearerToken(): void
    {
        $_ENV['SESSION_COOKIE_NAME'] = 'dotti_session';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/me/preferences';
        $_SERVER['SCRIPT_NAME'] = '/api/index.php';
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $_GET = [];
        $_COOKIE = ['dotti_session' => 'opaque-token'];

        $cookieRequest = new Request();
        $this->assertTrue(Auth::isCookieAuthRequest($cookieRequest));

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer opaque-token';
        $bearerRequest = new Request();
        $this->assertFalse(Auth::isCookieAuthRequest($bearerRequest));
    }

    public function testRateLimiterKeyHashDoesNotStoreRawIp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/auth/github/start';
        $_SERVER['SCRIPT_NAME'] = '/api/index.php';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $_GET = [];
        $_COOKIE = [];

        $request = new Request();
        $hash = RateLimiter::keyHash($request, 'auth.github.start');

        $this->assertSame(64, strlen($hash));
        $this->assertStringNotContainsString('203.0.113.10', $hash);
        $this->assertNotSame($hash, RateLimiter::keyHash($request, 'auth.github.callback'));
    }
}
