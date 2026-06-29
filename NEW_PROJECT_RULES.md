# Briefing de adaptação do backend — dotti.work

Você deve alterar um backend já existente em PHP nativo para atender ao novo produto **dotti.work**.

O projeto atual já possui uma arquitetura MVC simples, sem Laravel ou Symfony, composta por:

* `api/index.php` como entrypoint;
* `api/core/` com Router, Request, Response, Auth, Database e Mailer;
* `api/controller/` com controllers;
* `api/model/` com models estáticos usando PDO/MySQL;
* Apache com `.htaccess`;
* Swagger/OpenAPI;
* variáveis de ambiente via `.env`;
* deploy por GitHub Actions via FTP.

O domínio antigo era produtividade, tarefas e categorias. Esse domínio deve ser removido ou descontinuado. O novo domínio é uma plataforma de descoberta de projetos open source baseada no perfil técnico do usuário.

A aplicação front-end já existe em Next.js e possui um MVP funcional com dados em `localStorage`. Agora o backend deve persistir usuários, autenticação, perfil, stacks, preferências, projetos salvos, projetos ignorados, histórico e resultados de matching.

---

## 1. Objetivo do backend

O backend deve atender a plataforma **dotti.work**, que ajuda desenvolvedores a encontrar repositórios e issues open source compatíveis com:

* Linguagens;
* Frameworks;
* Bibliotecas;
* Ferramentas;
* Plataformas;
* Banco de dados;
* Cloud e DevOps;
* Senioridade;
* Objetivos profissionais;
* Tipo de contribuição desejada;
* Dificuldade de contribuição;
* Tamanho do projeto;
* Atividade recente;
* Projetos com `good first issue`;
* Projetos com `help wanted`;
* Preferências salvas pelo usuário.

O backend deve:

1. Autenticar exclusivamente via GitHub OAuth App.
2. Criar ou atualizar um usuário local após o login GitHub.
3. Persistir o perfil profissional e preferências do usuário.
4. Persistir tecnologias e níveis de domínio.
5. Buscar e normalizar dados da API do GitHub.
6. Gerar matches personalizados.
7. Armazenar projetos salvos, ignorados e em andamento.
8. Registrar histórico de interação.
9. Reduzir chamadas desnecessárias para GitHub por meio de cache.
10. Nunca expor tokens do GitHub ao front-end.
11. Usar respostas HTTP padronizadas e seguras.
12. Continuar compatível com hospedagem PHP tradicional e MySQL/MariaDB.

---

# 2. Decisões obrigatórias de arquitetura

## 2.1 Manter e reaproveitar

Preservar e evoluir:

```text
api/core/Router.php
api/core/Request.php
api/core/Response.php
api/core/Database.php
api/core/Auth.php
api/controller/BaseController.php
api/config/database.php
docs/
openapi.yaml
tests/
```

O padrão deve continuar sendo:

```text
Cliente
  -> API PHP
  -> Router
  -> Controller
  -> Model
  -> PDO/MySQL
  -> JSON Response
```

## 2.2 Substituir ou remover

Remover, substituir ou deixar fora das rotas públicas:

```text
TasksController.php
TaskCategoryController.php
Task.php
TaskCategory.php
PasswordResetToken.php
rotas /task/*
rotas /task/category/*
rotas públicas /test/*
rotas de signup por senha
rotas de login por email e senha
rotas de password reset
```

Não manter endpoints legados de tarefa em produção.

## 2.3 Padronizar autenticação

A API antiga utiliza o header customizado:

```http
key: TOKEN
```

Isso deve ser substituído.

A nova API deve aceitar:

```http
Authorization: Bearer TOKEN
```

Além disso, deve suportar sessão por cookie HttpOnly para uso seguro com o front-end Next.js.

A implementação deve priorizar:

```text
Cookie HttpOnly + Secure + SameSite=Lax
```

O token local da API não deve ser salvo em `localStorage`.

O front-end deve utilizar:

```ts
fetch(url, {
  credentials: "include"
})
```

O header `Authorization: Bearer` pode continuar disponível para integrações externas, testes e aplicativos futuros.

---

# 3. Estrutura de arquivos esperada

Criar ou adaptar a estrutura para algo semelhante a:

```text
api/
  index.php

  config/
    database.php
    github.php
    mail.php

  core/
    Auth.php
    Crypto.php
    Database.php
    GitHubClient.php
    GitHubOAuth.php
    Mailer.php
    Request.php
    Response.php
    Router.php
    Validator.php

  controller/
    BaseController.php
    AuthController.php
    ProfileController.php
    TechnologyController.php
    PreferencesController.php
    MatchController.php
    RepositoryController.php
    UserRepositoryStateController.php
    ActivityController.php
    CatalogController.php
    AccountController.php
    HealthController.php

  model/
    User.php
    OAuthAccount.php
    AuthToken.php
    OAuthAuthorizationState.php
    UserProfile.php
    Technology.php
    UserTechnology.php
    UserPreference.php
    UserRepositoryState.php
    UserActivityEvent.php
    RepositoryCache.php
    RepositoryIssueCache.php
    UserRepositoryMatch.php

  service/
    MatchService.php
    RepositoryHealthService.php
    IssueDifficultyService.php
    UserProfileService.php

  templates/
    ...

docs/
tests/
migrations/
openapi.yaml
```

Não concentrar regras complexas dentro dos controllers.

