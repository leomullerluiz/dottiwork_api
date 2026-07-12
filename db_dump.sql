-- dotti.work API v2 - clean database dump
-- Domain: open source repository suggestion portal.
-- This dump intentionally excludes legacy task/notepad/password tables and user/session data.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS
  `feedback`,
  `notepads`,
  `password_reset_tokens`,
  `todo_lists`,
  `todo_categories`,
  `user_badge_progress`,
  `user_badges`,
  `badge_definitions`,
  `user_repository_matches`,
  `repository_issue_cache`,
  `repository_cache`,
  `user_activity_events`,
  `user_repository_states`,
  `user_consents`,
  `user_preferences`,
  `user_technologies`,
  `technologies`,
  `user_profile_goals`,
  `user_profiles`,
  `user_referrals`,
  `user_invite_links`,
  `oauth_authorization_states`,
  `oauth_accounts`,
  `auth_tokens`,
  `rate_limit_buckets`,
  `users`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `login` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `display_name` varchar(150) DEFAULT NULL,
  `avatar_url` varchar(500) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `website_url` varchar(500) DEFAULT NULL,
  `github_profile_url` varchar(500) DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_login` (`login`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `auth_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_auth_tokens_token_hash` (`token_hash`),
  KEY `idx_auth_tokens_user_id` (`user_id`),
  KEY `idx_auth_tokens_expires_at` (`expires_at`),
  KEY `idx_auth_tokens_revoked_at` (`revoked_at`),
  CONSTRAINT `fk_auth_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `oauth_accounts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(50) NOT NULL,
  `provider_account_id` varchar(100) NOT NULL,
  `provider_login` varchar(100) DEFAULT NULL,
  `access_token_encrypted` text NOT NULL,
  `refresh_token_encrypted` text DEFAULT NULL,
  `token_type` varchar(50) DEFAULT NULL,
  `scope` varchar(500) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `token_last_verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_oauth_provider_account` (`provider`, `provider_account_id`),
  KEY `idx_oauth_accounts_user_id` (`user_id`),
  CONSTRAINT `fk_oauth_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `oauth_authorization_states` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `state_hash` char(64) NOT NULL,
  `return_to` varchar(255) NOT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `invite_code` varchar(64) DEFAULT NULL,
  `invite_link_id` bigint(20) UNSIGNED DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_oauth_state_hash` (`state_hash`),
  KEY `idx_oauth_state_expires_at` (`expires_at`),
  KEY `idx_oauth_state_invite_link_id` (`invite_link_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_invite_links` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(64) NOT NULL,
  `label` varchar(120) DEFAULT NULL,
  `status` enum('active','revoked','expired') NOT NULL DEFAULT 'active',
  `max_uses` int(10) UNSIGNED DEFAULT NULL,
  `uses_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_invite_links_code` (`code`),
  KEY `idx_user_invite_links_user_status` (`user_id`, `status`),
  KEY `idx_user_invite_links_expires_at` (`expires_at`),
  CONSTRAINT `fk_user_invite_links_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_referrals` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `referrer_user_id` bigint(20) UNSIGNED NOT NULL,
  `referred_user_id` bigint(20) UNSIGNED NOT NULL,
  `invite_link_id` bigint(20) UNSIGNED NOT NULL,
  `invite_code` varchar(64) NOT NULL,
  `source` varchar(50) NOT NULL DEFAULT 'github_oauth',
  `registered_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_referrals_referred_user` (`referred_user_id`),
  KEY `idx_user_referrals_referrer_user` (`referrer_user_id`),
  KEY `idx_user_referrals_invite_link` (`invite_link_id`),
  KEY `idx_user_referrals_registered_at` (`registered_at`),
  CONSTRAINT `fk_user_referrals_referrer` FOREIGN KEY (`referrer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_referrals_referred` FOREIGN KEY (`referred_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_referrals_invite_link` FOREIGN KEY (`invite_link_id`) REFERENCES `user_invite_links` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rate_limit_buckets` (
  `key_hash` char(64) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `reset_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`key_hash`),
  KEY `idx_rate_limit_reset_at` (`reset_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role` varchar(100) DEFAULT NULL,
  `seniority` enum('junior','mid','senior') DEFAULT NULL,
  `onboarding_completed` tinyint(1) NOT NULL DEFAULT 0,
  `onboarding_completed_at` datetime DEFAULT NULL,
  `public_profile_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `public_profile_slug` varchar(120) DEFAULT NULL,
  `public_profile_updated_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_profiles_user_id` (`user_id`),
  UNIQUE KEY `uq_user_profiles_public_slug` (`public_profile_slug`),
  CONSTRAINT `fk_user_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_profile_goals` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `goal` enum('first_contribution','build_portfolio','practical_experience','join_communities','long_term_projects') NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_profile_goals_user_goal` (`user_id`, `goal`),
  CONSTRAINT `fk_user_profile_goals_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `technologies` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) NOT NULL,
  `name` varchar(120) NOT NULL,
  `category` enum('language','framework','library','tool','platform','database','devops_cloud') NOT NULL,
  `github_language` varchar(100) DEFAULT NULL,
  `github_topics` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_technologies_slug` (`slug`),
  KEY `idx_technologies_category` (`category`),
  KEY `idx_technologies_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_technologies` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `technology_id` bigint(20) UNSIGNED NOT NULL,
  `proficiency_level` enum('learning','basic','daily','advanced') NOT NULL,
  `interest_level` enum('learn','contribute','mentor') NOT NULL DEFAULT 'contribute',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_technologies_user_technology` (`user_id`, `technology_id`),
  KEY `idx_user_technologies_user_id` (`user_id`),
  KEY `idx_user_technologies_technology_id` (`technology_id`),
  CONSTRAINT `fk_user_technologies_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_technologies_technology` FOREIGN KEY (`technology_id`) REFERENCES `technologies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_preferences` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `contribution_types` json NOT NULL,
  `difficulty_levels` json NOT NULL,
  `project_sizes` json NOT NULL,
  `documentation_languages` json NOT NULL,
  `organization_types` json NOT NULL,
  `activity_window_days` int(11) NOT NULL DEFAULT 90,
  `minimum_stars` int(11) NOT NULL DEFAULT 0,
  `require_good_first_issue` tinyint(1) NOT NULL DEFAULT 0,
  `require_help_wanted` tinyint(1) NOT NULL DEFAULT 0,
  `default_sort_by` enum('best_match','most_active','most_stars','beginner_friendly','recently_updated') NOT NULL DEFAULT 'best_match',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_preferences_user_id` (`user_id`),
  CONSTRAINT `fk_user_preferences_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_consents` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('essential','analytics','sentry_replay','marketing','github_oauth_notice') NOT NULL,
  `status` enum('granted','revoked') NOT NULL DEFAULT 'granted',
  `policy_version` varchar(50) NOT NULL,
  `source` enum('cookie_banner','settings','login_notice','onboarding') NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_consents_user_type` (`user_id`,`type`),
  KEY `idx_user_consents_user_status` (`user_id`,`status`),
  KEY `idx_user_consents_type_status` (`type`,`status`),
  CONSTRAINT `fk_user_consents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_repository_states` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `github_repository_id` bigint(20) UNSIGNED NOT NULL,
  `owner_login` varchar(100) NOT NULL,
  `repository_name` varchar(200) NOT NULL,
  `state` enum('saved','ignored','researching','working','pull_request_sent','contributed','archived') NOT NULL,
  `notes` text DEFAULT NULL,
  `saved_at` datetime DEFAULT NULL,
  `ignored_at` datetime DEFAULT NULL,
  `contributed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_repository_states_user_repo` (`user_id`, `github_repository_id`),
  KEY `idx_user_repository_states_user_state` (`user_id`, `state`),
  CONSTRAINT `fk_user_repository_states_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_activity_events` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `github_repository_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event_type` enum('viewed_project','saved_project','ignored_project','opened_github','started_contributing','sent_pull_request','marked_contributed','restored_project') NOT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_activity_events_user_id` (`user_id`),
  KEY `idx_user_activity_events_repository` (`github_repository_id`),
  KEY `idx_user_activity_events_type` (`event_type`),
  CONSTRAINT `fk_user_activity_events_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `badge_definitions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` varchar(500) NOT NULL,
  `category` varchar(50) NOT NULL,
  `level` varchar(30) NOT NULL DEFAULT 'bronze',
  `image_url` varchar(500) NOT NULL,
  `image_alt` varchar(255) NOT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_secret` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `criteria_type` varchar(100) NOT NULL,
  `criteria_config` json NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_badge_definitions_slug` (`slug`),
  KEY `idx_badge_definitions_active_order` (`is_active`, `display_order`),
  KEY `idx_badge_definitions_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_badges` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `badge_id` bigint(20) UNSIGNED NOT NULL,
  `slug` varchar(100) NOT NULL,
  `awarded_at` datetime NOT NULL,
  `notification_seen_at` datetime DEFAULT NULL,
  `source_event_id` bigint(20) UNSIGNED DEFAULT NULL,
  `progress_snapshot` json DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_badges_user_badge` (`user_id`, `badge_id`),
  KEY `idx_user_badges_user_awarded` (`user_id`, `awarded_at`),
  KEY `idx_user_badges_user_notification_seen` (`user_id`, `notification_seen_at`, `awarded_at`),
  KEY `idx_user_badges_slug` (`slug`),
  KEY `idx_user_badges_source_event` (`source_event_id`),
  CONSTRAINT `fk_user_badges_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_badges_badge` FOREIGN KEY (`badge_id`) REFERENCES `badge_definitions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_user_badges_source_event` FOREIGN KEY (`source_event_id`) REFERENCES `user_activity_events` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_badge_progress` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `badge_id` bigint(20) UNSIGNED NOT NULL,
  `current_value` decimal(10,2) NOT NULL DEFAULT 0,
  `target_value` decimal(10,2) NOT NULL DEFAULT 1,
  `progress_data` json DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_badge_progress_user_badge` (`user_id`, `badge_id`),
  CONSTRAINT `fk_user_badge_progress_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_badge_progress_badge` FOREIGN KEY (`badge_id`) REFERENCES `badge_definitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `repository_cache` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `github_repository_id` bigint(20) UNSIGNED NOT NULL,
  `owner_login` varchar(100) NOT NULL,
  `repository_name` varchar(200) NOT NULL,
  `repository_data` json NOT NULL,
  `health_data` json DEFAULT NULL,
  `fetched_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_repository_cache_github_id` (`github_repository_id`),
  KEY `idx_repository_cache_owner_repo` (`owner_login`, `repository_name`),
  KEY `idx_repository_cache_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `repository_issue_cache` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `github_repository_id` bigint(20) UNSIGNED NOT NULL,
  `github_issue_id` bigint(20) UNSIGNED NOT NULL,
  `issue_number` int(11) NOT NULL,
  `issue_data` json NOT NULL,
  `difficulty_estimation` json DEFAULT NULL,
  `fetched_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_repository_issue_cache_repo_issue` (`github_repository_id`, `github_issue_id`),
  KEY `idx_repository_issue_cache_repo_number` (`github_repository_id`, `issue_number`),
  KEY `idx_repository_issue_cache_expires_at` (`expires_at`),
  CONSTRAINT `fk_repository_issue_cache_repository` FOREIGN KEY (`github_repository_id`) REFERENCES `repository_cache` (`github_repository_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_repository_matches` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `github_repository_id` bigint(20) UNSIGNED NOT NULL,
  `match_score` decimal(5,2) NOT NULL,
  `score_breakdown` json NOT NULL,
  `reasons` json NOT NULL,
  `generated_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_repository_matches_user_repo` (`user_id`, `github_repository_id`),
  KEY `idx_user_repository_matches_user_score` (`user_id`, `match_score`),
  KEY `idx_user_repository_matches_expires_at` (`expires_at`),
  KEY `idx_user_repository_matches_repository` (`github_repository_id`),
  CONSTRAINT `fk_user_repository_matches_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_repository_matches_repository` FOREIGN KEY (`github_repository_id`) REFERENCES `repository_cache` (`github_repository_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `technologies` (`slug`, `name`, `category`, `github_language`, `github_topics`, `is_active`, `display_order`, `created_at`, `updated_at`) VALUES
