<?php

use PHPUnit\Framework\TestCase;

class SignupCohortAwardServiceTest extends TestCase
{
    public function testAwardsBadgeFrameAndEmailForNewReservedSignup(): void
    {
        $calls = [];
        $service = new SignupCohortAwardService([
            'reserve_cohort_award' => function ($userId, $cohortSlug, $limit) use (&$calls) {
                $calls[] = ['reserve', $userId, $cohortSlug, $limit];
                return ['eligible' => true, 'created' => true, 'position' => 3];
            },
            'grant_badge' => function ($userId, $slug, $sourceEventId, array $snapshot) use (&$calls) {
                $calls[] = ['badge', $userId, $slug, $sourceEventId, $snapshot];
                return ['slug' => $slug, 'progress_snapshot' => $snapshot];
            },
            'grant_frame' => function ($userId, $slug, $name, $imageUrl, array $styleConfig, $sourceBadgeSlug) use (&$calls) {
                $calls[] = ['frame', $userId, $slug, $name, $imageUrl, $styleConfig, $sourceBadgeSlug];
                return ['slug' => $slug, 'style_config' => $styleConfig];
            },
            'send_email' => function ($user, $githubUser, $email, $position) use (&$calls) {
                $calls[] = ['email', $user['id'], $githubUser['login'], $email, $position];
                return ['sent' => true, 'reason' => null];
            },
        ]);

        $result = $service->awardFirstKeyEggIfEligible(
            ['id' => 7, 'email' => 'ana@example.test'],
            ['login' => 'ana-dev'],
            'ana@example.test'
        );

        $this->assertTrue($result['awarded']);
        $this->assertNull($result['reason']);
        $this->assertSame(3, $result['position']);
        $this->assertSame('first_key_first_egg', $result['badge']['slug']);
        $this->assertSame('first_key_first_egg_frame', $result['frame']['slug']);
        $this->assertSame(['sent' => true, 'reason' => null], $result['email']);
        $this->assertSame('reserve', $calls[0][0]);
        $this->assertSame('badge', $calls[1][0]);
        $this->assertSame('frame', $calls[2][0]);
        $this->assertSame('email', $calls[3][0]);
        $this->assertSame(10, $calls[0][3]);
        $this->assertSame('first_key_first_egg', $calls[1][2]);
        $this->assertSame('first_key_first_egg', $calls[2][6]);
    }

    public function testDoesNotSendEmailAgainForExistingReservation(): void
    {
        $emailCalls = 0;
        $service = new SignupCohortAwardService([
            'reserve_cohort_award' => function () {
                return ['eligible' => true, 'created' => false, 'position' => 2];
            },
            'grant_badge' => function () {
                return ['slug' => 'first_key_first_egg'];
            },
            'grant_frame' => function () {
                return ['slug' => 'first_key_first_egg_frame'];
            },
            'send_email' => function () use (&$emailCalls) {
                $emailCalls++;
                return ['sent' => true, 'reason' => null];
            },
        ]);

        $result = $service->awardFirstKeyEggIfEligible(['id' => 7]);

        $this->assertFalse($result['awarded']);
        $this->assertSame('already_awarded', $result['reason']);
        $this->assertSame(2, $result['position']);
        $this->assertSame(['sent' => false, 'reason' => 'already_awarded'], $result['email']);
        $this->assertSame(0, $emailCalls);
    }

    public function testSkipsWhenCohortIsFull(): void
    {
        $grantCalls = 0;
        $service = new SignupCohortAwardService([
            'reserve_cohort_award' => function () {
                return ['eligible' => false, 'created' => false, 'reason' => 'cohort_full'];
            },
            'grant_badge' => function () use (&$grantCalls) {
                $grantCalls++;
            },
        ]);

        $result = $service->awardFirstKeyEggIfEligible(['id' => 11]);

        $this->assertSame(['awarded' => false, 'reason' => 'cohort_full'], $result);
        $this->assertSame(0, $grantCalls);
    }

    public function testDoesNotGrantFrameOrEmailWhenBadgeDefinitionIsUnavailable(): void
    {
        $frameCalls = 0;
        $emailCalls = 0;
        $service = new SignupCohortAwardService([
            'reserve_cohort_award' => function () {
                return ['eligible' => true, 'created' => true, 'position' => 1];
            },
            'grant_badge' => function () {
                return null;
            },
            'grant_frame' => function () use (&$frameCalls) {
                $frameCalls++;
            },
            'send_email' => function () use (&$emailCalls) {
                $emailCalls++;
            },
        ]);

        $result = $service->awardFirstKeyEggIfEligible(['id' => 7]);

        $this->assertFalse($result['awarded']);
        $this->assertSame('badge_unavailable', $result['reason']);
        $this->assertSame(['sent' => false, 'reason' => 'badge_unavailable'], $result['email']);
        $this->assertSame(0, $frameCalls);
        $this->assertSame(0, $emailCalls);
    }
}
