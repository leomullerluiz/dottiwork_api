<?php

class HealthController extends BaseController
{
    public function health(Request $request)
    {
        Response::success([
            'service' => 'dotti.work API',
            'status' => 'online',
            'version' => '2.0.0',
        ]);
    }

    public function database(Request $request)
    {
        if (($_ENV['APP_ENV'] ?? 'local') === 'production') {
            $this->requireToken($request);
        }

        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query('SELECT 1 AS ok');
            $stmt->fetch();
            Response::success(['database' => 'online']);
        } catch (Exception $e) {
            Response::serviceUnavailable('Banco de dados indisponivel.');
        }
    }

    public function sentry(Request $request)
    {
        $enabled = ($_ENV['APP_ENV'] ?? 'local') !== 'production'
            || filter_var($_ENV['SENTRY_TEST_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$enabled) {
            Response::forbidden('Teste do Sentry desabilitado neste ambiente.');
        }

        if (empty($_ENV['SENTRY_DSN'])) {
            Response::serviceUnavailable('Sentry nao configurado.');
        }

        $message = 'Sentry test event from dotti.work API';
        $eventId = \Sentry\captureMessage($message, \Sentry\Severity::info());
        \Sentry\flush();

        Response::success([
            'configured' => true,
            'sent' => $eventId !== null,
            'event_id' => $eventId ? (string) $eventId : null,
            'message' => $message,
        ]);
    }
}
