<?php

use PHPUnit\Framework\TestCase;

class UserBadgeTest extends TestCase
{
    public function testToResponseIncludesNotificationSeenState(): void
    {
        $unseen = UserBadge::toResponse($this->row([
            'notification_seen_at' => null,
        ]));

        $this->assertSame(55, $unseen['id']);
        $this->assertFalse($unseen['notification_seen']);
        $this->assertNull($unseen['notification_seen_at']);

        $seen = UserBadge::toResponse($this->row([
            'notification_seen_at' => '2026-07-09 10:30:00',
        ]));

        $this->assertTrue($seen['notification_seen']);
        $this->assertSame('2026-07-09 10:30:00', $seen['notification_seen_at']);
    }

    private function row(array $overrides = []): array
    {
        return array_merge([
            'id' => 55,
            'user_id' => 7,
            'badge_id' => 3,
            'slug' => 'first_pr',
            'awarded_at' => '2026-07-09 10:00:00',
            'notification_seen_at' => null,
            'source_event_id' => null,
            'progress_snapshot' => '{"current_value":1}',
            'created_at' => '2026-07-09 10:00:00',
            'updated_at' => '2026-07-09 10:00:00',
            'name' => 'Primeiro PR',
            'description' => 'Marcou o primeiro pull request como enviado.',
            'category' => 'contribution',
            'level' => 'gold',
            'image_url' => '/uploads/media/badges/first_pr.png',
            'image_alt' => 'Insignia de primeiro pull request',
            'icon' => 'git-pull-request',
            'is_secret' => false,
            'display_order' => 90,
            'criteria_type' => 'activity_event_or_repository_state_exists',
            'criteria_config' => '{"target":1}',
        ], $overrides);
    }
}
