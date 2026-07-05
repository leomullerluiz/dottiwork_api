-- Rate limit buckets keyed by SHA-256 hashes. Raw IPs, tokens and headers are not stored.

CREATE TABLE IF NOT EXISTS rate_limit_buckets (
  key_hash CHAR(64) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  reset_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (key_hash),
  KEY idx_rate_limit_reset_at (reset_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
