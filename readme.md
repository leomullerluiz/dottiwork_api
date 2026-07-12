# dotti.work API

Native PHP backend for **dotti.work**, an open source project discovery platform that helps developers find repositories and issues that fit their technical profile, experience level, and contribution goals.

## Stack

- Native PHP with a small MVC-style architecture
- MySQL/MariaDB through PDO
- GitHub OAuth App authentication
- Local API sessions with HttpOnly cookies and opaque Bearer tokens
- GitHub API integration for repositories, issues, topics, labels, and profile sync
- OpenAPI contract in `openapi.yaml`
- Swagger UI in `docs/`
- PHPUnit test suite
- GitHub Actions + FTP deployment workflow

## Requirements

- PHP 7.4 or newer
- Composer
- MySQL 5.7+ or MariaDB with JSON support
- Apache with `mod_rewrite` enabled, or an equivalent local PHP setup
- A GitHub OAuth App for login tests

## Local Setup

1. Install PHP dependencies:

```bash
composer install
```

2. Copy the environment template:

```bash
cp .env.example .env
```

3. Generate `APP_SECRET` and `APP_ENCRYPTION_KEY` values:

```bash
php -r "echo rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=') . PHP_EOL;"
```

Run the command twice and paste one value into each variable.

4. Create a local database and import the clean schema:

```bash
mysql -u root -p dottiwork_db < db_dump.sql
```

For incremental upgrades from an older local database, apply the files in `migrations/` in chronological order instead.

5. Create a GitHub OAuth App.

For XAMPP-style local usage, the callback URL should match:

```text
http://localhost/dottiwork_api/api/auth/github/callback
```

Set these values in `.env`:

```env
OAUTH_GITHUB_CLIENT_ID=
OAUTH_GITHUB_CLIENT_SECRET=
OAUTH_GITHUB_REDIRECT_URI=http://localhost/dottiwork_api/api/auth/github/callback
```

6. Open the health endpoint:

```text
GET http://localhost/dottiwork_api/api/health
```

7. Start GitHub login:

```text
GET http://localhost/dottiwork_api/api/auth/github/start?return_to=/onboarding
```

## Environment Variables

Required for local development:

- `APP_SECRET`
- `APP_ENCRYPTION_KEY`
- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `OAUTH_GITHUB_CLIENT_ID`
- `OAUTH_GITHUB_CLIENT_SECRET`
- `OAUTH_GITHUB_REDIRECT_URI`

Recommended for a realistic local setup:

- `FRONTEND_URL`
- `API_BASE_URL`
- `CORS_ALLOWED_ORIGINS`
- `SESSION_COOKIE_NAME`
- `SESSION_COOKIE_DOMAIN`
- `SESSION_COOKIE_SECURE`
- `SESSION_COOKIE_SAMESITE`
- `SESSION_TOKEN_TTL_SECONDS`
- `RATE_LIMIT_ENABLED`

Optional:

- `MAIL_*` variables for transactional email. If SMTP is not configured, email sends fail gracefully in the current services.
- `DOCS_USER` and `DOCS_PASSWORD` for Swagger UI Basic Auth.
- `SENTRY_DSN` and `SENTRY_TEST_ENABLED` for Sentry health checks.
- `PUBLIC_PROFILE_BASE_URL` and `PUBLIC_API_BASE_URL` for public profile share links.
- `MATCH_*`, `REPOSITORY_CACHE_TTL_SECONDS`, and `ISSUES_CACHE_TTL_SECONDS` for GitHub matching/cache tuning.

Never commit a real `.env` file. The repository should only contain `.env.example`.

## Authentication

The MVP supports GitHub OAuth only.

Flow:

```text
GET /auth/github/start
GitHub redirects to /auth/github/callback
The backend creates or updates the local user
The backend stores an HttpOnly session cookie
The frontend calls fetch(..., { credentials: "include" })
```

Protected routes accept either:

```http
Authorization: Bearer TOKEN
```

or the session cookie:

```http
dotti_session=...
```

GitHub access tokens are encrypted at rest and are never returned to the frontend.

## Main Routes

The API registers routes both with and without the `/api/v1` prefix.

- `GET /health`
- `GET /health/database`
- `GET /auth/github/start`
- `GET /auth/github/callback`
- `GET /auth/me`
- `POST /auth/logout`
- `POST /auth/logout-all`
- `GET /integrations/github/status`
- `POST /integrations/github/sync`
- `DELETE /integrations/github`
- `GET /me/profile`
- `PUT /me/profile`
- `GET /me/public-profile`
- `PUT /me/public-profile/settings`
- `GET /catalog/technologies`
- `GET /me/technologies`
- `PUT /me/technologies`
- `GET /me/preferences`
- `PUT /me/preferences`
- `GET /badges`
- `GET /me/badges`
- `POST /me/badges/evaluate`
- `GET /matches`
- `POST /matches/refresh`
- `GET /repositories/top`
- `GET /repositories/:owner/:repo`
- `GET /repositories/:owner/:repo/issues`
- `GET /me/repositories`
- `PUT /me/repositories/:githubRepositoryId/state`
- `GET /me/history`
- `POST /me/import-local-data`
- `GET /me/export`
- `DELETE /me/account`

See `openapi.yaml` for the full HTTP contract.

## Tests

```bash
vendor/bin/phpunit
```

The current tests cover auth security, response contracts, validation, matching, repository summaries, badge progress, public profiles, invites, consents, email template rendering, and GitHub client behavior.

## API Documentation

The source contract is `openapi.yaml`.

Swagger UI is served from `docs/` and protected with:

```env
DOCS_USER=
DOCS_PASSWORD=
```

## Publishing Notes

Before making the repository public:

- Ensure `.env` is not tracked.
- Rotate any secret that was ever committed or shared.
- Use `db_dump.sql` for a clean install without user/session/OAuth data.
- Revoke old GitHub OAuth authorizations separately if needed. Clearing `oauth_accounts` removes local tokens, but it does not revoke authorizations at GitHub.
