<?php

class Crypto
{
    public static function randomBase64Url($bytes = 32)
    {
        return self::base64UrlEncode(random_bytes($bytes));
    }

    public static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode($data)
    {
        $padding = strlen($data) % 4;
        if ($padding > 0) {
            $data .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function hashToken($token)
    {
        return hash('sha256', $token);
    }

    public static function hashState($state)
    {
        return hash('sha256', $state);
    }

    public static function optionalIpHash($ip)
    {
        if (!$ip || !self::env('APP_SECRET')) {
            return null;
        }

        return hash_hmac('sha256', $ip, self::env('APP_SECRET'));
    }

    public static function encrypt($plainText)
    {
        $key = self::encryptionKey();
        $iv = random_bytes(12);
        $tag = '';
        $cipherText = openssl_encrypt($plainText, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($cipherText === false) {
            throw new RuntimeException('Could not encrypt value.');
        }

        return self::base64UrlEncode($iv . $tag . $cipherText);
    }

    public static function decrypt($encrypted)
    {
        $raw = self::base64UrlDecode($encrypted);
        if ($raw === false || strlen($raw) < 29) {
            throw new RuntimeException('Invalid encrypted value.');
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipherText = substr($raw, 28);
        $plainText = openssl_decrypt($cipherText, 'aes-256-gcm', self::encryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($plainText === false) {
            throw new RuntimeException('Could not decrypt value.');
        }

        return $plainText;
    }

    private static function encryptionKey()
    {
        $configured = self::env('APP_ENCRYPTION_KEY');
        if (!$configured) {
            $configured = self::env('APP_SECRET');
        }

        if (!$configured) {
            throw new RuntimeException('APP_ENCRYPTION_KEY or APP_SECRET must be configured.');
        }

        $decoded = self::base64UrlDecode($configured);
        if ($decoded !== false && strlen($decoded) >= 32) {
            return substr($decoded, 0, 32);
        }

        return hash('sha256', $configured, true);
    }

    private static function env($key)
    {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        $value = getenv($key);
        return $value === false ? null : $value;
    }
}