('javascript', 'JavaScript', 'language', 'JavaScript', JSON_ARRAY('javascript'), 1, 10, NOW(), NOW()),
('typescript', 'TypeScript', 'language', 'TypeScript', JSON_ARRAY('typescript'), 1, 20, NOW(), NOW()),
('react', 'React', 'framework', NULL, JSON_ARRAY('react', 'reactjs'), 1, 30, NOW(), NOW()),
('nextjs', 'Next.js', 'framework', NULL, JSON_ARRAY('nextjs', 'next-js'), 1, 40, NOW(), NOW()),
('nodejs', 'Node.js', 'platform', 'JavaScript', JSON_ARRAY('nodejs', 'node'), 1, 50, NOW(), NOW()),
('php', 'PHP', 'language', 'PHP', JSON_ARRAY('php'), 1, 60, NOW(), NOW()),
('laravel', 'Laravel', 'framework', 'PHP', JSON_ARRAY('laravel'), 1, 70, NOW(), NOW()),
('wordpress', 'WordPress', 'platform', 'PHP', JSON_ARRAY('wordpress'), 1, 80, NOW(), NOW()),
('python', 'Python', 'language', 'Python', JSON_ARRAY('python'), 1, 90, NOW(), NOW()),
('django', 'Django', 'framework', 'Python', JSON_ARRAY('django'), 1, 100, NOW(), NOW()),
('java', 'Java', 'language', 'Java', JSON_ARRAY('java'), 1, 110, NOW(), NOW()),
('spring', 'Spring', 'framework', 'Java', JSON_ARRAY('spring', 'spring-boot'), 1, 120, NOW(), NOW()),
('vue', 'Vue', 'framework', NULL, JSON_ARRAY('vue', 'vuejs'), 1, 130, NOW(), NOW()),
('angular', 'Angular', 'framework', 'TypeScript', JSON_ARRAY('angular'), 1, 140, NOW(), NOW()),
('tailwindcss', 'TailwindCSS', 'library', NULL, JSON_ARRAY('tailwindcss', 'tailwind'), 1, 150, NOW(), NOW()),
('react-query', 'React Query', 'library', 'TypeScript', JSON_ARRAY('react-query', 'tanstack-query'), 1, 160, NOW(), NOW()),
('redux', 'Redux', 'library', 'JavaScript', JSON_ARRAY('redux'), 1, 170, NOW(), NOW()),
('zod', 'Zod', 'library', 'TypeScript', JSON_ARRAY('zod'), 1, 180, NOW(), NOW()),
('docker', 'Docker', 'devops_cloud', NULL, JSON_ARRAY('docker'), 1, 190, NOW(), NOW()),
('github-actions', 'GitHub Actions', 'devops_cloud', NULL, JSON_ARRAY('github-actions', 'actions'), 1, 200, NOW(), NOW()),
('mysql', 'MySQL', 'database', NULL, JSON_ARRAY('mysql'), 1, 210, NOW(), NOW()),
('postgresql', 'PostgreSQL', 'database', NULL, JSON_ARRAY('postgresql', 'postgres'), 1, 220, NOW(), NOW()),
('mongodb', 'MongoDB', 'database', NULL, JSON_ARRAY('mongodb'), 1, 230, NOW(), NOW()),
('firebase', 'Firebase', 'platform', NULL, JSON_ARRAY('firebase'), 1, 240, NOW(), NOW()),
('aws', 'AWS', 'devops_cloud', NULL, JSON_ARRAY('aws', 'amazon-web-services'), 1, 250, NOW(), NOW()),
('cloudflare', 'Cloudflare', 'devops_cloud', NULL, JSON_ARRAY('cloudflare'), 1, 260, NOW(), NOW()),
('nginx', 'Nginx', 'tool', NULL, JSON_ARRAY('nginx'), 1, 270, NOW(), NOW()),
('playwright', 'Playwright', 'tool', 'TypeScript', JSON_ARRAY('playwright'), 1, 280, NOW(), NOW()),
('vitest', 'Vitest', 'tool', 'TypeScript', JSON_ARRAY('vitest'), 1, 290, NOW(), NOW()),
('jest', 'Jest', 'tool', 'JavaScript', JSON_ARRAY('jest'), 1, 300, NOW(), NOW());

INSERT INTO `badge_definitions` (
  `slug`, `name`, `description`, `category`, `level`, `image_url`, `image_alt`, `icon`,
  `is_active`, `is_secret`, `display_order`, `criteria_type`, `criteria_config`,
  `created_at`, `updated_at`
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
('alpha_user', 'Alpha user', 'Joined by 2026-10-30 and completed the first goals.', 'special', 'platinum', '/uploads/media/badges/alpha_user.png', 'dotti.work alpha user badge', 'star', 1, 0, 180, 'alpha_user', JSON_OBJECT('deadline', '2026-10-30', 'threshold', 5), NOW(), NOW());

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
