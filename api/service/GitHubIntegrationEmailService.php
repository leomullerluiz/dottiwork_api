<?php

class GitHubIntegrationEmailService
{
    private $sendTemplate;

    public function __construct(callable $sendTemplate = null)
    {
        $this->sendTemplate = $sendTemplate ?: ['Mailer', 'sendTemplate'];
    }

    public function sendDisconnectAlert(array $user, array $account)
    {
        $email = $user['email'] ?? null;
        if (!$email || !Validator::email($email)) {
            return [
                'sent' => false,
                'reason' => 'missing_email',
            ];
        }

        try {
            call_user_func(
                $this->sendTemplate,
                $email,
                'github_disconnected',
                [
                    'name' => $this->escape($this->displayName($user)),
                    'github_login' => $this->escape($this->githubLogin($account)),
                    'disconnected_at' => $this->escape(date('Y-m-d H:i:s')),
                    'settings_url' => $this->escape($this->frontendUrl('/settings')),
                ],
                'GitHub disconnected from dotti.work'
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

    private function displayName(array $user)
    {
        return $user['display_name']
            ?? $user['login']
            ?? 'dev';
    }

    private function githubLogin(array $account)
    {
        if (!empty($account['provider_login'])) {
            return '@' . $account['provider_login'];
        }

        return 'GitHub';
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
