<?php

class FirstKeyEggEmailService
{
    private $sendTemplate;

    public function __construct(callable $sendTemplate = null)
    {
        $this->sendTemplate = $sendTemplate ?: ['Mailer', 'sendTemplate'];
    }

    public function sendAwardedEmail(array $user, array $githubUser = [], $email = null, $position = null)
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
        $login = $githubUser['login'] ?? ($user['login'] ?? null);

        try {
            call_user_func(
                $this->sendTemplate,
                $email,
                'first_key_first_egg_awarded',
                [
                    'name' => $this->escape($name),
                    'github_login' => $this->escape($login ? ' (@' . $login . ')' : ''),
                    'badge_name' => $this->escape(SignupCohortAwardService::BADGE_NAME),
                    'profile_url' => $this->escape($this->profileUrl($login)),
                    'position' => $this->escape($position ?: ''),
                ],
                'You are one of the first to the key'
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

    private function profileUrl($login)
    {
        if ($login) {
            return $this->frontendUrl('/u/' . rawurlencode($login));
        }

        return $this->frontendUrl('/me/public-profile');
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
