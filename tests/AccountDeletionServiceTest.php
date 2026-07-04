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
