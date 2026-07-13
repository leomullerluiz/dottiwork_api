<?php

use PHPUnit\Framework\TestCase;

class FirstKeyEggEmailServiceTest extends TestCase
{
    public function testTemplateExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../api/templates/first_key_first_egg_awarded.html');
        $this->assertFileExists(__DIR__ . '/../api/templates/first_key_first_egg_awarded.txt');
    }

    public function testSendsAwardedEmailWithEscapedVariables(): void
    {
        $_ENV['FRONTEND_URL'] = 'https://app.dotti.work';
        $sent = [];
        $service = new FirstKeyEggEmailService(function ($toEmail, $slug, $variables, $subject) use (&$sent) {
            $sent = compact('toEmail', 'slug', 'variables', 'subject');
        });

        $result = $service->sendAwardedEmail([
            'display_name' => 'Ana <Dev>',
            'email' => 'ana@example.test',
        ], [
            'login' => 'ana-dev',
        ], 'ana@example.test', 4);

        $this->assertSame(['sent' => true, 'reason' => null], $result);
        $this->assertSame('ana@example.test', $sent['toEmail']);
        $this->assertSame('first_key_first_egg_awarded', $sent['slug']);
        $this->assertSame('You are one of the first to the key', $sent['subject']);
        $this->assertSame('Ana &lt;Dev&gt;', $sent['variables']['name']);
        $this->assertSame(' (@ana-dev)', $sent['variables']['github_login']);
        $this->assertSame('First to the key! First to the egg!', $sent['variables']['badge_name']);
        $this->assertSame('https://app.dotti.work/u/ana-dev', $sent['variables']['profile_url']);
        $this->assertSame('4', $sent['variables']['position']);
    }

    public function testSkipsInvalidEmailAndSuppressesSendFailures(): void
    {
        $called = false;
        $service = new FirstKeyEggEmailService(function () use (&$called) {
            $called = true;
        });

        $missing = $service->sendAwardedEmail(['display_name' => 'Ana'], ['login' => 'ana'], 'invalid-email', 1);
        $this->assertSame(['sent' => false, 'reason' => 'missing_verified_email'], $missing);
        $this->assertFalse($called);

        $failing = new FirstKeyEggEmailService(function () {
            throw new RuntimeException('SMTP unavailable.');
        });

        $failed = $failing->sendAwardedEmail(['display_name' => 'Ana'], ['login' => 'ana'], 'ana@example.test', 1);
        $this->assertSame(['sent' => false, 'reason' => 'send_failed'], $failed);
    }
}
