-- First to the key / first to the egg limited signup achievement.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS signup_cohort_awards (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cohort_slug VARCHAR(100) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  position INT NOT NULL,
  awarded_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_signup_cohort_user (cohort_slug, user_id),
  UNIQUE KEY uq_signup_cohort_position (cohort_slug, position),
  KEY idx_signup_cohort_awards_user_id (user_id),
  CONSTRAINT fk_signup_cohort_awards_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_profile_frames (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(100) NOT NULL,
  name VARCHAR(150) NOT NULL,
  image_url VARCHAR(500) NULL,
  style_config JSON NULL,
  source_badge_slug VARCHAR(100) NULL,
  awarded_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_profile_frames_user_slug (user_id, slug),
  KEY idx_user_profile_frames_user_awarded (user_id, awarded_at),
  KEY idx_user_profile_frames_source_badge (source_badge_slug),
  CONSTRAINT fk_user_profile_frames_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO badge_definitions (
  slug, name, description, category, level, image_url, image_alt, icon,
  is_active, is_secret, display_order, criteria_type, criteria_config,
  created_at, updated_at
) VALUES (
  'first_key_first_egg',
  'First to the key! First to the egg!',
  'Awarded to the first 10 new members after this milestone opened.',
  'special',
  'legendary',
  '/uploads/media/badges/first_key_first_egg.png',
  'First to the key and first to the egg badge',
  'key-round',
  1,
  0,
  190,
  'signup_cohort_first_n',
  JSON_OBJECT('cohort', 'first_key_first_egg', 'limit', 10, 'target', 1),
  NOW(),
  NOW()
) ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  category = VALUES(category),
  level = VALUES(level),
  image_url = VALUES(image_url),
  image_alt = VALUES(image_alt),
  icon = VALUES(icon),
  is_active = VALUES(is_active),
  is_secret = VALUES(is_secret),
  display_order = VALUES(display_order),
  criteria_type = VALUES(criteria_type),
  criteria_config = VALUES(criteria_config),
  updated_at = NOW();

COMMIT;
