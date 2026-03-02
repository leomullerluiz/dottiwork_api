-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 02/03/2026 às 23:16
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
-- Banco de dados: `dotti_api`
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
--

INSERT INTO `auth_tokens` (`id`, `user_id`, `token`, `expires_at`, `created_at`) VALUES
(10, 2, 'b2c626264c360bedd926db7c5e46562f9887b1f87e1b8edb5c70e38cfc4fa9ec', '2026-02-21 20:04:39', '2026-02-21 15:04:39'),
(11, 2, '6ca1160e776d5b917be007afe4f5159986d6a960bc10052532561b8b95160991', '2026-02-21 20:04:43', '2026-02-21 15:04:43'),
(12, 2, 'aa529416a8d68139ac5481db50ebb81a72eb9350fb0e77e63318e194354d4c5e', '2026-02-22 05:47:55', '2026-02-22 00:47:55'),
(13, 2, 'c3b2f4897afd771988e0394356ddd750d085420721731ec7dbfdc63d4f2559a0', '2026-03-02 22:26:14', '2026-03-02 17:26:14');

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
(1, 1, 'Trabalho', '#3b82f6', '💼', 1, '2026-03-02 17:00:00', '2026-03-02 17:00:00'),
(2, 1, 'Projetos', '#06b6d4', '🚀', 2, '2026-03-02 17:05:00', '2026-03-02 17:05:00'),
(3, 1, 'Reuniões', '#8b5cf6', '👥', 3, '2026-03-02 17:10:00', '2026-03-02 17:10:00'),
(4, 1, 'Documentação', '#ec4899', '📄', 4, '2026-03-02 17:15:00', '2026-03-02 17:15:00'),
(5, 1, 'Pendências', '#f59e0b', '⏰', 5, '2026-03-02 17:20:00', '2026-03-02 17:20:00'),
(6, 2, 'Pessoal', '#ef4444', '👤', 1, '2026-03-02 17:25:00', '2026-03-02 17:25:00'),
(7, 2, 'Compras', '#8b5cf6', '🛒', 2, '2026-03-02 17:30:00', '2026-03-02 17:30:00'),
(8, 2, 'Casa', '#14b8a6', '🏠', 3, '2026-03-02 17:35:00', '2026-03-02 17:35:00'),
(9, 2, 'Saúde', '#10b981', '❤️', 4, '2026-03-02 17:40:00', '2026-03-02 17:40:00'),
(10, 2, 'Lazer', '#d946ef', '🎮', 5, '2026-03-02 17:45:00', '2026-03-02 17:45:00'),
(11, 14, 'Saúde', '#10b981', '❤️', 1, '2026-03-02 17:50:00', '2026-03-02 17:50:00'),
(12, 14, 'Exercícios', '#06b6d4', '🏃', 2, '2026-03-02 17:55:00', '2026-03-02 17:55:00'),
(13, 14, 'Nutrição', '#84cc16', '🥗', 3, '2026-03-02 18:00:00', '2026-03-02 18:00:00'),
(14, 14, 'Médico', '#f472b6', '⚕️', 4, '2026-03-02 18:05:00', '2026-03-02 18:05:00'),
(15, 14, 'Bem-estar', '#a78bfa', '🧘', 5, '2026-03-02 18:10:00', '2026-03-02 18:10:00'),
(16, 15, 'Estudos', '#f59e0b', '📚', 1, '2026-03-02 18:15:00', '2026-03-02 18:15:00'),
(17, 15, 'Provas', '#ef4444', '✏️', 2, '2026-03-02 18:20:00', '2026-03-02 18:20:00'),
(18, 15, 'Trabalhos', '#3b82f6', '📝', 3, '2026-03-02 18:25:00', '2026-03-02 18:25:00'),
(19, 15, 'Leitura', '#8b5cf6', '📖', 4, '2026-03-02 18:30:00', '2026-03-02 18:30:00'),
(20, 15, 'Pesquisa', '#06b6d4', '🔍', 5, '2026-03-02 18:35:00', '2026-03-02 18:35:00');

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
  (1, 1, 1, 'Finalizar relatório trimestral', 'Completar e revisar o relatório de desempenho do Q1', 0, 'alta', 1, '2026-03-05 18:00:00', NULL, '2026-03-02 17:30:00', '2026-03-02 17:30:00'),
