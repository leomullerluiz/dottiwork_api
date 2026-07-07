<?php

class InviteController extends BaseController
{
    public function store(Request $request)
    {
        $user = $this->requireToken($request);
        RateLimiter::enforce($request, 'me.invite_links.create', 10, 300, 'user:' . $user['id']);

        $link = (new InviteLinkService())->getOrCreatePrimaryLink($user['id']);

        Response::success([
            'invite_link' => UserInviteLink::toResponse($link),
        ]);
    }

    public function index(Request $request)
    {
        $user = $this->requireToken($request);
        $links = array_map([UserInviteLink::class, 'toResponse'], UserInviteLink::listByUserId($user['id']));

        Response::success([
            'invite_links' => $links,
            'summary' => (new InviteLinkService())->summaryForUser($user['id']),
        ]);
    }

    public function publicShow(Request $request, $params)
    {
        $code = $params['code'] ?? ($params[0] ?? null);
        RateLimiter::enforce($request, 'invites.show', 60, 300);

        $link = (new InviteLinkService())->validatePublicCode($code);
        if (!$link) {
            Response::notFound('Convite nao encontrado ou indisponivel.');
        }

        Response::success([
            'invite' => UserInviteLink::toPublicResponse($link),
        ]);
    }

    public function revoke(Request $request, $params)
    {
        $user = $this->requireToken($request);
        $linkId = $params['id'] ?? ($params[0] ?? null);
        RateLimiter::enforce($request, 'me.invite_links.revoke', 10, 300, 'user:' . $user['id']);

        if (!Validator::integer($linkId)) {
            Response::validationError([
                ['field' => 'id', 'message' => 'Link de convite invalido.'],
            ]);
        }

        if (!(new InviteLinkService())->revoke($user['id'], $linkId)) {
            Response::notFound('Link de convite nao encontrado.');
        }

        Response::success(['revoked' => true]);
    }
}
