<?php

use PHPUnit\Framework\TestCase;

class InviteLinksTest extends TestCase
{
    public function testInviteCodeFormatAcceptsOpaqueBase64UrlCodes(): void
    {
        $code = InviteLinkService::generateCode();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{12,64}$/', $code);
        $this->assertTrue(InviteLinkService::isValidCodeFormat($code));
        $this->assertFalse(InviteLinkService::isValidCodeFormat('short'));
        $this->assertFalse(InviteLinkService::isValidCodeFormat('../abc123'));
        $this->assertFalse(InviteLinkService::isValidCodeFormat(str_repeat('a', 65)));
    }

    public function testInviteLinkDtoIncludesStableFrontendFields(): void
    {
        $_ENV['FRONTEND_URL'] = 'https://dotti.work/';

        $dto = UserInviteLink::toResponse([
            'id' => '10',
            'code' => 'AbC123xYz_456789',
            'status' => 'active',
            'uses_count' => '2',
            'expires_at' => null,
            'created_at' => '2026-07-07 18:00:00',
        ]);

        $this->assertSame(10, $dto['id']);
        $this->assertSame('AbC123xYz_456789', $dto['code']);
        $this->assertSame('https://dotti.work/invite/AbC123xYz_456789', $dto['url']);
        $this->assertSame('active', $dto['status']);
        $this->assertSame(2, $dto['uses_count']);
        $this->assertNull($dto['expires_at']);
    }

    public function testPublicInviteDtoExposesMinimalInviterData(): void
    {
        $dto = UserInviteLink::toPublicResponse([
            'id' => '10',
            'code' => 'AbC123xYz_456789',
            'status' => 'active',
            'uses_count' => '0',
            'expires_at' => null,
            'revoked_at' => null,
            'inviter_display_name' => 'Leo',
            'inviter_avatar_url' => null,
        ]);

        $this->assertSame('AbC123xYz_456789', $dto['code']);
        $this->assertTrue($dto['valid']);
        $this->assertSame(['display_name' => 'Leo', 'avatar_url' => null], $dto['inviter']);
    }

    public function testInviteRoutesAreRegistered(): void
    {
        $index = file_get_contents(__DIR__ . '/../api/index.php');

        $this->assertStringContainsString("\$route('post', '/me/invite-links', 'InviteController@store');", $index);
        $this->assertStringContainsString("\$route('get', '/me/invite-links', 'InviteController@index');", $index);
        $this->assertStringContainsString("\$route('post', '/me/invite-links/:id/revoke', 'InviteController@revoke');", $index);
        $this->assertStringContainsString("\$route('get', '/me/referrals', 'ReferralController@index');", $index);
        $this->assertStringContainsString("\$route('get', '/invites/:code', 'InviteController@publicShow');", $index);
    }

    public function testGitHubOAuthStoresAndRegistersInviteContext(): void
    {
        $oauth = file_get_contents(__DIR__ . '/../api/core/GitHubOAuth.php');
        $state = file_get_contents(__DIR__ . '/../api/model/OAuthAuthorizationState.php');

        $this->assertStringContainsString("\$request->getQuery('invite_code')", $oauth);
        $this->assertStringContainsString('registerSignup($userId, $inviteCode, \'github_oauth\')', $oauth);
        $this->assertStringContainsString('invite_code, invite_link_id', $state);
    }
}
