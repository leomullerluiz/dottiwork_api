<?php

use PHPUnit\Framework\TestCase;

class WelcomeEmailServiceTest extends TestCase
{
    public function testWelcomeTemplateExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../api/templates/welcome_github_signup.html');
    }

    public function testSendsWelcomeEmailWithOnboardingAndMatchesLinks(): void
    {
        $_ENV['FRONTEND_URL'] = 'https://app.dotti.work';
        $sent = [];
        $service = new WelcomeEmailService(function ($toEmail, $slug, $variables, $subject) use (&$sent) {
            $sent = compact('toEmail', 'slug', 'variables', 'subject');
        });

        $result = $service->sendAfterGitHubSignup([
            'display_name' => 'Ana <Dev>',
            'email' => 'ana@example.test',
        ], [
            'login' => 'ana-dev',
        ], 'ana@example.test');

        $this->assertSame(['sent' => true, 'reason' => null], $result);
        $this->assertSame('ana@example.test', $sent['toEmail']);
        $this->assertSame('welcome_github_signup', $sent['slug']);
        $this->assertSame('Welcome to dotti.work', $sent['subject']);
        $this->assertSame('Ana &lt;Dev&gt;', $sent['variables']['name']);
        $this->assertSame(' (@ana-dev)', $sent['variables']['github_login']);
        $this->assertSame('https://app.dotti.work/onboarding', $sent['variables']['onboarding_url']);
        $this->assertSame('https://app.dotti.work/matches', $sent['variables']['matches_url']);
    }

    public function testSkipsWhenVerifiedEmailIsMissingOrInvalid(): void
    {
        $called = false;
        $service = new WelcomeEmailService(function () use (&$called) {
            $called = true;
        });

        $result = $service->sendAfterGitHubSignup(['display_name' => 'Ana'], ['login' => 'ana'], 'invalid-email');

        $this->assertSame(['sent' => false, 'reason' => 'missing_verified_email'], $result);
        $this->assertFalse($called);
    }

    public function testSuppressesMailerFailuresSoSignupCanContinue(): void
    {
        $service = new WelcomeEmailService(function () {
            throw new RuntimeException('SMTP unavailable.');
        });

        $result = $service->sendAfterGitHubSignup([
            'display_name' => 'Ana',
        ], [
            'login' => 'ana',
        ], 'ana@example.test');

        $this->assertSame(['sent' => false, 'reason' => 'send_failed'], $result);
    }
}
