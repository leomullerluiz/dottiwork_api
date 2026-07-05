-- LGPD consent tracking for authenticated users.

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
