<?php

class WelcomeEmailService
{
    private $sendTemplate;

    public function __construct(callable $sendTemplate = null)
    {
        $this->sendTemplate = $sendTemplate ?: ['Mailer', 'sendTemplate'];
    }

    public function sendAfterGitHubSignup(array $user, array $githubUser, $email = null)
    {
        $email = $email ?: ($user['email'] ?? null);
        if (!$email || !Validator::email($email)) {
            return [
                'sent' => false,
                'reason' => 'missing_verified_email',
            ];
        }

        $name = $user['display_name']
            ?? $githubUser['name']
            ?? $githubUser['login']
            ?? 'dev';

        try {
            call_user_func(
                $this->sendTemplate,
                $email,
                'welcome_github_signup',
                [
                    'name' => $this->escape($name),
                    'github_login' => $this->escape(isset($githubUser['login']) && $githubUser['login'] !== '' ? ' (@' . $githubUser['login'] . ')' : ''),
                    'onboarding_url' => $this->escape($this->frontendUrl('/onboarding')),
                    'matches_url' => $this->escape($this->frontendUrl('/matches')),
                ],
                'Welcome to dotti.work'
            );
        } catch (Throwable $e) {
            return [
                'sent' => false,
                'reason' => 'send_failed',
            ];
        }

        return [
            'sent' => true,
            'reason' => null,
        ];
    }

    private function frontendUrl($path)
    {
        $base = rtrim($this->env('FRONTEND_URL') ?: 'https://dotti.work', '/');
        return $base . '/' . ltrim($path, '/');
    }

    private function escape($value)
    {
        return EmailTemplateRenderer::escape($value);
    }

    private function env($key)
    {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        $value = getenv($key);
        return $value === false ? null : $value;
    }
}
