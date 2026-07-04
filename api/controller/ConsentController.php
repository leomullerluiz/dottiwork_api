<?php

class ConsentController extends BaseController
{
    public function index(Request $request)
    {
        $user = $this->requireToken($request);
        Response::success(['consents' => UserConsent::listByUserId($user['id'])]);
    }

    public function store(Request $request)
    {
        $user = $this->requireToken($request);
        $body = $this->jsonBody($request);
        $errors = UserConsent::validateGrantPayload($body);

        if ($errors) {
            Response::validationError($errors);
        }

        Response::success(['consent' => UserConsent::grant($user['id'], $body)]);
    }

    public function revoke(Request $request, $params)
    {
        $user = $this->requireToken($request);
        $type = $params['type'] ?? ($params[0] ?? null);
        $errors = UserConsent::validateConsentType($type);

        if ($errors) {
            Response::validationError($errors);
        }

        if (!UserConsent::canRevoke($type)) {
            Response::forbidden('Consentimento essencial nao pode ser revogado.');
        }

        $existing = UserConsent::findByUserAndType($user['id'], $type);
        if (!$existing) {
            Response::notFound('Consentimento nao encontrado.');
        }

        Response::success(['consent' => UserConsent::revoke($user['id'], $type)]);
    }
}
