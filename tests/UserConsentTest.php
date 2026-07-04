<?php

use PHPUnit\Framework\TestCase;

class UserConsentTest extends TestCase
{
    public function testGrantPayloadRequiresValidTypePolicyVersionAndSource(): void
    {
        $errors = UserConsent::validateGrantPayload([
            'type' => 'unknown',
            'status' => 'revoked',
            'policy_version' => str_repeat('a', 51),
            'source' => 'debug_tool',
        ]);

        $this->assertSame([
            ['field' => 'type', 'message' => 'Tipo de consentimento invalido.'],
            ['field' => 'status', 'message' => 'Status deve ser granted.'],
            ['field' => 'policy_version', 'message' => 'Versao da politica e obrigatoria e deve ter ate 50 caracteres.'],
            ['field' => 'source', 'message' => 'Origem do consentimento invalida.'],
        ], $errors);
    }

    public function testGrantPayloadNormalizesStatusAsGranted(): void
    {
        $payload = UserConsent::normalizeGrantPayload([
            'type' => 'analytics',
            'status' => 'granted',
            'policy_version' => ' 2026-07-04 ',
            'source' => 'cookie_banner',
        ]);

        $this->assertSame([
            'type' => 'analytics',
            'status' => 'granted',
            'policy_version' => '2026-07-04',
            'source' => 'cookie_banner',
        ], $payload);
    }

    public function testEssentialConsentIsNotRevocable(): void
    {
        $this->assertFalse(UserConsent::canRevoke('essential'));
        $this->assertTrue(UserConsent::canRevoke('analytics'));
        $this->assertTrue(UserConsent::canRevoke('sentry_replay'));
        $this->assertTrue(UserConsent::canRevoke('marketing'));
        $this->assertTrue(UserConsent::canRevoke('github_oauth_notice'));
    }

    public function testDecodeReturnsPublicConsentContractOnly(): void
    {
        $decoded = UserConsent::decode([
            'id' => 10,
            'user_id' => 20,
            'type' => 'marketing',
            'status' => 'revoked',
            'policy_version' => '2026-07-04',
            'source' => 'settings',
            'created_at' => '2026-07-04 12:00:00',
            'updated_at' => '2026-07-04 13:00:00',
            'revoked_at' => '2026-07-04 13:00:00',
        ]);

        $this->assertSame([
            'type' => 'marketing',
            'status' => 'revoked',
            'policy_version' => '2026-07-04',
            'source' => 'settings',
            'created_at' => '2026-07-04 12:00:00',
            'revoked_at' => '2026-07-04 13:00:00',
        ], $decoded);
    }
}
