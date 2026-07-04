<?php

use PHPUnit\Framework\TestCase;

class GitHubDisconnectServiceTest extends TestCase
{
    public function testDisconnectReturnsNotFoundWhenGitHubAccountDoesNotExist(): void
    {
        $deleted = false;
        $revoked = false;
        $service = new GitHubDisconnectService(
            function () {
                return null;
            },
            function () use (&$deleted) {
                $deleted = true;
            },
            function () {
                return 'token';
            },
            function () use (&$revoked) {
                $revoked = true;
            }
        );

        $result = $service->disconnect(10);

        $this->assertFalse($result['found']);
        $this->assertNull($result['data']);
        $this->assertFalse($deleted);
        $this->assertFalse($revoked);
    }

    public function testDisconnectRevokesRemoteTokenAndDeletesLocalAccount(): void
    {
        $calls = [];
        $service = new GitHubDisconnectService(
            function ($userId, $provider) use (&$calls) {
                $calls[] = ['find', $userId, $provider];
                return ['id' => 5, 'access_token_encrypted' => 'encrypted-token'];
            },
            function ($userId, $provider) use (&$calls) {
                $calls[] = ['delete', $userId, $provider];
                return 1;
            },
            function ($account) use (&$calls) {
                $calls[] = ['decrypt', $account['id']];
                return 'plain-token';
            },
            function ($accessToken) use (&$calls) {
                $calls[] = ['revoke', $accessToken];
                return true;
            }
        );

        $result = $service->disconnect(42);

        $this->assertTrue($result['found']);
        $this->assertSame(['connected' => false], $result['data']);
        $this->assertSame([
            ['find', 42, 'github'],
            ['decrypt', 5],
            ['revoke', 'plain-token'],
            ['delete', 42, 'github'],
        ], $calls);
    }

    public function testDisconnectStillDeletesLocalAccountWhenRemoteRevocationFails(): void
    {
        $deleted = false;
        $service = new GitHubDisconnectService(
            function () {
                return ['id' => 7, 'access_token_encrypted' => 'encrypted-token'];
            },
            function () use (&$deleted) {
                $deleted = true;
                return 1;
            },
            function () {
                return 'plain-token';
            },
            function () {
                throw new RuntimeException('GitHub indisponivel.');
            }
        );

        $result = $service->disconnect(42);

        $this->assertTrue($result['found']);
        $this->assertSame(['connected' => false], $result['data']);
        $this->assertTrue($deleted);
    }
}
