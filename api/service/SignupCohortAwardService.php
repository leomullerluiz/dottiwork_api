<?php

class SignupCohortAwardService
{
    public const COHORT_SLUG = 'first_key_first_egg';
    public const BADGE_SLUG = 'first_key_first_egg';
    public const BADGE_NAME = 'First to the key! First to the egg!';
    public const FRAME_SLUG = 'first_key_first_egg_frame';
    public const FRAME_NAME = 'First to the key frame';
    public const COHORT_LIMIT = 10;

    private $deps;

    public function __construct(array $deps = [])
    {
        $this->deps = array_merge([
            'reserve_cohort_award' => [$this, 'reserveCohortAward'],
            'grant_badge' => ['UserBadge', 'grant'],
            'grant_frame' => ['UserProfileFrame', 'grant'],
            'send_email' => function (array $user, array $githubUser, $email, $position) {
                return (new FirstKeyEggEmailService())->sendAwardedEmail($user, $githubUser, $email, $position);
            },
        ], $deps);
    }

    public function awardFirstKeyEggIfEligible(array $user, array $githubUser = [], $email = null)
    {
        $userId = isset($user['id']) ? (int) $user['id'] : 0;
        if ($userId <= 0) {
            return [
                'awarded' => false,
                'reason' => 'invalid_user',
            ];
        }

        $reservation = $this->call('reserve_cohort_award', $userId, self::COHORT_SLUG, self::COHORT_LIMIT);
        if (empty($reservation['eligible'])) {
            return [
                'awarded' => false,
                'reason' => $reservation['reason'] ?? 'cohort_full',
            ];
        }

        $position = (int) $reservation['position'];
        $badge = $this->call('grant_badge', $userId, self::BADGE_SLUG, null, [
            'cohort' => self::COHORT_SLUG,
            'position' => $position,
            'limit' => self::COHORT_LIMIT,
            'awarded_on_signup' => true,
        ]);
        if (!$badge) {
            return [
                'awarded' => false,
                'reason' => 'badge_unavailable',
                'position' => $position,
                'email' => [
                    'sent' => false,
                    'reason' => 'badge_unavailable',
                ],
            ];
        }

        $frame = $this->call(
            'grant_frame',
            $userId,
            self::FRAME_SLUG,
            self::FRAME_NAME,
            null,
            self::frameStyleConfig(),
            self::BADGE_SLUG
        );

        $emailResult = [
            'sent' => false,
            'reason' => 'already_awarded',
        ];
        if (!empty($reservation['created'])) {
            $emailResult = $this->trySendEmail($user, $githubUser, $email, $position);
        }

        return [
            'awarded' => !empty($reservation['created']),
            'reason' => !empty($reservation['created']) ? null : 'already_awarded',
            'position' => $position,
            'badge' => $badge,
            'frame' => $frame,
            'email' => $emailResult,
        ];
    }

    public static function frameStyleConfig()
    {
        return [
            'variant' => 'founder-key-egg',
            'accent' => '#f05d4f',
            'ring' => '#f8c14a',
            'shadow' => '#15202b',
            'glow' => '#fff3c4',
        ];
    }

    public function reserveCohortAward($userId, $cohortSlug, $limit)
    {
        $db = Database::getInstance()->getConnection();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $db->beginTransaction();

                $existing = $this->lockedPositionForUser($db, $userId, $cohortSlug);
                if ($existing !== null) {
                    $db->commit();
                    return [
                        'eligible' => true,
                        'created' => false,
                        'position' => $existing,
                    ];
                }

                $currentPosition = $this->lockedCurrentPosition($db, $cohortSlug);
                if ($currentPosition >= $limit) {
                    $db->commit();
                    return [
                        'eligible' => false,
                        'created' => false,
                        'reason' => 'cohort_full',
                    ];
                }

                $position = $currentPosition + 1;
                $stmt = $db->prepare("
                    INSERT INTO signup_cohort_awards (
                        cohort_slug, user_id, position, awarded_at, created_at
                    ) VALUES (
                        :cohort_slug, :user_id, :position, NOW(), NOW()
                    )
                ");
                $stmt->execute([
                    'cohort_slug' => $cohortSlug,
                    'user_id' => $userId,
                    'position' => $position,
                ]);

                $db->commit();
                return [
                    'eligible' => true,
                    'created' => true,
                    'position' => $position,
                ];
            } catch (PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }

                if ($e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }

        return [
            'eligible' => false,
            'created' => false,
            'reason' => 'reservation_conflict',
        ];
    }

    private function lockedPositionForUser(PDO $db, $userId, $cohortSlug)
    {
        $stmt = $db->prepare("
            SELECT position
            FROM signup_cohort_awards
            WHERE cohort_slug = :cohort_slug AND user_id = :user_id
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([
            'cohort_slug' => $cohortSlug,
            'user_id' => $userId,
        ]);

        $position = $stmt->fetchColumn();
        return $position === false ? null : (int) $position;
    }

    private function lockedCurrentPosition(PDO $db, $cohortSlug)
    {
        $stmt = $db->prepare("
            SELECT position
            FROM signup_cohort_awards
            WHERE cohort_slug = :cohort_slug
            ORDER BY position DESC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute(['cohort_slug' => $cohortSlug]);

        $position = $stmt->fetchColumn();
        return $position === false ? 0 : (int) $position;
    }

    private function trySendEmail(array $user, array $githubUser, $email, $position)
    {
        try {
            return $this->call('send_email', $user, $githubUser, $email, $position);
        } catch (Throwable $e) {
            return [
                'sent' => false,
                'reason' => 'send_failed',
            ];
        }
    }

    private function call($key)
    {
        $args = array_slice(func_get_args(), 1);
        return call_user_func_array($this->deps[$key], $args);
    }
}
