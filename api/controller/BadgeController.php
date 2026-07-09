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
            'unseen_awarded' => UserBadge::unseenAwarded($user['id']),
            'unseen_awarded_count' => UserBadge::unseenAwardedCount($user['id']),
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
            'unseen_awarded' => UserBadge::unseenAwarded($user['id']),
            'unseen_awarded_count' => UserBadge::unseenAwardedCount($user['id']),
        ]);
    }

    public function markNotificationsViewed(Request $request)
    {
        $user = $this->requireToken($request);
        RateLimiter::enforce($request, 'badges.notifications.viewed', 30, 300, 'user:' . $user['id']);

        $body = $this->jsonBody($request);
        $slugs = $body['slugs'] ?? ($body['badge_slugs'] ?? []);
        $ids = $body['ids'] ?? ($body['user_badge_ids'] ?? []);
        $notificationSeen = $body['notification_seen'] ?? true;
        $errors = [];

        if (!is_array($slugs)) {
            $errors[] = [
                'field' => 'slugs',
                'message' => 'slugs deve ser uma lista de slugs de badges.',
            ];
        } elseif ($this->hasInvalidSlugs($slugs)) {
            $errors[] = [
                'field' => 'slugs',
                'message' => 'Todos os slugs devem ser strings nao vazias.',
            ];
        }

        if (!is_array($ids)) {
            $errors[] = [
                'field' => 'ids',
                'message' => 'ids deve ser uma lista de ids de conquistas.',
            ];
        } elseif ($this->hasInvalidIds($ids)) {
            $errors[] = [
                'field' => 'ids',
                'message' => 'Todos os ids devem ser inteiros positivos.',
            ];
        }

        if (
            array_key_exists('notification_seen', $body)
            && (!Validator::boolean($notificationSeen) || !filter_var($notificationSeen, FILTER_VALIDATE_BOOLEAN))
        ) {
            $errors[] = [
                'field' => 'notification_seen',
                'message' => 'notification_seen deve ser true para marcar notificacoes como visualizadas.',
            ];
        }

        if (!$errors && !$slugs && !$ids) {
            $errors[] = [
                'field' => 'slugs',
                'message' => 'Informe ao menos um slug ou id de conquista.',
            ];
        }

        if ($errors) {
            Response::validationError($errors);
        }

        $updatedCount = UserBadge::markNotificationsSeen($user['id'], $slugs, $ids);
        $since = (new DateTime())->modify('-24 hours')->format('Y-m-d H:i:s');

        Response::success([
            'updated_count' => $updatedCount,
            'recently_awarded' => UserBadge::recentlyAwarded($user['id'], $since),
            'unseen_awarded' => UserBadge::unseenAwarded($user['id']),
            'unseen_awarded_count' => UserBadge::unseenAwardedCount($user['id']),
        ]);
    }

    private function hasInvalidSlugs(array $slugs)
    {
        foreach ($slugs as $slug) {
            if (!is_string($slug) || trim($slug) === '') {
                return true;
            }
        }

        return false;
    }

    private function hasInvalidIds(array $ids)
    {
        foreach ($ids as $id) {
            if (!Validator::integer($id) || (int) $id <= 0) {
                return true;
            }
        }

        return false;
    }
}
