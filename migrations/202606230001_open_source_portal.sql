-- dotti.work API v2 - Open source project discovery portal
-- Non-destructive migration: legacy task tables are preserved, but no longer exposed by routes.

START TRANSACTION;

-- Users: adapt existing password-based table to GitHub OAuth.
ALTER TABLE users
  MODIFY login VARCHAR(100) NULL,
  MODIFY email VARCHAR(255) NULL,
  MODIFY senha VARCHAR(255) NULL,
  MODIFY first_name VARCHAR(30) NULL,
  MODIFY last_name VARCHAR(30) NULL;

ALTER TABLE users
  ADD COLUMN display_name VARCHAR(150) NULL AFTER email,
  ADD COLUMN avatar_url VARCHAR(500) NULL AFTER display_name,
  ADD COLUMN bio TEXT NULL AFTER avatar_url,
  ADD COLUMN location VARCHAR(255) NULL AFTER bio,
  ADD COLUMN company VARCHAR(255) NULL AFTER location,
  ADD COLUMN website_url VARCHAR(500) NULL AFTER company,
  ADD COLUMN github_profile_url VARCHAR(500) NULL AFTER website_url,
  ADD COLUMN last_login_at DATETIME NULL AFTER github_profile_url,
  ADD COLUMN updated_at DATETIME NULL AFTER created_at,
  ADD COLUMN deleted_at DATETIME NULL AFTER updated_at;

UPDATE users SET updated_at = COALESCE(updated_at, created_at, NOW()) WHERE updated_at IS NULL;

-- Auth tokens: keep old token column temporarily, but use token_hash from now on.
ALTER TABLE auth_tokens
  MODIFY token VARCHAR(255) NULL,
  ADD COLUMN token_hash CHAR(64) NULL AFTER token,
  ADD COLUMN revoked_at DATETIME NULL AFTER expires_at,
  ADD COLUMN last_used_at DATETIME NULL AFTER revoked_at,
  ADD COLUMN ip_hash CHAR(64) NULL AFTER last_used_at,
  ADD COLUMN user_agent VARCHAR(500) NULL AFTER ip_hash;

ALTER TABLE auth_tokens ADD INDEX idx_auth_tokens_token_hash (token_hash);
ALTER TABLE auth_tokens ADD INDEX idx_auth_tokens_revoked_at (revoked_at);

