<?php

class BadgeController extends BaseController
{
    public function index(Request $request)
    {
        Response::success([
            'badges' => BadgeDefinition::publicCatalog(),
        ]);
    }

    public function mine(Request $request)
    {
        $user = $this->requireToken($request);
        $since = (new DateTime())->modify('-24 hours')->format('Y-m-d H:i:s');

        Response::success([
            'earned' => UserBadge::listByUser($user['id']),
            'progress' => (new BadgeProgressService())->progressForUser($user['id']),
            'recently_awarded' => UserBadge::recentlyAwarded($user['id'], $since),
        ]);
    }

    public function evaluate(Request $request)
    {
        $user = $this->requireToken($request);
        RateLimiter::enforce($request, 'badges.evaluate', 10, 300, 'user:' . $user['id']);
        $awarded = (new BadgeEvaluatorService())->evaluateUser($user['id']);
        $since = (new DateTime())->modify('-24 hours')->format('Y-m-d H:i:s');

        Response::success([
            'awarded' => $awarded,
            'earned' => UserBadge::listByUser($user['id']),
            'progress' => (new BadgeProgressService())->progressForUser($user['id']),
            'recently_awarded' => UserBadge::recentlyAwarded($user['id'], $since),
        ]);
    }
}
