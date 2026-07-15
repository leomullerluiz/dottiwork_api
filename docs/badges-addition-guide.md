# Adding New Badges

This guide describes the recommended backend workflow for adding badges to dotti.work.

## Overview

The frontend does not need to know award rules. It consumes:

- `GET /badges` for the public badge catalog.
- `GET /me/badges` for earned badges, progress, and recent awards.
- `POST /me/badges/evaluate` for manual recalculation for the authenticated user.

Every badge starts in `badge_definitions`. When a user meets the criteria, the backend stores one unique row in `user_badges`.

Badge visuals are rendered by the frontend from badge metadata such as `slug`, `level`, and `icon`.

## When a Seed Is Enough

If the criteria already exists in `BadgeProgressService`, adding a new row to `badge_definitions` is enough.

Currently supported criteria:

- `profile_onboarding_completed`
- `technology_count`
- `preferences_defined`
- `activity_event_count`
- `activity_event_exists`
- `activity_event_or_repository_state_exists`
- `repository_state_count`
- `repository_state_exists`
- `repository_language_saved_count`
- `issue_label_interaction_count`
- `activity_distinct_days`
- `referral_count`
- `alpha_user`

Example:

```sql
INSERT INTO badge_definitions (
  slug, name, description, category, level, image_alt, icon,
  is_active, is_secret, display_order, criteria_type, criteria_config,
  created_at, updated_at
) VALUES (
  'view_20_projects',
  'Open map',
  'Viewed 20 open source projects.',
  'discovery',
  'gold',
  'Advanced project exploration badge',
  'map',
  1,
  0,
  190,
  'activity_event_count',
  JSON_OBJECT('event_type', 'viewed_project', 'threshold', 20, 'distinct_repositories', true),
  NOW(),
  NOW()
) ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  category = VALUES(category),
  level = VALUES(level),
  image_alt = VALUES(image_alt),
  icon = VALUES(icon),
  is_active = VALUES(is_active),
  is_secret = VALUES(is_secret),
  display_order = VALUES(display_order),
  criteria_type = VALUES(criteria_type),
  criteria_config = VALUES(criteria_config),
  updated_at = NOW();
```

## When Code Changes Are Needed

Create a new `criteria_type` only when the rule cannot be expressed with the existing criteria.

Steps:

1. Add the calculation to `BadgeProgressService::currentValue`.
2. If the calculation reads from the database, create a public method in the service and inject it through the constructor `$deps` array.
3. Use `criteria_config` for variable parameters such as `threshold`, `event_type`, `state`, `label`, or a day window.
4. Add tests in `tests/BadgeProgressServiceTest.php`.
5. Create a migration that seeds the badge.
6. Update `db_dump.sql` if the badge should exist in clean installs.
7. Update `openapi.yaml` if the public badge contract changes.

## When Evaluation Runs

The evaluator already runs after:

- Profile or onboarding updates.
- Technology updates.
- Preference updates.
- Repository activity events.
- Repository state changes.
- Local MVP data imports.
- Effective referral registration from an invite link.

If a badge depends on a new flow, call:

```php
(new BadgeEvaluatorService())->evaluateUser($userId);
```

If a `user_activity_events.id` explains the award, prefer:

```php
(new BadgeEvaluatorService())->evaluateAfterActivityEvent($userId, $eventType, $eventId);
```

## Recalculating Existing Users

After publishing a retroactive badge:

1. Apply the migration/seed.
2. Run `POST /me/badges/evaluate` for a specific user during testing.
3. For a bulk recalculation, create an admin script that iterates over active `users` rows and calls `BadgeEvaluatorService::evaluateUser($userId)`.

`user_badges` has a unique index on `(user_id, badge_id)`, so recalculation is idempotent.

## Checklist

- `slug` is unique, stable, and snake_case.
- `name`, `description`, and `image_alt` are clear for the frontend.
- `icon`, `level`, and `slug` provide enough metadata for frontend-rendered visuals.
- `criteria_type` is known by the backend.
- `criteria_config` includes `threshold` or `target` when applicable.
- Unit tests cover any new criteria.
- `GET /badges` returns the definition.
- `GET /me/badges` shows progress and `awarded_at` when earned.
- `db_dump.sql` and relevant migrations are aligned.
