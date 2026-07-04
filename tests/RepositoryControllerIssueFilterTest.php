<?php

use PHPUnit\Framework\TestCase;

class RepositoryControllerIssueFilterTest extends TestCase
{
    public function testIssueFilterUsesNormalizedDifficultyAndLabels(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/repositories/owner/repo/issues?difficulty=beginner&label=good%20first%20issue';
        $_SERVER['SCRIPT_NAME'] = '/api/index.php';
        $_GET = [
            'difficulty' => 'beginner',
            'label' => 'good first issue',
        ];
        $_COOKIE = [];

        $request = new Request();
        $controller = new RepositoryController();
        $method = new ReflectionMethod(RepositoryController::class, 'filterIssues');
        $method->setAccessible(true);

        $items = $method->invoke($controller, [
            [
                'difficulty' => 'easy',
                'labels' => [['name' => 'good first issue']],
            ],
            [
                'difficulty' => 'hard',
                'labels' => [['name' => 'security']],
            ],
        ], $request);

        $this->assertCount(1, $items);
        $this->assertSame('easy', $items[0]['difficulty']);
    }
}
