<?php

class ReferralController extends BaseController
{
    public function index(Request $request)
    {
        $user = $this->requireToken($request);
        $limit = $this->limit($request, 20, 100);
        $service = new ReferralService();

        Response::success([
            'summary' => [
                'effective_referrals' => $service->countEffectiveReferrals($user['id']),
            ],
            'referrals' => $service->listEffectiveReferrals($user['id'], $limit),
        ]);
    }
}