CREATE TABLE IF NOT EXISTS oauth_accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(50) NOT NULL,
  provider_account_id VARCHAR(100) NOT NULL,
  provider_login VARCHAR(100) NULL,
  access_token_encrypted TEXT NOT NULL,
  refresh_token_encrypted TEXT NULL,
  token_type VARCHAR(50) NULL,
  scope VARCHAR(500) NULL,
  token_expires_at DATETIME NULL,
  token_last_verified_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_oauth_provider_account (provider, provider_account_id),
  KEY idx_oauth_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS oauth_authorization_states (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  state_hash CHAR(64) NOT NULL,
  return_to VARCHAR(255) NOT NULL,
  ip_hash CHAR(64) NULL,
  user_agent VARCHAR(500) NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_oauth_state_hash (state_hash),
  KEY idx_oauth_state_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limit_buckets (
  key_hash CHAR(64) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  reset_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (key_hash),
  KEY idx_rate_limit_reset_at (reset_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  role VARCHAR(100) NULL,
  seniority ENUM('junior','mid','senior') NULL,
  onboarding_completed TINYINT(1) NOT NULL DEFAULT 0,
  onboarding_completed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_profiles_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_profile_goals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  goal ENUM(
    'first_contribution',
    'build_portfolio',
    'practical_experience',
    'join_communities',
    'long_term_projects'
  ) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_profile_goals_user_goal (user_id, goal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS technologies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(100) NOT NULL,
  name VARCHAR(120) NOT NULL,
  category ENUM('language','framework','library','tool','platform','database','devops_cloud') NOT NULL,
  github_language VARCHAR(100) NULL,
  github_topics JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  display_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_technologies_slug (slug),
  KEY idx_technologies_category (category),
  KEY idx_technologies_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_technologies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  technology_id BIGINT UNSIGNED NOT NULL,
  proficiency_level ENUM('learning','basic','daily','advanced') NOT NULL,
  interest_level ENUM('learn','contribute','mentor') NOT NULL DEFAULT 'contribute',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_technologies_user_technology (user_id, technology_id),
  KEY idx_user_technologies_user_id (user_id),
  KEY idx_user_technologies_technology_id (technology_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_preferences (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  contribution_types JSON NOT NULL,
  difficulty_levels JSON NOT NULL,
  project_sizes JSON NOT NULL,
  documentation_languages JSON NOT NULL,
  organization_types JSON NOT NULL,
  activity_window_days INT NOT NULL DEFAULT 90,
  minimum_stars INT NOT NULL DEFAULT 0,
  require_good_first_issue TINYINT(1) NOT NULL DEFAULT 0,
  require_help_wanted TINYINT(1) NOT NULL DEFAULT 0,
  default_sort_by ENUM('best_match','most_active','most_stars','beginner_friendly','recently_updated') NOT NULL DEFAULT 'best_match',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_preferences_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_consents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  type ENUM('essential','analytics','sentry_replay','marketing','github_oauth_notice') NOT NULL,
  status ENUM('granted','revoked') NOT NULL DEFAULT 'granted',
  policy_version VARCHAR(50) NOT NULL,
  source ENUM('cookie_banner','settings','login_notice','onboarding') NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_consents_user_type (user_id, type),
  KEY idx_user_consents_user_status (user_id, status),
  KEY idx_user_consents_type_status (type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_repository_states (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  github_repository_id BIGINT UNSIGNED NOT NULL,
  owner_login VARCHAR(100) NOT NULL,
  repository_name VARCHAR(200) NOT NULL,
  state ENUM('saved','ignored','researching','working','pull_request_sent','contributed','archived') NOT NULL,
  notes TEXT NULL,
  saved_at DATETIME NULL,
  ignored_at DATETIME NULL,
  contributed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_repository_states_user_repo (user_id, github_repository_id),
  KEY idx_user_repository_states_user_state (user_id, state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_activity_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  github_repository_id BIGINT UNSIGNED NULL,
  event_type ENUM(
    'viewed_project',
    'saved_project',
    'ignored_project',
    'opened_github',
    'started_contributing',
    'sent_pull_request',
    'marked_contributed',
    'restored_project'
  ) NOT NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_user_activity_events_user_id (user_id),
  KEY idx_user_activity_events_repository (github_repository_id),
  KEY idx_user_activity_events_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS repository_cache (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  github_repository_id BIGINT UNSIGNED NOT NULL,
  owner_login VARCHAR(100) NOT NULL,
  repository_name VARCHAR(200) NOT NULL,
  repository_data JSON NOT NULL,
  health_data JSON NULL,
  fetched_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_repository_cache_github_id (github_repository_id),
  KEY idx_repository_cache_owner_repo (owner_login, repository_name),
  KEY idx_repository_cache_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS repository_issue_cache (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  github_repository_id BIGINT UNSIGNED NOT NULL,
  github_issue_id BIGINT UNSIGNED NOT NULL,
  issue_number INT NOT NULL,
  issue_data JSON NOT NULL,
  difficulty_estimation JSON NULL,
  fetched_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_repository_issue_cache_repo_issue (github_repository_id, github_issue_id),
  KEY idx_repository_issue_cache_repo_number (github_repository_id, issue_number),
  KEY idx_repository_issue_cache_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_repository_matches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  github_repository_id BIGINT UNSIGNED NOT NULL,
  match_score DECIMAL(5,2) NOT NULL,
  score_breakdown JSON NOT NULL,
  reasons JSON NOT NULL,
  generated_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_repository_matches_user_repo (user_id, github_repository_id),
  KEY idx_user_repository_matches_user_score (user_id, match_score),
  KEY idx_user_repository_matches_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO technologies (slug, name, category, github_language, github_topics, is_active, display_order, created_at, updated_at) VALUES
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
('jest', 'Jest', 'tool', 'JavaScript', JSON_ARRAY('jest'), 1, 300, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  category = VALUES(category),
  github_language = VALUES(github_language),
  github_topics = VALUES(github_topics),
  is_active = VALUES(is_active),
  display_order = VALUES(display_order),
  updated_at = NOW();

COMMIT;
