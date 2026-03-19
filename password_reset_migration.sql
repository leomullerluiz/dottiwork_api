-- Migration: Tabela de tokens para reset de senha
-- Execute este script no banco dottiwork_db

CREATE TABLE `password_reset_tokens` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11) NOT NULL,
  `email`      VARCHAR(255) NOT NULL,
  `token_hash` VARCHAR(255) NOT NULL COMMENT 'Hash bcrypt do código de 5 dígitos',
  `expires_at` DATETIME NOT NULL COMMENT 'Expira 1 hora após a criação',
  `used_at`    DATETIME NULL DEFAULT NULL COMMENT 'Preenchido quando o token é utilizado',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_expires_at` (`expires_at`),
  CONSTRAINT `fk_prt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
