-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 14/03/2026 às 12:00
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `dottiwork_db`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `auth_tokens`
--

CREATE TABLE `auth_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `auth_tokens`
-- (sem tokens ativos no dump inicial)

-- --------------------------------------------------------

--
-- Estrutura para tabela `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL COMMENT 'Identificador único do feedback',
  `user_id` int(11) NOT NULL COMMENT 'ID do usuário que enviou o feedback',
  `rating` tinyint(4) NOT NULL COMMENT 'Nota de avaliação (1 a 5)',
  `suggestion` text DEFAULT NULL COMMENT 'Campo livre para sugestões do usuário',
  `status` enum('pendente','analisado','implementado','descartado') DEFAULT 'pendente' COMMENT 'Status da análise do feedback',
  `admin_notes` text DEFAULT NULL COMMENT 'Notas do administrador sobre o feedback',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'Data/hora em que o feedback foi enviado',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Data/hora da última atualização'
) ;

--
-- Despejando dados para a tabela `feedback`
--

INSERT INTO `feedback` (`id`, `user_id`, `rating`, `suggestion`, `status`, `admin_notes`, `created_at`, `updated_at`) VALUES
(1, 1, 5, 'Adorei o app! Seria incrível ter integração com o Google Calendar para sincronizar as tarefas.', 'pendente',    NULL,                                  '2026-03-11 10:00:00', '2026-03-11 10:00:00'),
(2, 1, 4, 'A interface é bem intuitiva. Senti falta de notificações por e-mail quando o prazo está próximo.',              'analisado',   'Funcionalidade no backlog Q2', '2026-03-12 16:30:00', '2026-03-13 09:00:00'),
(3, 2, 3, 'Funcionando bem no geral, mas às vezes demora para carregar as categorias no celular.',                          'analisado',   'Investigar performance mobile', '2026-03-12 20:00:00', '2026-03-13 11:00:00'),
(4, 2, 5, 'Muito bom! Uso todo dia para organizar minhas tarefas pessoais. Parabéns pela simplicidade.',                   'implementado', 'Feedback positivo arquivado',  '2026-03-13 08:00:00', '2026-03-14 09:00:00');

-- --------------------------------------------------------

--
-- Estrutura para tabela `notepads`
--