Controllers devem validar entrada, obter o usuário autenticado, chamar services/models e devolver respostas JSON.

Models devem conter apenas acesso ao banco.

Services devem conter regras de negócio, integração GitHub, cálculo de score, cache e normalização.

---

# 4. OAuth GitHub obrigatório

## 4.1 Tipo de autenticação

Implementar login usando um **GitHub OAuth App**.

Não criar login por senha.

Não criar cadastro manual por e-mail.

Não criar reset de senha.

O GitHub será a única forma de autenticação nesta primeira versão.

## 4.2 Variáveis de ambiente obrigatórias

Adicionar ao `.env`:

```env
APP_ENV=production
APP_SECRET=
APP_ENCRYPTION_KEY=

FRONTEND_URL=https://dotti.work
API_BASE_URL=https://api.dotti.work

CORS_ALLOWED_ORIGINS=https://dotti.work,http://localhost:3000

SESSION_COOKIE_NAME=dotti_session
SESSION_COOKIE_DOMAIN=.dotti.work
SESSION_COOKIE_SECURE=true
SESSION_TOKEN_TTL_SECONDS=2592000

OAUTH_GITHUB_CLIENT_ID=
OAUTH_GITHUB_CLIENT_SECRET=
OAUTH_GITHUB_REDIRECT_URI=https://api.dotti.work/auth/github/callback
OAUTH_GITHUB_SCOPES=read:user,user:email
OAUTH_GITHUB_API_VERSION=

SENTRY_DSN=
```

Regras:

* Nunca expor `OAUTH_GITHUB_CLIENT_SECRET` ao Next.js.
* Nunca salvar `APP_SECRET` no repositório.
* Nunca colocar token OAuth em logs.
* Nunca devolver token do GitHub em resposta JSON.
* Nunca usar token pessoal do desenvolvedor como token de produção.
* Não solicitar o escopo `repo` neste MVP.
* Projetos privados não devem ser analisados inicialmente.
* O aplicativo deve trabalhar apenas com dados públicos de repositórios.

## 4.3 Fluxo OAuth

Implementar o fluxo:

```text
Front-end
  -> GET /auth/github/start
  -> Backend cria state temporário
  -> Redirect para GitHub
  -> Usuário autoriza
  -> GitHub chama /auth/github/callback
  -> Backend valida state
  -> Backend troca code por access token
  -> Backend consulta perfil GitHub
  -> Backend cria/atualiza usuário local
  -> Backend cria sessão local
  -> Backend grava cookie HttpOnly
  -> Redirect para o front-end
```

## 4.4 Rota inicial de OAuth

```http
GET /auth/github/start?return_to=/onboarding
```

Regras:

* `return_to` deve aceitar apenas caminhos internos, como `/onboarding`, `/matches` ou `/profile`.
* Nunca aceitar URL externa.
* Rejeitar valores que comecem com `http`, `//`, `javascript:` ou qualquer protocolo.
* Gerar um `state` forte com `random_bytes`.
* Armazenar somente o hash do state no banco.
* O state deve expirar em até 10 minutos.
* Salvar `return_to`, user agent e IP hash, quando disponíveis.
* Redirecionar para a URL oficial de autorização do GitHub.

## 4.5 Callback OAuth

```http
GET /auth/github/callback?code=...&state=...
```

Fluxo obrigatório:

1. Validar presença de `code` e `state`.
2. Buscar state no banco.
3. Verificar expiração.
4. Verificar se já foi usado.
5. Marcar state como usado.
6. Trocar `code` pelo access token no servidor.
7. Consultar dados do usuário no GitHub.
8. Buscar e-mail apenas se necessário.
9. Criar ou atualizar usuário local.
10. Criar ou atualizar conta OAuth vinculada.
11. Criar sessão local.
12. Gravar cookie HttpOnly.
13. Redirecionar ao front-end.

O redirect final deve ser semelhante a:

```text
https://dotti.work/auth/callback?status=success
```

Nunca enviar tokens na query string.

Em caso de erro:

```text
https://dotti.work/auth/callback?status=error&reason=github_authorization_failed
```

Não enviar detalhes internos, stack trace ou credenciais no redirect.

---

# 5. Sessão local da API

Mesmo usando GitHub OAuth, a aplicação deve manter uma sessão própria.

Não usar o access token do GitHub como token de sessão da aplicação.

## 5.1 Token local

Criar token opaco próprio:

```text
random_bytes(32)
base64url encode
```

Regras:

* Retornar o token apenas uma vez, se usar Bearer token.
* No banco, guardar somente o hash do token.
* Nunca salvar token puro em MySQL.
* Expiração padrão de 30 dias.
* Permitir revogação.
* Atualizar `last_used_at`.
* Permitir logout.
* Limpar tokens expirados periodicamente.
* Permitir múltiplas sessões por usuário, inicialmente.

## 5.2 AuthToken

A tabela de tokens deve possuir campos semelhantes a:

```text
id
user_id
token_hash
expires_at
revoked_at
last_used_at
ip_hash
user_agent
created_at
```

## 5.3 Refatorar Auth.php

Atualizar `Auth.php` para:

```text
generateSessionToken()
hashSessionToken()
validateSessionToken()
getAuthenticatedUser()
requireAuth()
revokeCurrentToken()
revokeAllUserTokens()
cleanupExpiredTokens()
```

Remover segredos hardcoded.

