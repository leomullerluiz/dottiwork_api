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
}
