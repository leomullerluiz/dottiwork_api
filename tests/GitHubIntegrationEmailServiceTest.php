<?php

use PHPUnit\Framework\TestCase;

class GitHubIntegrationEmailServiceTest extends TestCase
{
    public function testGitHubDisconnectedTemplateExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../api/templates/github_disconnected.html');
    }

    public function testSendsDisconnectAlertEmail(): void
    {
        $_ENV['FRONTEND_URL'] = 'https://app.dotti.work';
        $sent = [];
        $service = new GitHubIntegrationEmailService(function ($toEmail, $slug, $variables, $subject) use (&$sent) {
            $sent = compact('toEmail', 'slug', 'variables', 'subject');
        });

        $result = $service->sendDisconnectAlert([
            'display_name' => 'Ana <Dev>',
            'login' => 'ana',
            'email' => 'ana@example.test',
        ], [
            'provider_login' => 'ana-dev',
        ]);

        $this->assertSame(['sent' => true, 'reason' => null], $result);
        $this->assertSame('ana@example.test', $sent['toEmail']);
        $this->assertSame('github_disconnected', $sent['slug']);
        $this->assertSame('GitHub disconnected from dotti.work', $sent['subject']);
        $this->assertSame('Ana &lt;Dev&gt;', $sent['variables']['name']);
        $this->assertSame('@ana-dev', $sent['variables']['github_login']);
        $this->assertSame('https://app.dotti.work/settings', $sent['variables']['settings_url']);
        $this->assertArrayHasKey('disconnected_at', $sent['variables']);
    }

    public function testSkipsDisconnectAlertWhenEmailIsMissingOrInvalid(): void
    {
        $called = false;
        $service = new GitHubIntegrationEmailService(function () use (&$called) {
            $called = true;
        });

        $result = $service->sendDisconnectAlert([
            'display_name' => 'Ana',
            'email' => 'invalid-email',
        ], [
            'provider_login' => 'ana-dev',
        ]);

        $this->assertSame(['sent' => false, 'reason' => 'missing_email'], $result);
        $this->assertFalse($called);
    }

    public function testSuppressesMailerFailuresSoDisconnectCanContinue(): void
    {
        $service = new GitHubIntegrationEmailService(function () {
            throw new RuntimeException('SMTP unavailable.');
        });

        $result = $service->sendDisconnectAlert([
            'display_name' => 'Ana',
            'email' => 'ana@example.test',
        ], [
            'provider_login' => 'ana-dev',
        ]);

        $this->assertSame(['sent' => false, 'reason' => 'send_failed'], $result);
    }
}
