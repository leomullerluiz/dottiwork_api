# dotti.work Backend Rules

This document is the working rulebook for changing the dotti.work API. It describes the current product domain, architectural boundaries, security constraints, and contribution expectations.

For route-level details, use `openapi.yaml`. For local setup, use `readme.md`.

## Product Domain

dotti.work helps developers discover open source repositories and issues that match their:

- Languages, frameworks, libraries, tools, platforms, databases, and DevOps/cloud stack.
- Experience level.
- Portfolio and contribution goals.
- Preferred contribution types.
- Desired issue difficulty.
- Project size preferences.
- Documentation language preferences.
- Interest in `good first issue` and `help wanted` opportunities.

The legacy productivity/task/notepad/password domain is deprecated and must not be exposed by production routes.

## Architecture

Keep the native PHP MVC-style structure:

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

Use the existing directories:

```text
api/
  config/
  core/
  controller/
  model/
  service/
  templates/
docs/
migrations/
tests/
openapi.yaml
```

Rules:

- Controllers validate input, resolve the authenticated user, call services/models, and return JSON.
- Models contain database access and data shaping.
- Services contain business logic, GitHub integration, matching, cache decisions, badge evaluation, import/export, and email orchestration.
- Do not place complex business rules directly in controllers.
- Prefer existing helpers in `api/core` before creating new abstractions.
- Keep route registration compatible with both unversioned paths and `/api/v1`.

## Authentication

GitHub OAuth App is the only login method in the MVP.

Do not add:

- Password login.
- Manual email signup.
- Password reset.
- Personal access token login.
- Private repository access through the `repo` scope.

Session rules:

- The app uses first-party opaque API session tokens.
- Store only `token_hash` in the database.
- Use HttpOnly cookies for the browser flow.
- Allow `Authorization: Bearer TOKEN` for tests and future integrations.
- Never use the GitHub access token as the application session token.
- Never return GitHub tokens to the frontend.

Recommended cookie posture:

```text
HttpOnly
Secure in production
SameSite=Lax
```

The frontend should call protected endpoints with:

```ts
fetch(url, {
  credentials: "include"
});
```

## GitHub OAuth Flow

The expected flow is:

```text
Frontend
  -> GET /auth/github/start
  -> Backend creates a temporary state
  -> Backend redirects to GitHub
  -> User authorizes
  -> GitHub calls /auth/github/callback
  -> Backend validates state
  -> Backend exchanges code for access token
  -> Backend fetches GitHub profile and verified email when available
  -> Backend creates or updates the local user
  -> Backend creates or updates the linked OAuth account
  -> Backend creates a local session
  -> Backend writes the HttpOnly cookie
  -> Backend redirects to the frontend callback route
```

OAuth state rules:

- Generate state with `random_bytes`.
- Store only a hash of the state.
- Expire state quickly.
- Mark used state values as used.
- Store `return_to`, user agent, invite metadata, and IP hash when available.
- Accept only internal `return_to` paths such as `/onboarding`, `/matches`, and `/profile`.
- Reject external URLs, protocol-relative URLs, and JavaScript URLs.

OAuth callback rules:

- Do not put tokens in query strings.
- Do not expose internal error details in redirects.
- Redirect success to the frontend auth callback.
- Redirect failure with a stable, non-sensitive reason code.

## Environment Variables

Required for normal local development:

```env
APP_SECRET=
APP_ENCRYPTION_KEY=
DB_HOST=
DB_NAME=
DB_USER=
DB_PASSWORD=
OAUTH_GITHUB_CLIENT_ID=
OAUTH_GITHUB_CLIENT_SECRET=
OAUTH_GITHUB_REDIRECT_URI=
```

Security rules:

- Never commit `.env`.
- Never commit production secrets.
- Rotate any secret that was previously committed.
- Never expose `OAUTH_GITHUB_CLIENT_SECRET` to the frontend.
- Never log OAuth access tokens, session tokens, cookies, or raw authorization headers.
- Never use a developer personal token as a production token.

Operational variables are documented in `.env.example`.

## Database Rules

Use migrations for schema changes. Keep `db_dump.sql` as the clean install snapshot.

Core tables:

- `users`
- `auth_tokens`
- `oauth_accounts`
- `oauth_authorization_states`
- `user_profiles`
- `user_profile_goals`
- `technologies`
- `user_technologies`
- `user_preferences`
- `user_consents`
- `user_repository_states`
- `user_activity_events`
- `repository_cache`
- `repository_issue_cache`
- `user_repository_matches`
- `badge_definitions`
- `user_badges`
- `user_badge_progress`
- `user_invite_links`
- `user_referrals`
- `rate_limit_buckets`

Rules:

- Use prepared statements.
- Use transactions for multi-table writes.
- Derive `user_id` from the authenticated session, never from request bodies.
- Use soft delete for user account deletion.
- Keep local session tokens hashed.
- Keep GitHub access tokens encrypted.
- Keep cached GitHub data separate from per-user state.
- Do not duplicate tables for saved, ignored, working, and contributed repositories; use `user_repository_states`.

## Matching

The initial matching algorithm must remain deterministic, explainable, and testable.

Inputs may include:

- User technologies and GitHub topics.
- User seniority.
- User preferences.
- Repository language, topics, activity, stars, size, license, labels, and health.
- Open issues and estimated issue difficulty.
- Contribution-friendly signals such as `good first issue`, `help wanted`, README, CONTRIBUTING, tests, CI, and code of conduct.

