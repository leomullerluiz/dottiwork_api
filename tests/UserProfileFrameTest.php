<?php

use PHPUnit\Framework\TestCase;

class UserProfileFrameTest extends TestCase
{
    public function testToResponseDecodesStyleConfig(): void
    {
        $response = UserProfileFrame::toResponse([
            'slug' => 'first_key_first_egg_frame',
            'name' => 'First to the key frame',
            'image_url' => null,
            'style_config' => '{"accent":"#f05d4f","ring":"#f8c14a"}',
            'source_badge_slug' => 'first_key_first_egg',
            'awarded_at' => '2026-07-13 15:00:00',
        ]);

        $this->assertSame('first_key_first_egg_frame', $response['slug']);
        $this->assertNull($response['image_url']);
        $this->assertSame('#f05d4f', $response['style_config']['accent']);
        $this->assertSame('first_key_first_egg', $response['source_badge_slug']);
    }
}
