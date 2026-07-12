-- Badges and achievements.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS badge_definitions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(100) NOT NULL,
  name VARCHAR(150) NOT NULL,
  description VARCHAR(500) NOT NULL,
  category VARCHAR(50) NOT NULL,
  level VARCHAR(30) NOT NULL DEFAULT 'bronze',
  image_url VARCHAR(500) NOT NULL,
  image_alt VARCHAR(255) NOT NULL,
  icon VARCHAR(100) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_secret TINYINT(1) NOT NULL DEFAULT 0,
  display_order INT NOT NULL DEFAULT 0,
  criteria_type VARCHAR(100) NOT NULL,
  criteria_config JSON NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_badge_definitions_slug (slug),
  KEY idx_badge_definitions_active_order (is_active, display_order),
  KEY idx_badge_definitions_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_badges (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  badge_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(100) NOT NULL,
  awarded_at DATETIME NOT NULL,
  notification_seen_at DATETIME NULL,
  source_event_id BIGINT UNSIGNED NULL,
  progress_snapshot JSON NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_badges_user_badge (user_id, badge_id),
  KEY idx_user_badges_user_awarded (user_id, awarded_at),
  KEY idx_user_badges_user_notification_seen (user_id, notification_seen_at, awarded_at),
  KEY idx_user_badges_slug (slug),
  KEY idx_user_badges_source_event (source_event_id),
  CONSTRAINT fk_user_badges_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_user_badges_badge FOREIGN KEY (badge_id) REFERENCES badge_definitions (id) ON DELETE RESTRICT,
  CONSTRAINT fk_user_badges_source_event FOREIGN KEY (source_event_id) REFERENCES user_activity_events (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_badge_progress (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  badge_id BIGINT UNSIGNED NOT NULL,
  current_value DECIMAL(10,2) NOT NULL DEFAULT 0,
  target_value DECIMAL(10,2) NOT NULL DEFAULT 1,
  progress_data JSON NULL,
  completed_at DATETIME NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_badge_progress_user_badge (user_id, badge_id),
  CONSTRAINT fk_user_badge_progress_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_user_badge_progress_badge FOREIGN KEY (badge_id) REFERENCES badge_definitions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO badge_definitions (
  slug, name, description, category, level, image_url, image_alt, icon,
  is_active, is_secret, display_order, criteria_type, criteria_config,
  created_at, updated_at
) VALUES
('complete_profile', 'Complete profile', 'Completed the initial onboarding.', 'onboarding', 'bronze', '/uploads/media/badges/complete_profile.png', 'Complete profile badge', 'user-check', 1, 0, 10, 'profile_onboarding_completed', JSON_OBJECT('target', 1), NOW(), NOW()),
('stack_configured', 'Stack configured', 'Added at least 3 technologies to the profile.', 'onboarding', 'bronze', '/uploads/media/badges/stack_configured.png', 'Stack configured badge', 'code', 1, 0, 20, 'technology_count', JSON_OBJECT('threshold', 3), NOW(), NOW()),
('preferences_defined', 'Preferences defined', 'Saved matching preferences.', 'onboarding', 'bronze', '/uploads/media/badges/preferences_defined.png', 'Preferences defined badge', 'sliders-horizontal', 1, 0, 30, 'preferences_defined', JSON_OBJECT('target', 1), NOW(), NOW()),
('explorer', 'Explorer', 'Viewed 5 open source projects.', 'discovery', 'silver', '/uploads/media/badges/explorer.png', 'Open source explorer badge', 'compass', 1, 0, 40, 'activity_event_count', JSON_OBJECT('event_type', 'viewed_project', 'threshold', 5, 'distinct_repositories', true), NOW(), NOW()),
('curator', 'Curator', 'Saved 3 projects to follow.', 'discovery', 'silver', '/uploads/media/badges/curator.png', 'Project curator badge', 'bookmark', 1, 0, 50, 'repository_state_count', JSON_OBJECT('states', JSON_ARRAY('saved'), 'threshold', 3), NOW(), NOW()),
('technical_focus', 'Technical focus', 'Saved projects from at least 3 different languages.', 'discovery', 'silver', '/uploads/media/badges/technical_focus.png', 'Technical focus badge', 'layers', 1, 0, 60, 'repository_language_saved_count', JSON_OBJECT('threshold', 3), NOW(), NOW()),
('first_action', 'First action', 'Opened a repository on GitHub for the first time.', 'contribution', 'bronze', '/uploads/media/badges/first_action.png', 'First GitHub action badge', 'external-link', 1, 0, 70, 'activity_event_exists', JSON_OBJECT('event_type', 'opened_github', 'target', 1), NOW(), NOW()),
('started_contributing', 'Started contributing', 'Marked a project as in contribution.', 'contribution', 'silver', '/uploads/media/badges/started_contributing.png', 'Started contributing badge', 'rocket', 1, 0, 80, 'activity_event_or_repository_state_exists', JSON_OBJECT('event_type', 'started_contributing', 'states', JSON_ARRAY('researching', 'working'), 'target', 1), NOW(), NOW()),
('first_pr', 'First PR', 'Marked the first pull request as sent.', 'contribution', 'gold', '/uploads/media/badges/first_pr.png', 'First pull request badge', 'git-pull-request', 1, 0, 90, 'activity_event_or_repository_state_exists', JSON_OBJECT('event_type', 'sent_pull_request', 'states', JSON_ARRAY('pull_request_sent'), 'target', 1), NOW(), NOW()),
('first_contribution', 'First contribution', 'Marked the first contribution as completed.', 'contribution', 'gold', '/uploads/media/badges/first_contribution.png', 'First open source contribution badge', 'award', 1, 0, 100, 'activity_event_or_repository_state_exists', JSON_OBJECT('event_type', 'marked_contributed', 'states', JSON_ARRAY('contributed'), 'target', 1), NOW(), NOW()),
('beginner_friend', 'Beginner ally', 'Interacted with 3 projects with good first issues.', 'quality', 'silver', '/uploads/media/badges/beginner_friend.png', 'Beginner ally badge', 'heart-handshake', 1, 0, 110, 'issue_label_interaction_count', JSON_OBJECT('label', 'good first issue', 'threshold', 3), NOW(), NOW()),
('help_wanted_helper', 'Helpful contributor', 'Interacted with 3 projects with help wanted issues.', 'quality', 'silver', '/uploads/media/badges/help_wanted_helper.png', 'Helpful contributor badge', 'hand-heart', 1, 0, 120, 'issue_label_interaction_count', JSON_OBJECT('label', 'help wanted', 'threshold', 3), NOW(), NOW()),
('exploration_streak', 'Exploration streak', 'Interacted with projects on 3 different days.', 'consistency', 'silver', '/uploads/media/badges/exploration_streak.png', 'Exploration streak badge', 'calendar-days', 1, 0, 130, 'activity_distinct_days', JSON_OBJECT('threshold', 3), NOW(), NOW()),
('open_source_week', 'Open source week', 'Had activity on 5 days within 7 days.', 'consistency', 'gold', '/uploads/media/badges/open_source_week.png', 'Open source week badge', 'calendar-check', 1, 0, 140, 'activity_distinct_days', JSON_OBJECT('threshold', 5, 'within_days', 7), NOW(), NOW()),
('invite_friend', 'Invite a friend', 'Invited one person who created a dotti.work account.', 'special', 'silver', '/uploads/media/badges/invite_friend.png', 'Invite a friend badge', 'user-plus', 1, 0, 150, 'referral_count', JSON_OBJECT('threshold', 1), NOW(), NOW()),
('invite_5_friends', 'Open source connector', 'Invited 5 people who created dotti.work accounts.', 'special', 'gold', '/uploads/media/badges/invite_5_friends.png', 'Open source connector badge', 'users', 1, 0, 160, 'referral_count', JSON_OBJECT('threshold', 5), NOW(), NOW()),
('invite_10_friends', 'Community in motion', 'Invited 10 people who created dotti.work accounts.', 'special', 'platinum', '/uploads/media/badges/invite_10_friends.png', 'Community in motion badge', 'sparkles', 1, 0, 170, 'referral_count', JSON_OBJECT('threshold', 10), NOW(), NOW()),
('alpha_user', 'Alpha user', 'Joined by 2026-10-30 and completed the first goals.', 'special', 'platinum', '/uploads/media/badges/alpha_user.png', 'dotti.work alpha user badge', 'star', 1, 0, 180, 'alpha_user', JSON_OBJECT('deadline', '2026-10-30', 'threshold', 5), NOW(), NOW())
ON DUPLICATE KEY UPDATE
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
