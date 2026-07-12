<?php

class InviteLinkService
{
    public function getOrCreatePrimaryLink($userId)
    {
        $existing = UserInviteLink::findPrimaryActiveByUserId($userId);
        if ($existing) {
            return $existing;
        }

        return $this->create($userId);
    }

    public function create($userId, array $data = [])
    {
        $attempts = 0;

        do {
            $attempts++;
            $data['code'] = $data['code'] ?? self::generateCode();

            try {
                return UserInviteLink::create($userId, $data);
            } catch (PDOException $e) {
                if ($attempts >= 3 || strpos($e->getMessage(), 'Duplicate') === false) {
                    throw $e;
                }

                unset($data['code']);
            }
        } while ($attempts < 3);

        throw new RuntimeException('Could not generate invite link.');
    }

    public function findByCode($code)
    {
        if (!self::isValidCodeFormat($code)) {
            return null;
        }

        return UserInviteLink::findByCode($code);
    }

    public function validatePublicCode($code)
    {
        $link = $this->findByCode($code);
        return UserInviteLink::isActive($link) ? $link : null;
    }

    public function revoke($userId, $linkId)
    {
        return UserInviteLink::revoke($userId, $linkId);
    }

    public function summaryForUser($userId)
    {
        return [
            'effective_referrals' => (new ReferralService())->countEffectiveReferrals($userId),
        ];
    }

    public static function generateCode()
    {
        return Crypto::randomBase64Url(18);
    }

    public static function isValidCodeFormat($code)
    {
        return is_string($code) && preg_match('/^[A-Za-z0-9_-]{12,64}$/', $code) === 1;
    }

    public static function publicUrl($code)
    {
        $baseUrl = rtrim(self::env('INVITE_BASE_URL') ?: self::env('FRONTEND_URL') ?: 'https://dotti.work', '/');
        return $baseUrl . '/invite/' . rawurlencode($code);
    }

    private static function env($key)
    {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }

        $value = getenv($key);
        return $value === false ? null : $value;
    }
}
