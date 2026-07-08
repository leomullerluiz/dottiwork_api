<?php

class BadgeEvaluatorService
{
    private $progressService;

    public function __construct(BadgeProgressService $progressService = null)
    {
        $this->progressService = $progressService ?: new BadgeProgressService();
    }

    public function evaluateUser($userId, $sourceEventId = null)
    {
        $awarded = [];

        foreach (BadgeDefinition::allActive() as $definition) {
            if (UserBadge::hasBadge($userId, $definition['slug'])) {
                continue;
            }

            $progress = $this->progressService->progressForDefinition($userId, $definition);
            if (!$progress['completed']) {
                continue;
            }

            $badge = UserBadge::grant($userId, $definition['slug'], $sourceEventId, [
                'current_value' => $progress['current_value'],
                'target_value' => $progress['target_value'],
                'percent' => $progress['percent'],
                'criteria_type' => $progress['criteria_type'],
                'criteria_config' => $progress['criteria_config'],
            ]);

            if ($badge) {
                $awarded[] = $badge;
            }
        }

        return $awarded;
    }

    public function evaluateAfterActivityEvent($userId, $eventType, $eventId = null)
    {
        return $this->evaluateUser($userId, $eventId);
    }

    public function evaluateAfterProfileUpdate($userId)
    {
        return $this->evaluateUser($userId);
    }

    public function evaluateAfterRepositoryStateChange($userId, $githubRepositoryId)
    {
        return $this->evaluateUser($userId);
    }
}
