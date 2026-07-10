# Como adicionar novas badges

Este guia descreve o caminho recomendado para adicionar novas badges ao backend do dotti.work.

## Visao geral

O front nao precisa conhecer regras de conquista. Ele consome:

- `GET /badges` para o catalogo publico.
- `GET /me/badges` para badges conquistadas, progresso e conquistas recentes.
- `POST /me/badges/evaluate` para recalculo manual do usuario autenticado.

Toda badge nasce em `badge_definitions`. Quando um usuario cumpre o criterio, o backend grava uma linha unica em `user_badges`.

As badges devem apontar para imagens publicas em `uploads/media/badges`.

## Quando basta adicionar seed

Se o criterio ja existe em `BadgeProgressService`, basta inserir uma nova definicao em `badge_definitions`.

Criterios suportados hoje:

- `profile_onboarding_completed`
- `technology_count`
- `preferences_defined`
- `activity_event_count`
- `activity_event_exists`
- `activity_event_or_repository_state_exists`
- `repository_state_count`
- `repository_state_exists`
- `repository_language_saved_count`
- `issue_label_interaction_count`
- `activity_distinct_days`
- `referral_count`
- `alpha_user`

Exemplo:

```sql
INSERT INTO badge_definitions (
  slug, name, description, category, level, image_url, image_alt, icon,
  is_active, is_secret, display_order, criteria_type, criteria_config,
  created_at, updated_at
) VALUES (
  'view_20_projects',
  'Mapa aberto',
  'Visualizou 20 projetos open source.',
  'discovery',
  'gold',
  '/uploads/media/badges/view_20_projects.png',
  'Insignia de exploracao avancada de projetos',
  'map',
  1,
  0,
  190,
  'activity_event_count',
  JSON_OBJECT('event_type', 'viewed_project', 'threshold', 20, 'distinct_repositories', true),
  NOW(),
  NOW()
) ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  category = VALUES(category),
  level = VALUES(level),
  image_url = VALUES(image_url),
  image_alt = VALUES(image_alt),
  icon = VALUES(icon),
  is_active = VALUES(is_active),
  is_secret = VALUES(is_secret),
  display_order = VALUES(display_order),
  criteria_type = VALUES(criteria_type),
  criteria_config = VALUES(criteria_config),
  updated_at = NOW();
```

## Quando precisa alterar codigo

Crie um novo `criteria_type` quando a regra nao puder ser expressa pelos criterios atuais.

Passos:

1. Adicione o calculo em `BadgeProgressService::currentValue`.
2. Se o calculo acessar banco, crie um metodo publico no proprio service e injete-o no array `$deps` do construtor.
3. Use `criteria_config` para qualquer parametro variavel, como `threshold`, `event_type`, `state`, `label` ou janela de dias.
4. Adicione testes em `tests/BadgeProgressServiceTest.php`.
5. Crie uma migration com o seed da badge.
6. Atualize o `db_dump.sql` se a badge deve existir em instalacoes novas.

## Onde disparar avaliacao

O avaliador ja roda depois de:

- atualizar perfil/onboarding;
- atualizar tecnologias;
- atualizar preferencias;
- visualizar ou registrar atividade em repositorio;
- mudar estado de repositorio;
- importar dados locais;
- registrar uma indicacao efetiva por convite.

Se uma badge depender de um novo fluxo, chame:

```php
(new BadgeEvaluatorService())->evaluateUser($userId);
```

Se houver um `user_activity_events.id` que explique a conquista, prefira:

```php
(new BadgeEvaluatorService())->evaluateAfterActivityEvent($userId, $eventType, $eventId);
```

## Recalculo para usuarios antigos

Depois de publicar uma nova badge retroativa:

1. Aplique a migration/seed.
2. Rode `POST /me/badges/evaluate` para um usuario especifico durante testes.
3. Para recalculo em massa, crie um comando/script administrativo que percorra `users` ativos e chame `BadgeEvaluatorService::evaluateUser($userId)`.

`user_badges` tem indice unico em `(user_id, badge_id)`, entao o recalculo e idempotente.

## Checklist

- `slug` unico, estavel e em snake_case.
- `name`, `description` e `image_alt` claros para o front.
- `image_url` preenchido com o caminho publico da imagem, por exemplo `/uploads/media/badges/minha_badge.png`.
- `criteria_type` conhecido pelo backend.
- `criteria_config` com `threshold` ou `target` quando aplicavel.
- Teste unitario cobrindo o criterio novo.
- `GET /badges` retorna a definicao.
- `GET /me/badges` mostra progresso e `awarded_at` quando conquistada.
