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
}
