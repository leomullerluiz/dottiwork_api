<?php

use PHPUnit\Framework\TestCase;

class TopRepositoriesDocumentationTest extends TestCase
{
    public function testOpenApiDocumentsTopRepositoriesEndpointAndSchemas(): void
    {
        $openapi = file_get_contents(__DIR__ . '/../openapi.yaml');

        $this->assertStringContainsString('/repositories/top:', $openapi);
        $this->assertStringContainsString('TopRepositorySortBy:', $openapi);
        $this->assertStringContainsString('TopRepositoryItem:', $openapi);
        $this->assertStringContainsString('TopRepositoryRankMetric:', $openapi);
        $this->assertStringContainsString('TopRepositoryListResponse:', $openapi);
    }

    public function testTopRepositoriesControllerUsesOptionalAuthentication(): void
    {
        $controller = file_get_contents(__DIR__ . '/../api/controller/RepositoryController.php');
        preg_match('/public function top\\(Request \\$request\\)(.*?)public function show/s', $controller, $matches);
        $topMethod = $matches[1] ?? '';

        $this->assertStringContainsString('Auth::getAuthenticatedUser($request)', $topMethod);
        $this->assertStringNotContainsString('requireToken($request)', $topMethod);
    }
}
