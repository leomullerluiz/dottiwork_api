<?php

use PHPUnit\Framework\TestCase;

class GitHubClientTest extends TestCase
{
    public function testParsesLastPageFromGithubLinkHeader(): void
    {
        $client = new GitHubClient();
        $method = new ReflectionMethod(GitHubClient::class, 'lastPageFromLinkHeader');
        $method->setAccessible(true);

        $header = '<https://api.github.com/repositories/1/contributors?per_page=1&page=2>; rel="next", <https://api.github.com/repositories/1/contributors?per_page=1&page=42>; rel="last"';

        $this->assertSame(42, $method->invoke($client, $header));
        $this->assertNull($method->invoke($client, '<https://api.github.com/repositories/1/contributors?per_page=1&page=2>; rel="next"'));
        $this->assertNull($method->invoke($client, null));
    }

    public function testReturnsLatestHeaderValue(): void
    {
        $client = new GitHubClient();
        $method = new ReflectionMethod(GitHubClient::class, 'headerValue');
        $method->setAccessible(true);

        $this->assertSame('second', $method->invoke($client, ['link' => ['first', 'second']], 'Link'));
        $this->assertNull($method->invoke($client, [], 'Link'));
    }
}
