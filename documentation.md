# Technical Documentation - dotti.work API v2

This document summarizes the current backend architecture. The detailed HTTP contract, schemas, and examples live in `openapi.yaml`.

## Architecture

```text
Client
  -> Apache/.htaccess
  -> api/index.php
  -> Router
  -> Controller
  -> Service
  -> Model
  -> PDO/MySQL
  -> JSON response
```

## Layers

- `api/core`: HTTP primitives, routing, response formatting, auth/session helpers, GitHub OAuth, GitHub client, cryptography, validation, rate limiting, mail, and CORS.
- `api/controller`: route entry points. Controllers authenticate, validate input, call services/models, and return JSON responses.
- `api/model`: PDO/MySQL data access and response shaping.
- `api/service`: business rules, matching, repository health, issue difficulty, public profiles, invites, badges, import/export, and email orchestration.
- `migrations`: incremental schema changes for API v2.
- `docs`: Swagger UI assets.
- `tests`: PHPUnit coverage for isolated services, DTOs, contracts, and security behavior.

## Authentication

GitHub OAuth App is the only login method in the MVP.

Rules:

- No manual signup.
- No password login.
- No password reset.
- GitHub access tokens are encrypted before being stored.
- GitHub access tokens are never returned to the frontend.
- API sessions use first-party opaque tokens.
- Only `token_hash` is stored for local session tokens.
- Browsers should use an HttpOnly cookie with `fetch(..., { credentials: "include" })`.
- External integrations and tests may use `Authorization: Bearer TOKEN`.

## Response Contract

Success:

```json
{
  "success": true,
  "data": {}
}
```

Error:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Invalid data.",
    "details": []
  }
}
```

## Domain

The legacy task/notepad/password domain is no longer exposed by public routes. The current domain includes:

- Users authenticated through GitHub.
- Professional profile and onboarding state.
- Portfolio and contribution goals.
- Technology catalog.
- User technology stack.
- Matching preferences.
- GitHub repository and issue cache.
- Persistent, explainable repository matches.
- Per-user repository states.
- User activity history.
- Public profiles.
- Invite links and referrals.
- Consent tracking.
- Badge catalog, progress, and earned badges.
- Import from the localStorage MVP format.
- User data export and account deletion.

## Database Setup

For a fresh local install without users, sessions, OAuth accounts, or cached GitHub data:

```bash
mysql -u root -p dottiwork_db < db_dump.sql
```

For incremental upgrades from an older database, apply migrations in chronological order. Important baseline files include:

```bash
mysql -u user -p database < migrations/202606230001_open_source_portal.sql
mysql -u user -p database < migrations/202607040001_user_consents.sql
mysql -u user -p database < migrations/202607040002_rate_limit_buckets.sql
mysql -u user -p database < migrations/202607070001_invite_links.sql
mysql -u user -p database < migrations/202607080001_badges.sql
mysql -u user -p database < migrations/202607080002_public_user_profiles.sql
mysql -u user -p database < migrations/202607090001_badge_notification_seen.sql
mysql -u user -p database < migrations/202607100001_badge_image_assets.sql
```

## Security

- CORS uses `CORS_ALLOWED_ORIGINS`; in production, allow only frontend domains.
- Cookies use `HttpOnly`, `Secure` through `SESSION_COOKIE_SECURE`, `SameSite` through `SESSION_COOKIE_SAMESITE`, and an optional `SESSION_COOKIE_DOMAIN`.
- Cookie-authenticated mutations require an allowed `Origin` to reduce CSRF risk.
- Rate limiting uses `rate_limit_buckets` with SHA-256 keys; raw IPs, tokens, cookies, and sensitive headers are not stored.
- SQL statements use prepared statements.
- OAuth `return_to` accepts internal paths only.
- `.htaccess` blocks direct access to `config`, `core`, `controller`, `model`, `service`, `tests`, `vendor`, `migrations`, and `templates`.
- Secrets must come from environment variables. Do not commit `.env`.

## Tests

```bash
vendor/bin/phpunit
```

Current tests cover local token handling, Bearer parsing, OAuth `return_to` safety, validation, deterministic matching, issue difficulty estimation, public profile shaping, badge progress, invites, consents, and response contracts.