Toda chave criptográfica deve vir do `.env`.

---

# 6. Banco de dados

Criar migrations novas e não fazer alterações destrutivas sem necessidade.

A migração deve preservar dados existentes até que a nova aplicação seja validada.

## 6.1 Tabela users

Reaproveitar a tabela `users`, adaptando-a para GitHub OAuth.

Campos esperados:

```text
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
login VARCHAR(100) NULL UNIQUE
email VARCHAR(255) NULL UNIQUE
display_name VARCHAR(150) NULL
avatar_url VARCHAR(500) NULL
bio TEXT NULL
location VARCHAR(255) NULL
company VARCHAR(255) NULL
website_url VARCHAR(500) NULL
github_profile_url VARCHAR(500) NULL
last_login_at DATETIME NULL
created_at DATETIME NOT NULL
updated_at DATETIME NOT NULL
deleted_at DATETIME NULL
```

Regras:

* `senha` deve deixar de ser obrigatória.
* `first_name` e `last_name` devem deixar de ser obrigatórios.
* O usuário pode não possuir e-mail público no GitHub.
* Não usar senha para autenticação.
* Não retornar dados internos do usuário.

## 6.2 Tabela oauth_accounts

Criar tabela para vínculo com provedores OAuth.

```text
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
user_id BIGINT UNSIGNED NOT NULL
provider VARCHAR(50) NOT NULL
provider_account_id VARCHAR(100) NOT NULL
provider_login VARCHAR(100) NULL
access_token_encrypted TEXT NOT NULL
refresh_token_encrypted TEXT NULL
token_type VARCHAR(50) NULL
scope VARCHAR(500) NULL
token_expires_at DATETIME NULL
token_last_verified_at DATETIME NULL
created_at DATETIME NOT NULL
updated_at DATETIME NOT NULL
```

Índices obrigatórios:

```text
UNIQUE(provider, provider_account_id)
INDEX(user_id)
```

Regras:

* Provider inicial: `github`.
* `access_token_encrypted` deve ser criptografado antes de ser salvo.
* Implementar `Crypto.php`.
* Nunca expor token em JSON.
* Nunca logar token.
* Nunca armazenar token puro.

## 6.3 Tabela oauth_authorization_states

```text
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
state_hash CHAR(64) NOT NULL UNIQUE
return_to VARCHAR(255) NOT NULL
ip_hash CHAR(64) NULL
user_agent VARCHAR(500) NULL
expires_at DATETIME NOT NULL
used_at DATETIME NULL
created_at DATETIME NOT NULL
```

## 6.4 Tabela user_profiles

```text
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
user_id BIGINT UNSIGNED NOT NULL UNIQUE
role VARCHAR(100) NULL
seniority ENUM('junior','mid','senior') NULL
onboarding_completed TINYINT(1) NOT NULL DEFAULT 0
onboarding_completed_at DATETIME NULL
created_at DATETIME NOT NULL
updated_at DATETIME NOT NULL
```

## 6.5 Tabela user_profile_goals

```text
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
user_id BIGINT UNSIGNED NOT NULL
goal ENUM(
  'first_contribution',
  'build_portfolio',
  'practical_experience',
  'join_communities',
  'long_term_projects'
) NOT NULL
created_at DATETIME NOT NULL
```

Índice obrigatório:

```text
UNIQUE(user_id, goal)
```

## 6.6 Tabela technologies

Tabela catálogo global.

```text
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
slug VARCHAR(100) NOT NULL UNIQUE
name VARCHAR(120) NOT NULL
category ENUM(
  'language',
  'framework',
  'library',
  'tool',
  'platform',
  'database',
  'devops_cloud'
) NOT NULL
github_language VARCHAR(100) NULL
github_topics JSON NULL
is_active TINYINT(1) NOT NULL DEFAULT 1
display_order INT NOT NULL DEFAULT 0
created_at DATETIME NOT NULL
updated_at DATETIME NOT NULL
```

Cadastrar tecnologias iniciais:

```text
JavaScript
TypeScript
React
Next.js
Node.js
PHP
Laravel
WordPress
Python
Django
Java
Spring
Vue
Angular
TailwindCSS
React Query
Redux
Zod
Docker
GitHub Actions
MySQL
PostgreSQL
MongoDB
Firebase
AWS
Cloudflare
Nginx
Playwright
Vitest
Jest
```

## 6.7 Tabela user_technologies

```text
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
user_id BIGINT UNSIGNED NOT NULL
technology_id BIGINT UNSIGNED NOT NULL
proficiency_level ENUM('learning','basic','daily','advanced') NOT NULL
interest_level ENUM('learn','contribute','mentor') NOT NULL DEFAULT 'contribute'
created_at DATETIME NOT NULL
updated_at DATETIME NOT NULL
```

Índice obrigatório:

```text
UNIQUE(user_id, technology_id)
```

## 6.8 Tabela user_preferences

```text
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
user_id BIGINT UNSIGNED NOT NULL UNIQUE

contribution_types JSON NOT NULL
difficulty_levels JSON NOT NULL
project_sizes JSON NOT NULL
documentation_languages JSON NOT NULL
organization_types JSON NOT NULL

activity_window_days INT NOT NULL DEFAULT 90
minimum_stars INT NOT NULL DEFAULT 0

require_good_first_issue TINYINT(1) NOT NULL DEFAULT 0
require_help_wanted TINYINT(1) NOT NULL DEFAULT 0

default_sort_by ENUM(
  'best_match',
  'most_active',
  'most_stars',
  'beginner_friendly',
  'recently_updated'
) NOT NULL DEFAULT 'best_match'

created_at DATETIME NOT NULL
updated_at DATETIME NOT NULL
```

