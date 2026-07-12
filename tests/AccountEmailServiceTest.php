<?php

use PHPUnit\Framework\TestCase;

class AccountEmailServiceTest extends TestCase
{
    public function testAccountDeletedTemplateExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../api/templates/account_deleted.html');
    }

    public function testSendsDeletionConfirmationEmail(): void
    {
        $_ENV['FRONTEND_URL'] = 'https://app.dotti.work';
        $sent = [];
        $service = new AccountEmailService(function ($toEmail, $slug, $variables, $subject) use (&$sent) {
            $sent = compact('toEmail', 'slug', 'variables', 'subject');
        });

        $result = $service->sendDeletionConfirmation([
            'display_name' => 'Ana <Dev>',
            'login' => 'ana',
            'email' => 'ana@example.test',
        ]);

        $this->assertSame(['sent' => true, 'reason' => null], $result);
        $this->assertSame('ana@example.test', $sent['toEmail']);
        $this->assertSame('account_deleted', $sent['slug']);
        $this->assertSame('Your dotti.work account deletion confirmation', $sent['subject']);
        $this->assertSame('Ana &lt;Dev&gt;', $sent['variables']['name']);
        $this->assertSame('https://app.dotti.work/', $sent['variables']['home_url']);
        $this->assertArrayHasKey('deleted_at', $sent['variables']);
    }

    public function testSkipsDeletionEmailWhenEmailIsMissingOrInvalid(): void
    {
        $called = false;
        $service = new AccountEmailService(function () use (&$called) {
            $called = true;
        });

        $result = $service->sendDeletionConfirmation([
            'display_name' => 'Ana',
            'email' => 'invalid-email',
        ]);

        $this->assertSame(['sent' => false, 'reason' => 'missing_email'], $result);
        $this->assertFalse($called);
    }

    public function testSuppressesMailerFailuresSoDeletionCanContinue(): void
    {
        $service = new AccountEmailService(function () {
            throw new RuntimeException('SMTP unavailable.');
        });

        $result = $service->sendDeletionConfirmation([
            'display_name' => 'Ana',
            'email' => 'ana@example.test',
        ]);

        $this->assertSame(['sent' => false, 'reason' => 'send_failed'], $result);
    }
}
