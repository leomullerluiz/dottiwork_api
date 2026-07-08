<?php

class ReferralService
{
    public function registerSignup($referredUserId, $inviteCode, $source = 'github_oauth')
    {
        if (!$inviteCode || !InviteLinkService::isValidCodeFormat($inviteCode)) {
            return null;
        }

        $db = Database::getInstance()->getConnection();
        $startedTransaction = !$db->inTransaction();

        if ($startedTransaction) {
            $db->beginTransaction();
        }

        try {
            $link = (new InviteLinkService())->validatePublicCode($inviteCode);
            if (!$link || (int) $link['user_id'] === (int) $referredUserId) {
                if ($startedTransaction) {
                    $db->commit();
                }
                return null;
            }

            if (UserReferral::findByReferredUserId($referredUserId)) {
                if ($startedTransaction) {
                    $db->commit();
                }
                return null;
            }

            $referral = UserReferral::create($link['user_id'], $referredUserId, $link, $source);
            UserInviteLink::incrementUsage($link['id']);
            (new BadgeEvaluatorService())->evaluateUser($link['user_id']);

            if ($startedTransaction) {
                $db->commit();
            }

            return $referral;
        } catch (PDOException $e) {
            if ($startedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }

            if ($e->getCode() === '23000') {
                return null;
            }

            throw $e;
        } catch (Throwable $e) {
            if ($startedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    public function countEffectiveReferrals($userId)
    {
        return UserReferral::countByReferrerUserId($userId);
    }

    public function listEffectiveReferrals($userId, $limit = 20)
    {
        return UserReferral::listByReferrerUserId($userId, $limit);
    }

    public function hasReferrer($userId)
    {
        return (bool) UserReferral::findByReferredUserId($userId);
    }
}
