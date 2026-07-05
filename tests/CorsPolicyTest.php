<?php

use PHPUnit\Framework\TestCase;

class CorsPolicyTest extends TestCase
{
    public function testProductionDefaultsIncludeWwwDottiworkOrigin(): void
    {
        $origins = CorsPolicy::allowedOrigins('', 'production');

        $this->assertContains('https://dotti.work', $origins);
        $this->assertContains('https://dottiwork.com', $origins);
        $this->assertContains('https://www.dottiwork.com', $origins);
    }

    public function testConfiguredDottiworkOriginAllowsWwwAlias(): void
    {
        $origins = CorsPolicy::allowedOrigins('https://dottiwork.com,http://localhost:3000', 'production');

        $this->assertContains('https://dottiwork.com', $origins);
        $this->assertContains('https://www.dottiwork.com', $origins);
        $this->assertContains('http://localhost:3000', $origins);
    }

    public function testConfiguredWwwOriginAllowsApexAlias(): void
    {
        $origins = CorsPolicy::allowedOrigins('https://www.dottiwork.com', 'production');

        $this->assertContains('https://www.dottiwork.com', $origins);
        $this->assertContains('https://dottiwork.com', $origins);
    }
}
