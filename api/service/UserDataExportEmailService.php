<?php

class UserDataExportEmailService
{
    private $sendTemplate;

    public function __construct(callable $sendTemplate = null)
    {
        $this->sendTemplate = $sendTemplate ?: ['Mailer', 'sendTemplate'];
    }

    public function sendExportRequestedAlert(array $user)
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
                'data_export_requested',
                [
                    'name' => $this->escape($this->displayName($user)),
                    'exported_at' => $this->escape(date('Y-m-d H:i:s')),
                    'privacy_url' => $this->escape($this->frontendUrl('/settings/privacy')),
                ],
                'Exportacao dos seus dados dotti.work'
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
