<?php

use PHPUnit\Framework\TestCase;

class IssueDifficultyServiceTest extends TestCase
{
    public function testGoodFirstIssueIsEstimatedAsBeginner(): void
    {
        $service = new IssueDifficultyService();
        $result = $service->estimate([
            'title' => 'Improve README example',
            'body' => 'Small documentation update.',
            'comments' => 1,
            'labels' => [
                ['name' => 'good first issue'],
                ['name' => 'documentation'],
            ],
        ]);

        $this->assertSame('beginner', $result['level']);
        $this->assertGreaterThan(0.7, $result['confidence']);
        $this->assertNotEmpty($result['reasons']);
    }

    public function testSecurityMigrationIsEstimatedAsAdvanced(): void
    {
        $service = new IssueDifficultyService();
        $result = $service->estimate([
            'title' => 'Security migration for auth layer',
            'body' => 'Breaking change in infrastructure.',
            'comments' => 18,
            'labels' => [
                ['name' => 'security'],
                ['name' => 'migration'],
            ],
        ]);

        $this->assertSame('advanced', $result['level']);
    }
}
