-- Badge notification visibility state.

START TRANSACTION;

ALTER TABLE user_badges
  ADD COLUMN notification_seen_at DATETIME NULL AFTER awarded_at,
  ADD KEY idx_user_badges_user_notification_seen (user_id, notification_seen_at, awarded_at);

UPDATE user_badges
SET notification_seen_at = awarded_at
WHERE notification_seen_at IS NULL;

COMMIT;
