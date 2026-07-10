-- Badge image assets from uploads/media/badges.

START TRANSACTION;

UPDATE badge_definitions
SET image_url = CONCAT('/uploads/media/badges/', slug, '.png'),
    updated_at = NOW()
WHERE slug IN (
  'complete_profile',
  'stack_configured',
  'preferences_defined',
  'explorer',
  'curator',
  'technical_focus',
  'first_action',
  'started_contributing',
  'first_pr',
  'first_contribution',
  'beginner_friend',
  'help_wanted_helper',
  'exploration_streak',
  'open_source_week',
  'invite_friend',
  'invite_5_friends',
  'invite_10_friends',
  'alpha_user'
);

COMMIT;