Valores válidos:

```text
contribution_types:
bug_fix
feature
documentation
tests
performance
refactor
accessibility
translation

difficulty_levels:
beginner
intermediate
advanced

project_sizes:
small
medium
large

documentation_languages:
en
pt
es
any

organization_types:
independent
startup
company
community
foundation
any
```

## 6.9 Tabela user_repository_states

Uma única tabela deve controlar projetos salvos, ignorados e em andamento.

```text
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
user_id BIGINT UNSIGNED NOT NULL
github_repository_id BIGINT UNSIGNED NOT NULL
owner_login VARCHAR(100) NOT NULL
repository_name VARCHAR(200) NOT NULL

state ENUM(
  'saved',
  'ignored',
  'researching',
  'working',
  'pull_request_sent',
  'contributed',
  'archived'
) NOT NULL

notes TEXT NULL
saved_at DATETIME NULL
ignored_at DATETIME NULL
contributed_at DATETIME NULL
created_at DATETIME NOT NULL
updated_at DATETIME NOT NULL
```

Índice obrigatório:

```text
UNIQUE(user_id, github_repository_id)
INDEX(user_id, state)
```

## 6.10 Tabela user_activity_events

```text
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
user_id BIGINT UNSIGNED NOT NULL
github_repository_id BIGINT UNSIGNED NULL
event_type ENUM(
  'viewed_project',
  'saved_project',
  'ignored_project',
  'opened_github',
  'started_contributing',
  'sent_pull_request',
  'marked_contributed',
  'restored_project'
) NOT NULL
metadata JSON NULL
created_at DATETIME NOT NULL
```

## 6.11 Tabela repository_cache

Cache de repositórios GitHub.

```text
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
github_repository_id BIGINT UNSIGNED NOT NULL UNIQUE
owner_login VARCHAR(100) NOT NULL
repository_name VARCHAR(200) NOT NULL
repository_data JSON NOT NULL
health_data JSON NULL
fetched_at DATETIME NOT NULL
expires_at DATETIME NOT NULL
created_at DATETIME NOT NULL
updated_at DATETIME NOT NULL
```

## 6.12 Tabela repository_issue_cache

```text
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
github_repository_id BIGINT UNSIGNED NOT NULL
github_issue_id BIGINT UNSIGNED NOT NULL
issue_number INT NOT NULL
issue_data JSON NOT NULL
difficulty_estimation JSON NULL
fetched_at DATETIME NOT NULL
expires_at DATETIME NOT NULL
created_at DATETIME NOT NULL
updated_at DATETIME NOT NULL
```

Índice obrigatório:

```text
UNIQUE(github_repository_id, github_issue_id)
INDEX(github_repository_id, issue_number)
```

## 6.13 Tabela user_repository_matches

```text
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
user_id BIGINT UNSIGNED NOT NULL
github_repository_id BIGINT UNSIGNED NOT NULL
match_score DECIMAL(5,2) NOT NULL
score_breakdown JSON NOT NULL
reasons JSON NOT NULL
generated_at DATETIME NOT NULL
expires_at DATETIME NOT NULL
created_at DATETIME NOT NULL
updated_at DATETIME NOT NULL
```

Índice obrigatório:

```text
UNIQUE(user_id, github_repository_id)
INDEX(user_id, match_score)
```

---

# 7. Rotas da API

Usar prefixo `/api/v1` caso a estrutura atual permita.

Exemplo:

```text
/api/v1/auth/me
/api/v1/matches
/api/v1/me/profile
```

Se não for possível aplicar versionamento sem quebrar deploy, manter as rotas sem prefixo, mas estruturar o Router para que o versionamento seja fácil no futuro.

Todas as rotas protegidas devem exigir usuário autenticado.

---

## 7.1 Sistema

| Método | Rota               |                  Autenticação | Descrição                              |
| ------ | ------------------ | ----------------------------: | -------------------------------------- |
| GET    | `/health`          |                           Não | Status simples da API                  |
| GET    | `/health/database` | Não em dev / restrito em prod | Teste de banco controlado por ambiente |

Resposta:

```json
{
  "success": true,
  "data": {
    "service": "dotti.work API",
    "status": "online",
    "version": "2.0.0"
  }
}
```

Não manter endpoints públicos que enviam e-mail ou exibem dados internos.

---

## 7.2 Autenticação

| Método | Rota                          | Auth | Descrição                      |
| ------ | ----------------------------- | ---: | ------------------------------ |
| GET    | `/auth/github/start`          |  Não | Inicia OAuth                   |
| GET    | `/auth/github/callback`       |  Não | Callback GitHub                |
| GET    | `/auth/me`                    |  Sim | Usuário atual                  |
| POST   | `/auth/logout`                |  Sim | Logout da sessão atual         |
| POST   | `/auth/logout-all`            |  Sim | Revoga todas as sessões        |
| GET    | `/auth/session`               |  Sim | Valida sessão atual            |
| GET    | `/integrations/github/status` |  Sim | Status do vínculo GitHub       |
| POST   | `/integrations/github/sync`   |  Sim | Atualiza perfil público GitHub |