CREATE TABLE `notepads` (
  `id` int(11) NOT NULL COMMENT 'Identificador único do bloco de notas',
  `user_id` int(11) NOT NULL COMMENT 'ID do usuário dono do bloco de notas',
  `title` varchar(255) NOT NULL COMMENT 'Título do bloco de notas',
  `content` text DEFAULT NULL COMMENT 'Conteúdo do bloco de notas',
  `color` varchar(7) DEFAULT '#ffffff' COMMENT 'Cor do bloco de notas (formato hexadecimal)',
  `is_favorite` tinyint(1) DEFAULT 0 COMMENT 'Indica se o bloco é favorito',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'Data/hora de criação do bloco',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Data/hora da última atualização'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Armazena os blocos de notas dos usuários';

--
-- Despejando dados para a tabela `notepads`
--

INSERT INTO `notepads` (`id`, `user_id`, `title`, `content`, `color`, `is_favorite`, `created_at`, `updated_at`) VALUES
(1, 1, 'Ideias para o produto', 'Adicionar integração com Google Calendar\nNotificações push para tarefas com prazo\nDashboard com métricas de produtividade', '#fef9c3', 1, '2026-03-10 08:30:00', '2026-03-12 11:00:00'),
(2, 1, 'Links úteis de desenvolvimento', 'MDN Web Docs: https://developer.mozilla.org\nPHP FIG / PSR: https://www.php-fig.org/psr/\nConvensões de commit: https://www.conventionalcommits.org', '#dbeafe', 0, '2026-03-10 09:00:00', '2026-03-10 09:00:00'),
(3, 1, 'Checklist de deploy', '1. Rodar testes unitários\n2. Atualizar variáveis de ambiente\n3. Fazer backup do banco\n4. Deploy no servidor de homologação\n5. Validar endpoints críticos\n6. Deploy em produção', '#dcfce7', 1, '2026-03-11 14:00:00', '2026-03-13 10:30:00'),
(4, 2, 'Lista de compras do mês', 'Arroz, feijão, macarrão\nFrango, carne moída\nLeite, iogurte, queijo\nFrutas da estação\nDetergente, sabão em pó', '#fce7f3', 0, '2026-03-10 10:30:00', '2026-03-10 10:30:00'),
(5, 2, 'Senhas e anotações importantes', 'Nota: não guardas senhas aqui em texto puro! Use um gerenciador de senhas.', '#fee2e2', 1, '2026-03-11 09:00:00', '2026-03-11 09:00:00'),
(6, 2, 'Receita de frango grelhado', 'Ingredientes: 2 filés de frango, limão, alho, azeite, sal e pimenta\nPreparo: Marinar por 30min, grelhar 7min cada lado em fogo médio', '#ede9fe', 0, '2026-03-12 19:00:00', '2026-03-12 19:00:00');

-- --------------------------------------------------------

--
-- Estrutura para tabela `todo_categories`
--

CREATE TABLE `todo_categories` (
  `id` int(11) NOT NULL COMMENT 'Identificador único da categoria',
  `user_id` int(11) NOT NULL COMMENT 'ID do usuário dono da categoria',
  `name` varchar(100) NOT NULL COMMENT 'Nome da categoria (ex: Trabalho, Pessoal)',
  `color` varchar(7) DEFAULT '#3b82f6' COMMENT 'Cor da categoria (formato hexadecimal)',
  `icon` varchar(50) DEFAULT '?' COMMENT 'Ícone ou emoji da categoria',
  `display_order` int(11) DEFAULT 0 COMMENT 'Ordem de exibição definida pelo usuário',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'Data/hora de criação',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Data/hora da última atualização'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categorias/conjuntos de listas de tarefas';

--
-- Despejando dados para a tabela `todo_categories`
--

INSERT INTO `todo_categories` (`id`, `user_id`, `name`, `color`, `icon`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 1, 'Trabalho', '#3b82f6', '💼', 1, '2026-03-10 09:00:00', '2026-03-10 09:00:00'),
(2, 1, 'Projetos', '#06b6d4', '🚀', 2, '2026-03-10 09:05:00', '2026-03-10 09:05:00'),
(3, 1, 'Reuniões', '#8b5cf6', '👥', 3, '2026-03-10 09:10:00', '2026-03-10 09:10:00'),
(4, 1, 'Documentação', '#ec4899', '📄', 4, '2026-03-10 09:15:00', '2026-03-10 09:15:00'),
(5, 1, 'Pendências', '#f59e0b', '⏰', 5, '2026-03-10 09:20:00', '2026-03-10 09:20:00'),
(6, 2, 'Pessoal', '#ef4444', '👤', 1, '2026-03-10 10:00:00', '2026-03-10 10:00:00'),
(7, 2, 'Compras', '#8b5cf6', '🛒', 2, '2026-03-10 10:05:00', '2026-03-10 10:05:00'),
(8, 2, 'Casa', '#14b8a6', '🏠', 3, '2026-03-10 10:10:00', '2026-03-10 10:10:00'),
(9, 2, 'Saúde', '#10b981', '❤️', 4, '2026-03-10 10:15:00', '2026-03-10 10:15:00'),
(10, 2, 'Lazer', '#d946ef', '🎮', 5, '2026-03-10 10:20:00', '2026-03-10 10:20:00');

-- --------------------------------------------------------

--
-- Estrutura para tabela `todo_lists`
--

CREATE TABLE `todo_lists` (
  `id` int(11) NOT NULL COMMENT 'Identificador único da tarefa',
  `user_id` int(11) NOT NULL COMMENT 'ID do usuário dono da tarefa',
  `category_id` int(11) DEFAULT NULL COMMENT 'ID da categoria (NULL = sem categoria)',
  `title` varchar(255) NOT NULL COMMENT 'Título/descrição da tarefa',
  `description` text DEFAULT NULL COMMENT 'Descrição detalhada da tarefa',
  `is_completed` tinyint(1) DEFAULT 0 COMMENT 'Indica se a tarefa foi concluída',
  `priority` enum('baixa','media','alta') DEFAULT 'media' COMMENT 'Prioridade da tarefa',
  `display_order` int(11) DEFAULT 0 COMMENT 'Ordem de exibição dentro da categoria',
  `due_date` datetime DEFAULT NULL COMMENT 'Data/hora de vencimento da tarefa',
  `completed_at` datetime DEFAULT NULL COMMENT 'Data/hora em que a tarefa foi concluída',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'Data/hora de criação da tarefa',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Data/hora da última atualização'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Armazena as tarefas (to-dos) dos usuários';

--
-- Despejando dados para a tabela `todo_lists`
--

INSERT INTO `todo_lists` (`id`, `user_id`, `category_id`, `title`, `description`, `is_completed`, `priority`, `display_order`, `due_date`, `completed_at`, `created_at`, `updated_at`) VALUES
(1,  1, 1, 'Finalizar relatório trimestral',       'Completar e revisar o relatório de desempenho do Q1',        0, 'alta',  1, '2026-03-20 18:00:00', NULL,                  '2026-03-10 09:30:00', '2026-03-10 09:30:00'),
(2,  1, 2, 'Configurar repositório do novo projeto','Criar estrutura de pastas, README e pipeline de CI/CD',      0, 'alta',  1, '2026-03-18 14:00:00', NULL,                  '2026-03-10 09:35:00', '2026-03-10 09:35:00'),
(3,  1, 3, 'Reunião com stakeholders',              'Apresentar roadmap do produto para o time de negócios',      1, 'media', 1, '2026-03-12 10:00:00', '2026-03-12 10:45:00', '2026-03-10 09:40:00', '2026-03-12 10:45:00'),
(4,  1, 4, 'Atualizar documentação da API',         'Adicionar novos endpoints ao OpenAPI e exemplos de uso',     0, 'media', 1, '2026-03-22 17:00:00', NULL,                  '2026-03-10 09:45:00', '2026-03-10 09:45:00'),
(5,  1, 5, 'Revisar pull requests da equipe',       'Code review de 3 PRs aguardando aprovação',                  0, 'alta',  1, '2026-03-15 16:00:00', NULL,                  '2026-03-10 09:50:00', '2026-03-10 09:50:00'),
(6,  1, 1, 'Preparar apresentação do sprint',       'Slides para o sprint review de sexta-feira',                 0, 'media', 2, '2026-03-14 09:00:00', NULL,                  '2026-03-10 10:00:00', '2026-03-10 10:00:00'),
(7,  1, 2, 'Implementar autenticação JWT',          'Adicionar suporte a JWT no módulo de auth do projeto',       1, 'alta',  2, '2026-03-11 18:00:00', '2026-03-11 17:30:00', '2026-03-10 10:05:00', '2026-03-11 17:30:00'),
(8,  1, 5, 'Responder e-mails pendentes',           'Responder ao cliente sobre prazo de entrega',                0, 'media', 2, '2026-03-14 12:00:00', NULL,                  '2026-03-10 10:10:00', '2026-03-10 10:10:00'),
(9,  2, 6, 'Ligar para o médico',                   'Agendar consulta de rotina',                                 1, 'media', 1, '2026-03-15 12:00:00', '2026-03-11 09:00:00', '2026-03-10 10:00:00', '2026-03-11 09:00:00'),
(10, 2, 7, 'Comprar frutas e vegetais',             'Cenoura, maçã, alface, brócolis e tomate',                   0, 'media', 1, '2026-03-14 19:00:00', NULL,                  '2026-03-10 10:05:00', '2026-03-10 10:05:00'),
(11, 2, 8, 'Consertar vazamento da pia',            'Chamar encanador ou tentar resolver com vídeo no YouTube',   0, 'alta',  1, '2026-03-16 14:00:00', NULL,                  '2026-03-10 10:10:00', '2026-03-10 10:10:00'),
(12, 2, 9, 'Tomar vitaminas pela manhã',            'Vitamina D3 e complexo B em jejum',                          0, 'alta',  1, NULL,                  NULL,                  '2026-03-10 10:15:00', '2026-03-10 10:15:00'),
(13, 2, 10,'Assistir novo filme indicado',          'Ver o filme no fim de semana com a família',                  0, 'baixa', 1, '2026-03-15 20:00:00', NULL,                  '2026-03-10 10:20:00', '2026-03-10 10:20:00'),
(14, 2, 7, 'Renovar seguro do carro',               'Contatar corretora antes do vencimento',                     0, 'alta',  2, '2026-03-20 18:00:00', NULL,                  '2026-03-10 10:25:00', '2026-03-10 10:25:00'),
(15, 2, 8, 'Organizar armário da sala',             'Separar roupas para doação e reorganizar cabides',           0, 'baixa', 2, '2026-03-22 16:00:00', NULL,                  '2026-03-10 10:30:00', '2026-03-10 10:30:00'),
(16, 2, 10,'Jogar partida de xadrez online',        'Manter sequência diária no lichess',                         1, 'baixa', 2, NULL,                  '2026-03-13 21:00:00', '2026-03-10 10:35:00', '2026-03-13 21:00:00');

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `login` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(30) NOT NULL,
  `last_name` varchar(30) NOT NULL,
  `ultimo_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `login`, `senha`, `email`, `first_name`, `last_name`, `ultimo_login`, `created_at`) VALUES
(1, 'leomullerluiz', '$2y$10$FLzS9FeillPPgqF6XBd7UuJztYq2iIr.E0zF3o/shtWmpWSDmOuHm', 'leomullerluiz@gmail.com', 'Leo', 'Muller',  '2026-03-14 10:00:00', '2026-03-10 08:00:00'),
(2, 'teste',         '$2y$10$bqoDUDli9WAwQPVLrfvzKO2vISYVfkmoO0eTysUCgZPvx9gAleMhq', 'teste@teste.com',          'Teste', 'Usuário', '2026-03-13 18:00:00', '2026-03-10 09:00:00');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Índices de tabela `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_rating` (`rating`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices de tabela `notepads`
--
ALTER TABLE `notepads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_is_favorite` (`is_favorite`);

--
-- Índices de tabela `todo_categories`
--
ALTER TABLE `todo_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_display_order` (`display_order`);

--
-- Índices de tabela `todo_lists`
--
ALTER TABLE `todo_lists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_category_id` (`category_id`),
  ADD KEY `idx_is_completed` (`is_completed`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_display_order` (`display_order`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_login` (`login`),
  ADD KEY `idx_email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `auth_tokens`
--
ALTER TABLE `auth_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de tabela `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Identificador único do feedback', AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `notepads`
--
ALTER TABLE `notepads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Identificador único do bloco de notas', AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `todo_categories`
--
ALTER TABLE `todo_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Identificador único da categoria', AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `todo_lists`
--
ALTER TABLE `todo_lists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Identificador único da tarefa', AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD CONSTRAINT `auth_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `notepads`
--
ALTER TABLE `notepads`
  ADD CONSTRAINT `notepads_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `todo_categories`
--
ALTER TABLE `todo_categories`
  ADD CONSTRAINT `todo_categories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `todo_lists`
--
ALTER TABLE `todo_lists`
  ADD CONSTRAINT `todo_lists_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `todo_lists_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `todo_categories` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
