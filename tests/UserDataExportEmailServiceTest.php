<?php

use PHPUnit\Framework\TestCase;

class UserDataExportEmailServiceTest extends TestCase
{
    public function testDataExportRequestedTemplateExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../api/templates/data_export_requested.html');
    }

    public function testSendsExportRequestedAlertEmail(): void
    {
        $_ENV['FRONTEND_URL'] = 'https://app.dotti.work';
        $sent = [];
        $service = new UserDataExportEmailService(function ($toEmail, $slug, $variables, $subject) use (&$sent) {
            $sent = compact('toEmail', 'slug', 'variables', 'subject');
        });

        $result = $service->sendExportRequestedAlert([
            'display_name' => 'Ana <Dev>',
            'login' => 'ana',
            'email' => 'ana@example.test',
        ]);

        $this->assertSame(['sent' => true, 'reason' => null], $result);
        $this->assertSame('ana@example.test', $sent['toEmail']);
        $this->assertSame('data_export_requested', $sent['slug']);
        $this->assertSame('Exportacao dos seus dados dotti.work', $sent['subject']);
        $this->assertSame('Ana &lt;Dev&gt;', $sent['variables']['name']);
        $this->assertSame('https://app.dotti.work/settings/privacy', $sent['variables']['privacy_url']);
        $this->assertArrayHasKey('exported_at', $sent['variables']);
    }

    public function testSkipsExportAlertWhenEmailIsMissingOrInvalid(): void
    {
        $called = false;
        $service = new UserDataExportEmailService(function () use (&$called) {
            $called = true;
        });

        $result = $service->sendExportRequestedAlert([
            'display_name' => 'Ana',
            'email' => 'invalid-email',
        ]);

        $this->assertSame(['sent' => false, 'reason' => 'missing_email'], $result);
        $this->assertFalse($called);
    }

    public function testSuppressesMailerFailuresSoExportCanContinue(): void
    {
        $service = new UserDataExportEmailService(function () {
            throw new RuntimeException('SMTP indisponivel.');
        });

        $result = $service->sendExportRequestedAlert([
            'display_name' => 'Ana',
            'email' => 'ana@example.test',
        ]);

        $this->assertSame(['sent' => false, 'reason' => 'send_failed'], $result);
    }
}