### GET `/auth/me`

Resposta:

```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "login": "leomuller",
      "display_name": "Leo Müller",
      "email": "user@example.com",
      "avatar_url": "https://...",
      "github_profile_url": "https://github.com/...",
      "created_at": "2026-06-23T10:00:00Z"
    },
    "profile": {
      "role": "frontend_developer",
      "seniority": "senior",
      "onboarding_completed": true
    },
    "github": {
      "connected": true,
      "login": "leomuller"
    }
  }
}
```

Nunca retornar:

```text
senha
token
token_hash
access_token_encrypted
refresh_token_encrypted
provider_account_id
dados internos de sessão
```

---

## 7.3 Perfil

| Método | Rota                    | Auth | Descrição                       |
| ------ | ----------------------- | ---: | ------------------------------- |
| GET    | `/me/profile`           |  Sim | Retorna perfil completo         |
| PUT    | `/me/profile`           |  Sim | Atualiza perfil e objetivos     |
| POST   | `/me/import-local-data` |  Sim | Migra dados do MVP localStorage |
| GET    | `/me/export`            |  Sim | Exporta dados persistidos       |
| DELETE | `/me/account`           |  Sim | Exclui conta e dados            |

### PUT `/me/profile`

Body:

```json
{
  "display_name": "Leo Müller",
  "role": "frontend_developer",
  "seniority": "senior",
  "goals": [
    "build_portfolio",
    "practical_experience",
    "long_term_projects"
  ],
  "onboarding_completed": true
}
```

Regras:

* Atualizar em transação.
* Validar enums.
* Não permitir alteração de `user_id`.
* Não permitir alteração de dados OAuth diretamente.
* Não sobrescrever stacks ou preferências nesta rota.
* Atualizar `onboarding_completed_at` somente na primeira conclusão.

---

## 7.4 Tecnologias e stacks

| Método | Rota                          | Auth | Descrição                   |
| ------ | ----------------------------- | ---: | --------------------------- |
| GET    | `/catalog/technologies`       |  Não | Catálogo de tecnologias     |
| GET    | `/catalog/technologies/:slug` |  Não | Detalhe de tecnologia       |
| GET    | `/me/technologies`            |  Sim | Stacks do usuário           |
| PUT    | `/me/technologies`            |  Sim | Substitui stacks do usuário |

### GET `/catalog/technologies`

Suportar filtros:

```text
category
search
active
limit
cursor
```

### PUT `/me/technologies`

Body:

```json
{
  "technologies": [
    {
      "technology_id": 1,
      "proficiency_level": "advanced",
      "interest_level": "contribute"
    },
    {
      "technology_id": 5,
      "proficiency_level": "daily",
      "interest_level": "learn"
    }
  ]
}
```

Regras:

* Fazer replace completo em transação.
* Limite de 50 tecnologias por usuário.
* Não aceitar tecnologia inativa.
* Não aceitar IDs duplicados.
* Não permitir níveis fora dos enums.
* Nunca aceitar `user_id` no body.
* Sempre derivar o usuário pelo token autenticado.

---

## 7.5 Preferências

| Método | Rota              | Auth | Descrição             |
| ------ | ----------------- | ---: | --------------------- |
| GET    | `/me/preferences` |  Sim | Retorna preferências  |
| PUT    | `/me/preferences` |  Sim | Atualiza preferências |

### PUT `/me/preferences`

Body:

```json
{
  "contribution_types": [
    "bug_fix",
    "documentation",
    "tests",
    "accessibility"
  ],
  "difficulty_levels": [
    "beginner",
    "intermediate"
  ],
  "project_sizes": [
    "small",
    "medium"
  ],
  "documentation_languages": [
    "en",
    "pt"
  ],
  "organization_types": [
    "community",
    "company",
    "foundation"
  ],
  "activity_window_days": 90,
  "minimum_stars": 20,
  "require_good_first_issue": false,
  "require_help_wanted": false,
  "default_sort_by": "best_match"
}
```

Regras:

* Validar todos os valores.
* Limitar listas a tamanhos seguros.
* `minimum_stars` não pode ser negativo.
* `activity_window_days` deve estar entre 1 e 3650.
* Alteração de preferências deve invalidar matches antigos do usuário.

---

## 7.6 Matches

| Método | Rota                           | Auth | Descrição                 |
| ------ | ------------------------------ | ---: | ------------------------- |
| GET    | `/matches`                     |  Sim | Lista matches armazenados |
| POST   | `/matches/refresh`             |  Sim | Recalcula matches         |
| GET    | `/matches/:githubRepositoryId` |  Sim | Retorna match específico  |

### GET `/matches`

Query params:

```text
state
difficulty
technology
minimum_score
sort_by
limit
cursor
```

