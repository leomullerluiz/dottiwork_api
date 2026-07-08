-- Public user profile settings.

START TRANSACTION;

ALTER TABLE user_profiles
  ADD COLUMN public_profile_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER onboarding_completed_at,
  ADD COLUMN public_profile_slug VARCHAR(120) DEFAULT NULL AFTER public_profile_enabled,
  ADD COLUMN public_profile_updated_at DATETIME DEFAULT NULL AFTER public_profile_slug;

ALTER TABLE user_profiles
  ADD UNIQUE KEY uq_user_profiles_public_slug (public_profile_slug);

COMMIT;
