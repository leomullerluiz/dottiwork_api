# Documentacao Tecnica - dotti.work API v2

Esta documentacao resume a arquitetura atual. O contrato detalhado de rotas, schemas e exemplos fica em `openapi.yaml`.

## Arquitetura

```text
Cliente
  -> Apache/.htaccess
  -> api/index.php
  -> Router
  -> Controller
  -> Service
  -> Model
  -> PDO/MySQL
  -> Response JSON
```

## Camadas

- `api/core`: infraestrutura HTTP, sessao, OAuth, GitHubClient, criptografia e validacao.
- `api/controller`: entrada das rotas; autentica, valida e chama services/models.
- `api/model`: acesso a banco via PDO.
- `api/service`: regras de negocio, matching, saude de repositorio, dificuldade de issues e import/export.
- `migrations`: schema incremental da API v2.
- `docs`: Swagger UI.

## Autenticacao

O unico login suportado no MVP e GitHub OAuth App.

Regras:

- Nao existe signup manual.
- Nao existe login por senha.
- Nao existe password reset.
- O access token do GitHub e criptografado no banco.
- O access token do GitHub nunca e enviado ao front-end.
- A sessao da API usa token opaco proprio.
- O banco salva apenas `token_hash`.
- O browser deve usar cookie HttpOnly com `credentials: "include"`.
- Integracoes externas podem usar `Authorization: Bearer TOKEN`.

## Respostas

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
    "message": "Dados invalidos.",
    "details": []
  }
}
```

## Dominio

O dominio antigo de tarefas foi descontinuado nas rotas publicas. O dominio atual possui:

- Usuarios autenticados via GitHub.
- Perfil profissional.
- Objetivos de portfolio/contribuicao.
- Catalogo de tecnologias.
- Stacks do usuario.
- Preferencias de matching.
- Cache de repositorios e issues do GitHub.
- Matches persistidos e explicaveis.
- Estados de repositorio por usuario.
- Historico de interacoes.
- Importacao de dados do MVP localStorage.
- Exportacao dos dados do usuario.

## Migration

Aplicar:

```bash
mysql -u usuario -p banco < migrations/202606230001_open_source_portal.sql
```

Ela preserva tabelas antigas e cria/adapta as estruturas da API v2.

## Seguranca

- CORS usa origens configuradas em `CORS_ALLOWED_ORIGINS`.
- Cookies usam `HttpOnly`, `SameSite=Lax` e `Secure` em producao.
- Mutacoes validam `Origin` quando presente.
- SQL usa prepared statements.
- `return_to` OAuth aceita apenas caminhos internos.
- `.htaccess` bloqueia acesso direto a `config`, `core`, `controller`, `model`, `service`, `tests`, `vendor`, `migrations` e `templates`.

## Testes

```bash
vendor/bin/phpunit
```

Os testes atuais cobrem componentes sem banco: token local, parsing Bearer, seguranca do `return_to`, validacoes, estimativa de dificuldade e score deterministico.