Resposta:

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "repository": {
          "github_repository_id": 123,
          "owner": "vercel",
          "name": "next.js",
          "description": "The React Framework for the Web",
          "html_url": "https://github.com/vercel/next.js",
          "stars": 130000,
          "forks": 28000,
          "open_issues": 2100,
          "good_first_issues": 14,
          "languages": ["TypeScript", "JavaScript"],
          "topics": ["react", "nextjs", "typescript"],
          "updated_at": "2026-06-21T10:00:00Z"
        },
        "match": {
          "score": 92.5,
          "recommended_seniority": "mid",
          "breakdown": {
            "stack": 35,
            "difficulty": 18,
            "issues": 15,
            "activity": 10,
            "health": 9,
            "contribution_readiness": 5
          },
          "reasons": [
            "React and TypeScript match your profile",
            "Active repository",
            "Good first issues available",
            "Contribution guide found"
          ]
        },
        "user_state": null
      }
    ],
    "pagination": {
      "next_cursor": "..."
    },
    "metadata": {
      "generated_at": "2026-06-23T10:00:00Z",
      "cached": true
    }
  }
}
```

### POST `/matches/refresh`

Regras:

* Não permitir atualização contínua e sem limite.
* Aplicar rate limit por usuário.
* Exemplo: uma atualização a cada 60 segundos.
* Usar cache quando os dados ainda forem válidos.
* Buscar candidatos GitHub em lotes pequenos.
* Deduplicar repositórios.
* Buscar issues apenas dos melhores candidatos.
* Persistir resultados em `user_repository_matches`.
* Não bloquear por longos períodos.
* Definir timeout HTTP para chamadas GitHub.
* Retornar status claro se GitHub estiver indisponível.

---

## 7.7 Repositórios

| Método | Rota                                  | Auth | Descrição              |
| ------ | ------------------------------------- | ---: | ---------------------- |
| GET    | `/repositories/:owner/:repo`          |  Sim | Detalhe de repositório |
| GET    | `/repositories/:owner/:repo/issues`   |  Sim | Issues recomendadas    |
| POST   | `/repositories/:owner/:repo/activity` |  Sim | Registra interação     |

### GET `/repositories/:owner/:repo`

Deve retornar:

* Dados gerais;
* Linguagens;
* Topics;
* Stars;
* Forks;
* Issues abertas;
* Última atividade;
* Licença;
* Site;
* Health score;
* CONTRIBUTING detectado;
* CODE_OF_CONDUCT detectado;
* Testes detectados;
* CI/CD detectado;
* Labels de contribuição;
* Estado do usuário;
* Match daquele usuário, se existir.

### GET `/repositories/:owner/:repo/issues`

Query params:

```text
difficulty
label
limit
cursor
```

Regras:

* Retornar apenas issues abertas.
* Ignorar pull requests quando a API GitHub devolver objetos de pull request na listagem de issues.
* Estimar dificuldade.
* Retornar labels.
* Indicar se possui `good first issue`.
* Indicar se possui `help wanted`.
* Usar cache.
* Não fazer uma chamada GitHub por card renderizado no front-end.

### POST `/repositories/:owner/:repo/activity`

Body:

```json
{
  "event_type": "opened_github"
}
```

Valores válidos:

```text
viewed_project
opened_github
started_contributing
sent_pull_request
marked_contributed
```

---

## 7.8 Estado de projetos do usuário

| Método | Rota                                           | Auth | Descrição                 |
| ------ | ---------------------------------------------- | ---: | ------------------------- |
| GET    | `/me/repositories`                             |  Sim | Lista projetos por estado |
| PUT    | `/me/repositories/:githubRepositoryId/state`   |  Sim | Define status             |
| DELETE | `/me/repositories/:githubRepositoryId/state`   |  Sim | Remove estado salvo       |
| POST   | `/me/repositories/:githubRepositoryId/restore` |  Sim | Restaura projeto ignorado |

### PUT `/me/repositories/:githubRepositoryId/state`

Body:

```json
{
  "state": "working",
  "notes": "Vou iniciar pela documentação do componente."
}
```

Estados:

```text
saved
ignored
researching
working
pull_request_sent
contributed
archived
```

Regras:

* O usuário não pode alterar o estado de outro usuário.
* Sempre usar `user_id` do token.
* Registrar evento no histórico.
* `ignored` deve remover o projeto da lista padrão de matches.
* `restore` deve retirar o estado `ignored` ou alterar para `saved`.
* Não criar tabelas duplicadas para favoritos e ignorados.
* Utilizar somente `user_repository_states`.

---

## 7.9 Histórico

| Método | Rota          | Auth | Descrição                  |
| ------ | ------------- | ---: | -------------------------- |
| GET    | `/me/history` |  Sim | Histórico paginado         |
| DELETE | `/me/history` |  Sim | Limpa histórico do usuário |

Query params:

```text
event_type
github_repository_id
limit
cursor
```

Regras:

* Histórico sempre filtrado por `user_id`.
* Nunca permitir exclusão de eventos de outro usuário.
* Limpar somente eventos do usuário autenticado.
* Salvar metadados mínimos e sem tokens.

---

# 8. GitHubClient

Criar `api/core/GitHubClient.php`.

Responsabilidades:

```text
exchangeOAuthCode()
getAuthenticatedUser()
getAuthenticatedUserEmails()
searchRepositories()
getRepository()
getRepositoryLanguages()
getRepositoryTopics()
getRepositoryIssues()
getRepositoryLabels()
getRepositoryContents()
getRateLimit()
```

Regras:

* Usar cURL ou client HTTP simples.
* Definir timeout de conexão e timeout total.
* Enviar User-Agent identificável.
* Enviar Accept adequado.
* Enviar versão de API via configuração.
* Tratar erros HTTP.
* Não expor resposta bruta do GitHub diretamente.
* Normalizar dados antes de salvar ou devolver ao front-end.
* Nunca registrar Authorization header em logs.
* Respeitar rate limits.
* Tratar respostas `403`, `429`, `500`, `502`, `503` e `504`.

---

# 9. Cache e limites

O GitHub possui limites de requisição e limites específicos para buscas. Portanto, o backend deve trabalhar com cache e evitar chamadas repetitivas.

Regras mínimas:

```text
Repository cache: 6 horas
Issues cache: 1 hora
User match cache: 1 hora
Refresh de matches por usuário: 1 minuto
Tecnologias catálogo: cache longo
```

Esses valores devem ser configuráveis.

Não implementar busca nova a cada renderização da tela.

Fluxo ideal:

```text
Front-end solicita matches
  -> Backend verifica user_repository_matches
  -> Se ainda válido, retorna dados persistidos
  -> Se expirado, front-end chama refresh
  -> Backend consulta cache de repositórios
  -> Consulta GitHub somente quando necessário
  -> Calcula score
  -> Persiste matches
  -> Retorna resultado
