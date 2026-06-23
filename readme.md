# dotti.work API

Backend PHP nativo da plataforma dotti.work, agora focado em recomendacao de projetos open source para desenvolvedores montarem portfolio e encontrarem contribuicoes compativeis com seu perfil tecnico.

## Stack

- PHP nativo com arquitetura MVC simples
- MySQL/MariaDB via PDO
- OAuth GitHub App
- Sessao local por cookie HttpOnly e token Bearer opaco
- Swagger UI em `docs/`
- Deploy via GitHub Actions + FTP

## Setup

1. Instale dependencias:

```bash
composer install
```

2. Copie `.env.example` para `.env` e configure:

```env
APP_SECRET=
APP_ENCRYPTION_KEY=
FRONTEND_URL=http://localhost:3000
CORS_ALLOWED_ORIGINS=http://localhost:3000
DB_HOST=
DB_NAME=
DB_USER=
DB_PASSWORD=
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URI=http://localhost/dottiwork_api/api/auth/github/callback
```

3. Aplique a migration:

```bash
mysql -u usuario -p banco < migrations/202606230001_open_source_portal.sql
```

4. Acesse:

```text
GET /health
GET /auth/github/start?return_to=/onboarding
```

## Autenticacao

O login e exclusivamente via GitHub OAuth.

Fluxo:

```text
GET /auth/github/start
GitHub callback em /auth/github/callback
Backend cria sessao local
Backend grava cookie HttpOnly
Front usa fetch(..., { credentials: "include" })
```

Rotas protegidas aceitam:

```http
Authorization: Bearer TOKEN
```

ou cookie:

```http
dotti_session=...
```

O token do GitHub nunca e retornado ao front-end.

## Rotas Principais

- `GET /health`
- `GET /auth/github/start`
- `GET /auth/github/callback`
- `GET /auth/me`
- `POST /auth/logout`
- `POST /auth/logout-all`
- `GET /me/profile`
- `PUT /me/profile`
- `GET /catalog/technologies`
- `GET /me/technologies`
- `PUT /me/technologies`
- `GET /me/preferences`
- `PUT /me/preferences`
- `GET /matches`
- `POST /matches/refresh`
- `GET /repositories/:owner/:repo`
- `GET /repositories/:owner/:repo/issues`
- `GET /me/repositories`
- `PUT /me/repositories/:githubRepositoryId/state`
- `GET /me/history`
- `POST /me/import-local-data`
- `GET /me/export`

As mesmas rotas tambem sao registradas com prefixo `/api/v1`.

## Testes

```bash
vendor/bin/phpunit
```

## Documentacao

O contrato HTTP fica em `openapi.yaml`. A UI Swagger continua em `docs/` e deve ser protegida por `DOCS_USER` e `DOCS_PASSWORD`.
