-- Invite links and effective referral attribution.

ALTER TABLE oauth_authorization_states
  ADD COLUMN invite_code VARCHAR(64) NULL AFTER user_agent,
  ADD COLUMN invite_link_id BIGINT UNSIGNED NULL AFTER invite_code,
  ADD INDEX idx_oauth_state_invite_link_id (invite_link_id);

CREATE TABLE IF NOT EXISTS user_invite_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(64) NOT NULL,
  label VARCHAR(120) NULL,
  status ENUM('active','revoked','expired') NOT NULL DEFAULT 'active',
  max_uses INT UNSIGNED NULL,
  uses_count INT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NULL,
  last_used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_invite_links_code (code),
  KEY idx_user_invite_links_user_status (user_id, status),
  KEY idx_user_invite_links_expires_at (expires_at),
  CONSTRAINT fk_user_invite_links_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_referrals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  referrer_user_id BIGINT UNSIGNED NOT NULL,
  referred_user_id BIGINT UNSIGNED NOT NULL,
  invite_link_id BIGINT UNSIGNED NOT NULL,
  invite_code VARCHAR(64) NOT NULL,
  source VARCHAR(50) NOT NULL DEFAULT 'github_oauth',
  registered_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_referrals_referred_user (referred_user_id),
  KEY idx_user_referrals_referrer_user (referrer_user_id),
  KEY idx_user_referrals_invite_link (invite_link_id),
  KEY idx_user_referrals_registered_at (registered_at),
  CONSTRAINT fk_user_referrals_referrer FOREIGN KEY (referrer_user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_user_referrals_referred FOREIGN KEY (referred_user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_user_referrals_invite_link FOREIGN KEY (invite_link_id) REFERENCES user_invite_links (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