```

---

# 10. Algoritmo de matching

Criar `MatchService.php`.

O algoritmo inicial não deve depender de IA generativa.

Deve ser determinístico, testável e explicável.

Distribuição de score:

```text
Compatibilidade de stack: 35 pontos
Dificuldade e senioridade: 20 pontos
Issues compatíveis: 15 pontos
Atividade recente: 10 pontos
Saúde do repositório: 10 pontos
Facilidade para contribuir: 10 pontos
```

## 10.1 Compatibilidade de stack

Exemplos:

```text
Linguagem principal compatível: até 15 pontos
Framework/topic compatível: até 15 pontos
Bibliotecas/ferramentas compatíveis: até 5 pontos
```

## 10.2 Dificuldade e senioridade

Exemplos:

```text
Junior + good first issue: alta pontuação
Mid + bug/refactor/test: alta pontuação
Senior + arquitetura/performance/segurança: alta pontuação
```

## 10.3 Issues compatíveis

Considerar:

```text
good first issue
help wanted
bug
documentation
tests
accessibility
performance
refactor
```

## 10.4 Atividade

Considerar:

```text
Último push
Última release
Issues respondidas
Pull requests recentes
```

## 10.5 Saúde do repositório

Considerar:

```text
CONTRIBUTING.md
CODE_OF_CONDUCT.md
README
Licença
Testes
CI/CD
Issues organizadas
Labels úteis
```

## 10.6 Resposta explicável

Todo match deve armazenar:

```json
{
  "score": 92.5,
  "breakdown": {
    "stack": 35,
    "difficulty": 18,
    "issues": 15,
    "activity": 10,
    "health": 9,
    "contribution_readiness": 5
  },
  "reasons": [
    "TypeScript matches your profile",
    "React is one of your advanced technologies",
    "Repository is active",
    "Good first issues are available"
  ]
}
```

---

# 11. Estimativa de dificuldade de issues

Criar `IssueDifficultyService.php`.

Retornar:

```json
{
  "level": "beginner",
  "confidence": 0.85,
  "reasons": [
    "good first issue label found",
    "documentation topic found",
    "few comments"
  ]
}
```

Regras de classificação:

```text
Beginner:
good first issue
beginner friendly
documentation
translation
small UI change
simple test

Intermediate:
bug
feature
refactor
test coverage
performance
accessibility

Advanced:
architecture
security
breaking change
migration
infrastructure
complex performance work
```

Nunca apresentar dificuldade como certeza absoluta.

Usar nomenclaturas como:

```text
Estimated difficulty
Estimated level
Likely beginner-friendly
```

---

# 12. Segurança obrigatória

## 12.1 CORS

Não usar:

```http
Access-Control-Allow-Origin: *
```

quando houver cookies ou credenciais.

Permitir apenas origens configuradas:

```text
https://dotti.work
http://localhost:3000
```

Headers esperados:

```http
Access-Control-Allow-Origin: origem-validada
Access-Control-Allow-Credentials: true
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With
Vary: Origin
```

## 12.2 Cookies

Sessão deve usar:

```text
HttpOnly
Secure em produção
SameSite=Lax
Path=/
```

## 12.3 Proteção de rotas mutáveis

Para `POST`, `PUT`, `PATCH` e `DELETE`:

* Validar usuário autenticado;
* Validar `Origin` quando usar cookie;
* Aplicar validação de entrada;
* Nunca usar `user_id` vindo do body;
* Sempre filtrar SQL por `user_id`;
* Usar statements preparados;
* Usar transações para alterações compostas.

## 12.4 Proteção de dados

* Criptografar token GitHub no banco.
* Hash de tokens de sessão.
* Não registrar tokens no Sentry.
* Não enviar dados sensíveis ao front-end.
* Não retornar exceções cruas.
* Não usar segredo hardcoded.
* Não permitir `return_to` externo.
* Não permitir SQL dinâmico sem whitelist.
* Não expor models, core, config, vendor ou tests via Apache.

## 12.5 .htaccess

Corrigir regras para bloquear diretórios reais:

```text
config
core
controller
model
service
tests
vendor
migrations
templates
```

Permitir apenas o entrypoint necessário.

---

# 13. Request, Response e validações

## 13.1 Request.php

Adicionar métodos:

```php
getQuery($key, $default = null)
getHeader($name)
getAuthorizationBearerToken()
getCookie($name)
getJsonBody()
getClientIp()
getOrigin()
```

`getAuthorizationBearerToken()` deve interpretar:

```http
Authorization: Bearer TOKEN
```

Não depender do header `key`.

## 13.2 Response.php

Padronizar todas as respostas.

Sucesso:

```json
{
  "success": true,
  "data": {}
}
```

Erro:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Dados inválidos.",
    "details": []
  }
}
```

