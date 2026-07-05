<?php

use PHPUnit\Framework\TestCase;

class AccountDeletionServiceTest extends TestCase
{
    public function testDeleteRevokesTokensSoftDeletesUserAndClearsCookie(): void
    {
        $calls = [];
        $service = new AccountDeletionService(
            function ($userId) use (&$calls) {
                $calls[] = ['revoke_tokens', $userId];
            },
            function ($userId) use (&$calls) {
                $calls[] = ['soft_delete', $userId];
            },
            function () use (&$calls) {
                $calls[] = ['clear_cookie'];
            },
            function ($user) use (&$calls) {
                $calls[] = ['send_email', $user['id']];
            }
        );

        $result = $service->delete(['id' => 42, 'email' => 'ana@example.test']);

        $this->assertSame(['deleted' => true], $result);
        $this->assertSame([
            ['revoke_tokens', 42],
            ['soft_delete', 42],
            ['clear_cookie'],
            ['send_email', 42],
        ], $calls);
    }

    public function testDeleteKeepsLegacyUserIdInputWithoutSendingEmail(): void
    {
        $calls = [];
        $service = new AccountDeletionService(
            function ($userId) use (&$calls) {
                $calls[] = ['revoke_tokens', $userId];
            },
            function ($userId) use (&$calls) {
                $calls[] = ['soft_delete', $userId];
            },
            function () use (&$calls) {
                $calls[] = ['clear_cookie'];
            },
            function () use (&$calls) {
                $calls[] = ['send_email'];
            }
        );

        $result = $service->delete(42);

        $this->assertSame(['deleted' => true], $result);
        $this->assertSame([
            ['revoke_tokens', 42],
            ['soft_delete', 42],
            ['clear_cookie'],
        ], $calls);
    }
}
