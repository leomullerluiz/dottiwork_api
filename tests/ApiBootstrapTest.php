<?php

use PHPUnit\Framework\TestCase;

class ApiBootstrapTest extends TestCase
{
    public function testHealthEndpointBootstrapsBeforeCorsPolicyIsReferenced(): void
    {
        $command = PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/run_api_health.php');
        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));

        $payload = json_decode(implode("\n", $output), true);

        $this->assertIsArray($payload);
        $this->assertTrue($payload['success']);
        $this->assertSame('online', $payload['data']['status']);
    }
}