Status esperados:

```text
200 OK
201 Created
204 No Content
400 Bad Request
401 Unauthorized
403 Forbidden
404 Not Found
409 Conflict
422 Unprocessable Entity
429 Too Many Requests
500 Internal Server Error
502 Bad Gateway
503 Service Unavailable
```

## 13.3 Validator.php

Criar uma classe simples para:

```text
required
string
email
integer
boolean
enum
array
arrayOfEnum
maxLength
minLength
url
nullable
uniqueArray
```

Não acessar campos inexistentes diretamente em controllers.

---

# 14. Migração dos dados do MVP local

O front-end atual usa `localStorage`.

Criar endpoint:

```http
POST /me/import-local-data
```

Body esperado:

```json
{
  "profile": {},
  "technologies": [],
  "preferences": {},
  "repository_states": [],
  "history": []
}
```

Regras:

* Aceitar somente usuário autenticado.
* Validar todo o payload.
* Ignorar IDs locais.
* Não permitir que o front informe outro `user_id`.
* Criar ou atualizar dados no usuário autenticado.
* Usar transação.
* Limitar quantidade de eventos importados.
* Registrar que a importação foi feita.
* Deve ser seguro chamar mais de uma vez.
* Não criar duplicatas de tecnologias ou estados de repositório.

Também criar:

```http
GET /me/export
```

A exportação deve conter apenas dados do usuário autenticado.

---

# 15. OpenAPI e documentação

Atualizar completamente `openapi.yaml`.

Deve incluir:

* Segurança Bearer;
* Fluxo de cookie para browser;
* Todas as rotas;
* Exemplos de request;
* Exemplos de response;
* Schemas;
* Paginação cursor-based;
* Códigos de erro;
* Objetos de perfil;
* Tecnologias;
* Preferências;
* Repositórios;
* Matches;
* Histórico;
* Estados de projeto.

Remover documentação de tarefas, categorias e login por senha.

---

# 16. Testes obrigatórios

Criar testes PHPUnit para no mínimo:

```text
Auth:
- geração de state
- state expirado
- callback com state inválido
- criação de usuário por GitHub
- atualização de usuário existente
- token local nunca retornado em GET /auth/me
- logout revoga sessão

Perfil:
- usuário atualiza somente o próprio perfil
- goals são salvos corretamente
- validação de senioridade

Tecnologias:
- não aceita tecnologia duplicada
- não aceita tecnologia inexistente
- replace em transação

Preferências:
- enums inválidos retornam 422
- alteração invalida matches antigos

Projetos:
- usuário não altera estado de outro usuário
- ignored remove item dos matches padrão
- restore funciona

Matches:
- cálculo determinístico
- score contém breakdown
- cache válido evita chamada GitHub
- refresh respeita rate limit

Segurança:
- Authorization Bearer funciona
- header key não é obrigatório
- token GitHub não aparece em responses
- token GitHub não aparece em logs simulados
```

---

# 17. Critérios de aceite

A implementação estará correta quando:

1. O usuário consegue clicar em “Login com GitHub”.
2. O backend inicia OAuth com state seguro.
3. O callback valida state e cria sessão local.
4. O front-end recebe somente cookie de sessão, nunca token GitHub.
5. `GET /auth/me` retorna usuário autenticado.
6. O usuário consegue salvar perfil, objetivos, senioridade, stacks e preferências.
7. O usuário consegue buscar matches.
8. O backend reutiliza cache para reduzir chamadas GitHub.
9. O usuário consegue salvar, ignorar e atualizar status de projetos.
10. O histórico registra ações.
11. O usuário consegue importar dados do MVP localStorage.
12. O usuário consegue exportar seus dados.
13. Todas as consultas de dados pessoais filtram pelo usuário autenticado.
14. Não existem rotas públicas perigosas de teste.
15. Não existe secret hardcoded.
16. Não existe autenticação baseada no header `key`.
17. Todas as respostas seguem o padrão novo.
18. `openapi.yaml` representa o comportamento real da API.
19. O backend continua rodando em PHP nativo com PDO/MySQL.
20. O projeto compila e os testes passam.

---

# 18. Ordem de implementação

Executar nesta ordem:

```text
1. Corrigir infraestrutura base:
   - CORS
   - Response
   - Request
   - Auth
   - secrets .env
   - .htaccess
   - Sentry

2. Criar migrations:
   - adaptar users
   - auth_tokens
   - oauth_accounts
   - oauth states
   - perfil
   - tecnologias
   - preferências
   - projetos
   - histórico
   - cache
   - matches

3. Implementar OAuth GitHub:
   - start
   - callback
   - sessão
   - logout
   - me

4. Implementar perfil, stacks e preferências.

5. Implementar catálogo de tecnologias.

6. Implementar GitHubClient e cache.

7. Implementar MatchService e score.

8. Implementar projetos salvos, ignorados e histórico.

9. Implementar importação do localStorage.

10. Atualizar OpenAPI.

11. Criar testes.

12. Remover domínio antigo de tarefas e endpoints inseguros.
```

Não pular diretamente para integração GitHub antes de corrigir autenticação, CORS, segredos e isolamento por usuário.
