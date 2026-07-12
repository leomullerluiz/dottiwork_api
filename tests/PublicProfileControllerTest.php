<?php

use PHPUnit\Framework\TestCase;

class PublicProfileControllerTest extends TestCase
{
    public function testPublicProfileRoutesAreRegistered(): void
    {
        $index = file_get_contents(__DIR__ . '/../api/index.php');

        $this->assertStringContainsString("\$route('get', '/public/profiles/:login', 'PublicProfileController@show');", $index);
        $this->assertStringContainsString("\$route('get', '/me/public-profile', 'PublicProfileController@preview');", $index);
        $this->assertStringContainsString("\$route('put', '/me/public-profile/settings', 'PublicProfileController@updateSettings');", $index);
    }

    public function testControllerAppliesPublicRateLimitAndAuthenticatedSettings(): void
    {
        $controller = file_get_contents(__DIR__ . '/../api/controller/PublicProfileController.php');

        $this->assertStringContainsString("RateLimiter::enforce(\$request, 'public.profile.show', 60, 60);", $controller);
        $this->assertStringContainsString("\$user = \$this->requireToken(\$request);", $controller);
        $this->assertStringContainsString("RateLimiter::enforce(\$request, 'public.profile.settings', 20, 300, 'user:' . \$user['id']);", $controller);
        $this->assertStringContainsString("Response::notFound('Public profile not found.');", $controller);
    }

    public function testOpenApiDocumentsPublicProfileEndpoints(): void
    {
        $openApi = file_get_contents(__DIR__ . '/../openapi.yaml');

        $this->assertStringContainsString('/public/profiles/{login}:', $openApi);
        $this->assertStringContainsString('/me/public-profile:', $openApi);
        $this->assertStringContainsString('/me/public-profile/settings:', $openApi);
        $this->assertStringContainsString('PublicUserProfileResponse:', $openApi);
        $this->assertStringContainsString('PublicProfileSettingsInput:', $openApi);
    }
}