(2, 1, 2, 'Iniciar novo projeto client', 'Kickoff meeting e setup do repositório', 0, 'alta', 2, '2026-03-06 14:00:00', NULL, '2026-03-02 17:35:00', '2026-03-02 17:35:00'),
(3, 1, 3, 'Participar de reunião com stakeholders', 'Discussão sobre roadmap do produto', 1, 'media', 1, '2026-03-03 10:00:00', '2026-03-02 09:30:00', '2026-03-02 17:40:00', '2026-03-02 09:30:00'),
(4, 1, 4, 'Atualizar documentação da API', 'Adicionar novos endpoints e exemplos', 0, 'media', 3, '2026-03-10 17:00:00', NULL, '2026-03-02 17:45:00', '2026-03-02 17:45:00'),
(5, 1, 5, 'Revisar pull requests da equipe', 'Code review de 3 PRs aguardando', 0, 'alta', 2, '2026-03-03 16:00:00', NULL, '2026-03-02 17:50:00', '2026-03-02 17:50:00'),
(6, 2, 6, 'Ligar para o médico', 'Agendar consulta de rotina', 1, 'media', 1, '2026-03-10 12:00:00', '2026-03-02 15:45:00', '2026-03-02 14:00:00', '2026-03-02 15:45:00'),
(7, 2, 7, 'Comprar frutas e vegetais', 'Cenoura, maçã, alface e brócolis', 0, 'media', 1, '2026-03-04 19:00:00', NULL, '2026-03-02 10:30:00', '2026-03-02 10:30:00'),
(8, 2, 8, 'Consertar vazamento da pia', 'Chamar encanador ou tentar consertar', 0, 'alta', 2, '2026-03-05 14:00:00', NULL, '2026-03-02 16:20:00', '2026-03-02 16:20:00'),
(9, 2, 9, 'Tomar medicamento da pressão', 'Tomar diariamente pela manhã', 0, 'alta', 1, NULL, NULL, '2026-03-02 06:00:00', '2026-03-02 06:00:00'),
(10, 2, 10, 'Assistir novo filme indicado', 'Ver o filme no fim de semana', 0, 'baixa', 2, '2026-03-08 20:00:00', NULL, '2026-03-02 15:00:00', '2026-03-02 15:00:00'),
(11, 14, 11, 'Tomar medicamento', 'Tomar vitaminas pela manhã', 0, 'alta', 1, NULL, NULL, '2026-03-02 09:00:00', '2026-03-02 09:00:00'),
(12, 14, 12, 'Fazer caminhada', 'Caminhada de 45 minutos no parque', 0, 'media', 2, '2026-03-04 07:00:00', NULL, '2026-03-02 08:00:00', '2026-03-02 08:00:00'),
(13, 14, 13, 'Preparar refeição saudável', 'Fazer almoço com proteína e vegetais', 1, 'media', 1, '2026-03-02 12:00:00', '2026-03-02 12:30:00', '2026-03-02 11:00:00', '2026-03-02 12:30:00'),
(14, 14, 14, 'Agendar consulta com cardiologista', 'Fazer check-up anual', 0, 'alta', 3, '2026-03-20 09:00:00', NULL, '2026-03-02 14:00:00', '2026-03-02 14:00:00'),
(15, 14, 15, 'Meditar por 20 minutos', 'Praticar meditação matinal', 0, 'baixa', 2, NULL, NULL, '2026-03-02 06:30:00', '2026-03-02 06:30:00'),
(16, 15, 16, 'Estudar para prova de álgebra', 'Revisar capítulos 5 a 8', 0, 'alta', 1, '2026-03-15 17:00:00', NULL, '2026-03-02 18:00:00', '2026-03-02 18:00:00'),
(17, 15, 17, 'Fazer lista de exercícios de física', 'Resolver 20 questões do capítulo 3', 0, 'alta', 2, '2026-03-07 20:00:00', NULL, '2026-03-02 18:30:00', '2026-03-02 18:30:00'),
(18, 15, 18, 'Escrever trabalho sobre Historia do Brasil', 'Tema: Período colonial - 5 páginas', 0, 'alta', 1, '2026-03-12 18:00:00', NULL, '2026-03-02 17:00:00', '2026-03-02 17:00:00'),
(19, 15, 19, 'Ler livro de português', 'Ler capítulos 7 e 8', 0, 'media', 3, '2026-03-20 18:00:00', NULL, '2026-03-02 19:00:00', '2026-03-02 19:00:00'),
(20, 15, 20, 'Pesquisar sobre mudanças climáticas', 'Colher informações para projeto', 0, 'media', 2, '2026-03-18 16:00:00', NULL, '2026-03-02 19:30:00', '2026-03-02 19:30:00');

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
(1, 'exemplo', '$2a$12$ojqKdUdATriPdrVdLXpog.bWe.LPgVeG2TwLWIqo/LQntx.GhrCae', 'exemplo@email.com', '', '', '2026-02-21 14:09:57', '2026-02-21 09:12:46'),
(2, '', '$2y$10$DZGV1f0UGsBNtGu3aHy7xOw5zIbE9909jisxUUznNcvwDEzeYzDIy', 'teste@teste.com', '', '', '2026-03-02 17:26:14', '2026-02-21 13:59:51'),
(14, 'teste2@teste.com', '$2y$10$odjrnAkbcXmtRPwwV0y65.1ZxRmFgtGKRuGHe70cc9XWsMPXETsNq', 'teste2@teste.com', '', '', NULL, '2026-02-21 23:48:38'),
(15, 'teste3@teste.com', '$2y$10$kMjRneJvY.635Fp6sUywveodvMxU7Z8mR27HbIAaL71sAKbqZxLXe', 'teste3@teste.com', '', '', NULL, '2026-02-21 23:48:48');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Identificador único do feedback';

--
-- AUTO_INCREMENT de tabela `notepads`
--
ALTER TABLE `notepads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Identificador único do bloco de notas';

--
-- AUTO_INCREMENT de tabela `todo_categories`
--
ALTER TABLE `todo_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Identificador único da categoria';

--
-- AUTO_INCREMENT de tabela `todo_lists`
--
ALTER TABLE `todo_lists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Identificador único da tarefa';

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

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
