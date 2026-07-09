<?php

use PHPUnit\Framework\TestCase;

class EmailTemplateRendererTest extends TestCase
{
    /**
     * @dataProvider transactionalTemplates
     */
    public function testHtmlTemplateUsesDottiLayoutAndRendersVariables($slug, array $variables): void
    {
        $html = EmailTemplateRenderer::renderHtml($slug, $variables);

        $this->assertStringContainsString('lang="pt-BR"', $html);
        $this->assertStringContainsString('dotti<span style="color:#f05d4f;">.</span>work', $html);
        $this->assertStringContainsString('max-width:600px', $html);
        $this->assertStringContainsString('background:#f5f8fb', $html);
        $this->assertStringContainsString('border-radius:8px', $html);
        $this->assertStringNotContainsString('{{', $html);
        $this->assertStringNotContainsString('Bearer ', $html);
        $this->assertStringNotContainsString('stack trace', strtolower($html));
        $this->assertStringNotContainsString('secret', strtolower($html));
        $this->assertStringNotContainsString('token', strtolower($html));
    }

    /**
     * @dataProvider transactionalTemplates
     */
    public function testTextTemplateRendersPlainTextFallback($slug, array $variables): void
    {
        $text = EmailTemplateRenderer::renderText($slug, $variables);

        $this->assertIsString($text);
        $this->assertStringNotContainsString('<table', $text);
        $this->assertStringNotContainsString('{{', $text);
        $this->assertStringNotContainsString('Bearer ', $text);
        $this->assertStringNotContainsString('token', strtolower($text));
    }

    public function transactionalTemplates(): array
    {
        return [
            'welcome_github_signup' => [
                'welcome_github_signup',
                [
                    'name' => 'Ana &lt;Dev&gt;',
                    'github_login' => ' (@ana-dev)',
                    'onboarding_url' => 'https://app.dotti.work/onboarding',
                    'matches_url' => 'https://app.dotti.work/matches',
                ],
            ],
            'account_deleted' => [
                'account_deleted',
                [
                    'name' => 'Ana &lt;Dev&gt;',
                    'deleted_at' => '2026-07-08 12:00:00',
                    'home_url' => 'https://app.dotti.work/',
                ],
            ],
            'github_disconnected' => [
                'github_disconnected',
                [
                    'name' => 'Ana &lt;Dev&gt;',
                    'github_login' => '@ana-dev',
                    'disconnected_at' => '2026-07-08 12:00:00',
                    'settings_url' => 'https://app.dotti.work/settings',
                ],
            ],
            'data_export_requested' => [
                'data_export_requested',
                [
                    'name' => 'Ana &lt;Dev&gt;',
                    'exported_at' => '2026-07-08 12:00:00',
                    'privacy_url' => 'https://app.dotti.work/settings/privacy',
                ],
            ],
        ];
    }
}