Score areas:

- Stack compatibility.
- Difficulty fit.
- Issue availability.
- Recent activity.
- Repository health.
- Contribution readiness.

Rules:

- Do not depend on generative AI for core scoring.
- Store score breakdowns and reasons.
- Cache GitHub repository and issue data.
- Avoid GitHub calls during list rendering whenever cached data is valid.
- Rate limit match refresh per user.
- Return a clear fallback when GitHub is unavailable.

## Repository and Issue Handling

Repository detail endpoints should expose normalized data, not raw GitHub responses.

Include:

- Owner and repository name.
- Description and URL.
- Stars, forks, open issues, language, topics, license, and last update.
- Repository health signals.
- User state when authenticated.
- Match data when available.

Issue endpoints should:

- Return open issues only unless a route explicitly supports other states.
- Exclude pull requests from issue lists.
- Estimate difficulty from title, body, labels, and discussion size.
- Include confidence and reasons for difficulty estimates.
- Support filtering by difficulty and label.

## User Repository State

Valid states:

```text
saved
ignored
researching
working
pull_request_sent
contributed
archived
```

Rules:

- A user can have only one state row per GitHub repository.
- State updates must be scoped to the authenticated user.
- State changes should create activity events when meaningful.
- `ignored` repositories should be hidden from default match lists.
- `restore` should move an ignored repository back to `saved`.

## Activity History

Valid event types:

```text
viewed_project
saved_project
ignored_project
opened_github
started_contributing
sent_pull_request
marked_contributed
restored_project
```

Rules:

- Always filter history by authenticated user.
- Never allow one user to clear another user's history.
- Store minimal metadata.
- Never store tokens or sensitive headers in metadata.

## Badges

Badges are defined in `badge_definitions` and awarded in `user_badges`.

Rules:

- The frontend does not know badge rules.
- `GET /badges` returns the active public catalog.
- `GET /me/badges` returns earned badges, progress, and recent awards.
- `POST /me/badges/evaluate` recalculates badges for the authenticated user.
- New badge rules should use existing `criteria_type` values when possible.
- New `criteria_type` values belong in `BadgeProgressService` and require PHPUnit coverage.
- Update `db_dump.sql` when a new badge should exist in clean installs.

See `docs/badges-addition-guide.md` for the detailed workflow.

## Public Profiles

Public profiles must be opt-in.

Rules:

- Resolve by GitHub login or public slug.
- Never expose email, tokens, private notes, raw history, raw progress snapshots, or sensitive metadata.
- Expose only public-safe metrics, technologies, badges, featured repositories, and share URLs.
- Validate slugs and prevent collisions.

## Invites and Referrals

Invite links are user-owned and code-based.

Rules:

- Codes must be unguessable and URL-safe.
- Revoked or expired links must not register referrals.
- A referred user can only be counted once.
- Referral registration must not break OAuth signup if it fails.

## Consents and Privacy

Consent tracking is part of the authenticated user domain.

Rules:

- Track type, status, policy version, source, timestamps, and revocation.
- Optional consents can be revoked.
- Essential consent should not be treated like marketing consent.
- Data export must include only the authenticated user's persisted data.
- Account deletion must revoke sessions and disconnect GitHub locally.

## Security

Required posture:

- CORS allowlist through `CORS_ALLOWED_ORIGINS`.
- Allowed `Origin` check for cookie-authenticated mutations.
- Rate limiting for auth, GitHub sync, match refresh, and other expensive flows.
- SHA-256 or HMAC hashing for rate limit identities and IP hashes.
- AES-256-GCM encryption for OAuth access tokens.
- No raw tokens in logs, Sentry, errors, redirects, emails, or JSON responses.
- No dynamic SQL without a whitelist.
- No direct Apache access to `config`, `core`, `controller`, `model`, `service`, `tests`, `vendor`, `migrations`, or `templates`.

Standard error responses must follow:

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

## OpenAPI and Documentation

Every user-facing route change should update:

- `openapi.yaml`
- `readme.md` when setup or operational behavior changes
- `documentation.md` when architecture or domain behavior changes
- Relevant docs under `docs/`
- `db_dump.sql` when clean-install seed data changes

Documentation should be written in English and kept ASCII unless a file already needs non-ASCII content.

## Tests

Use focused PHPUnit coverage for any changed behavior.

Expected coverage areas:

- OAuth state generation, validation, expiry, and replay protection.
- Bearer token parsing and HttpOnly cookie session behavior.
- Origin checks for cookie-authenticated mutations.
- Profile, stack, preferences, repository state, and public profile validation.
- Matching score calculations and filters.
- Repository health and issue difficulty.
- Badges and badge progress.
- Invites, referrals, and consents.
- Account deletion and data export.
- Response contract consistency.

Run:

```bash
vendor/bin/phpunit
```

## Publishing Checklist

Before publishing:

- Confirm `.env` is not tracked.
- Rotate any secret that ever appeared in repository history or shared notes.
- Import `db_dump.sql` for a clean database without user/session/OAuth data.
- Confirm GitHub OAuth App callback URLs match the deployed API.
- Set production CORS domains only.
- Set `SESSION_COOKIE_SECURE=true`.
- Protect Swagger UI with `DOCS_USER` and `DOCS_PASSWORD`.
- Confirm `openapi.yaml` and docs are in English.
