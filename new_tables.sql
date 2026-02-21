-- =====================================================
-- SCRIPT SQL - APLICATIVO DE TODO
-- =====================================================
-- Tabelas para sistema de notas, tarefas e feedback
-- Relacionadas com a tabela users existente
-- =====================================================

-- =====================================================
-- TABELA: notepads (Blocos de Notas)
-- =====================================================
-- Permite que cada usuário crie múltiplos blocos de notas
-- =====================================================

CREATE TABLE IF NOT EXISTS notepads (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único do bloco de notas',
    user_id INT NOT NULL COMMENT 'ID do usuário dono do bloco de notas',
    title VARCHAR(255) NOT NULL COMMENT 'Título do bloco de notas',
    content TEXT NULL COMMENT 'Conteúdo do bloco de notas',
    color VARCHAR(7) DEFAULT '#ffffff' COMMENT 'Cor do bloco de notas (formato hexadecimal)',
    is_favorite BOOLEAN DEFAULT FALSE COMMENT 'Indica se o bloco é favorito',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora de criação do bloco',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data/hora da última atualização',
    
    -- Índices para performance
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_is_favorite (is_favorite),
    
    -- Relacionamento com tabela users
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Armazena os blocos de notas dos usuários';


-- =====================================================
-- TABELA: todo_categories (Categorias/Conjuntos de Tarefas)
-- =====================================================
-- Permite que o usuário agrupe tarefas em categorias/listas
-- Exemplo: "Trabalho", "Pessoal", "Compras", "Estudos"
-- =====================================================

CREATE TABLE IF NOT EXISTS todo_categories (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único da categoria',
    user_id INT NOT NULL COMMENT 'ID do usuário dono da categoria',
    name VARCHAR(100) NOT NULL COMMENT 'Nome da categoria (ex: Trabalho, Pessoal)',
    color VARCHAR(7) DEFAULT '#3b82f6' COMMENT 'Cor da categoria (formato hexadecimal)',
    icon VARCHAR(50) DEFAULT '📋' COMMENT 'Ícone ou emoji da categoria',
    display_order INT DEFAULT 0 COMMENT 'Ordem de exibição definida pelo usuário',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora de criação',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data/hora da última atualização',
    
    -- Índices para performance
    INDEX idx_user_id (user_id),
    INDEX idx_display_order (display_order),
    
    -- Relacionamento com tabela users
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Categorias/conjuntos de listas de tarefas';


-- =====================================================
-- TABELA: todo_lists (ATUALIZADA)
-- =====================================================
-- Agora com referência à categoria
-- =====================================================

CREATE TABLE IF NOT EXISTS todo_lists (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único da tarefa',
    user_id INT NOT NULL COMMENT 'ID do usuário dono da tarefa',
    category_id INT NULL COMMENT 'ID da categoria (NULL = sem categoria)',
    title VARCHAR(255) NOT NULL COMMENT 'Título/descrição da tarefa',
    description TEXT NULL COMMENT 'Descrição detalhada da tarefa',
    is_completed BOOLEAN DEFAULT FALSE COMMENT 'Indica se a tarefa foi concluída',
    priority ENUM('baixa', 'media', 'alta') DEFAULT 'media' COMMENT 'Prioridade da tarefa',
    display_order INT DEFAULT 0 COMMENT 'Ordem de exibição dentro da categoria',
    due_date DATETIME NULL COMMENT 'Data/hora de vencimento da tarefa',
    completed_at DATETIME NULL COMMENT 'Data/hora em que a tarefa foi concluída',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora de criação da tarefa',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data/hora da última atualização',
    
    -- Índices para performance
    INDEX idx_user_id (user_id),
    INDEX idx_category_id (category_id),
    INDEX idx_is_completed (is_completed),
    INDEX idx_priority (priority),
    INDEX idx_display_order (display_order),
    INDEX idx_due_date (due_date),
    INDEX idx_created_at (created_at),
    
    -- Relacionamentos
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES todo_categories(id) ON DELETE SET NULL
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Armazena as tarefas (to-dos) dos usuários';


-- =====================================================
-- TABELA: feedback (Avaliações e Sugestões)
-- =====================================================
-- Permite que usuários enviem avaliações e sugestões
-- =====================================================

CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único do feedback',
    user_id INT NOT NULL COMMENT 'ID do usuário que enviou o feedback',
    rating TINYINT NOT NULL COMMENT 'Nota de avaliação (1 a 5)',
    suggestion TEXT NULL COMMENT 'Campo livre para sugestões do usuário',
    status ENUM('pendente', 'analisado', 'implementado', 'descartado') DEFAULT 'pendente' COMMENT 'Status da análise do feedback',
    admin_notes TEXT NULL COMMENT 'Notas do administrador sobre o feedback',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora em que o feedback foi enviado',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data/hora da última atualização',
    
    -- Índices para performance
    INDEX idx_user_id (user_id),
    INDEX idx_rating (rating),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    
    -- Constraint para validar rating entre 1 e 5
    CHECK (rating >= 1 AND rating <= 5),
    
    -- Relacionamento com tabela users
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Armazena avaliações e sugestões dos usuários';


-- =====================================================
-- EXEMPLOS DE QUERIES ÚTEIS
-- =====================================================

-- Buscar todos os blocos de notas de um usuário
-- SELECT * FROM notepads WHERE user_id = 1 ORDER BY created_at DESC;

-- Buscar tarefas pendentes de um usuário ordenadas por prioridade
-- SELECT * FROM todo_lists 
-- WHERE user_id = 1 AND is_completed = FALSE 
-- ORDER BY FIELD(priority, 'alta', 'media', 'baixa'), due_date ASC;

-- Buscar tarefas vencidas de um usuário
-- SELECT * FROM todo_lists 
-- WHERE user_id = 1 AND is_completed = FALSE AND due_date < NOW();

-- Estatísticas de feedback
-- SELECT rating, COUNT(*) as total 
-- FROM feedback 
-- GROUP BY rating 
-- ORDER BY rating DESC;

-- Média de avaliação geral
-- SELECT AVG(rating) as media_avaliacoes FROM feedback;

-- Feedback pendente de análise
-- SELECT f.*, u.login as usuario 
-- FROM feedback f 
-- JOIN users u ON f.user_id = u.id 
-- WHERE f.status = 'pendente' 
-- ORDER BY f.created_at DESC;

-- =====================================================
-- QUERIES ÚTEIS ATUALIZADAS
-- =====================================================

-- 1. Buscar todas as categorias de um usuário (ordenadas)
-- SELECT * FROM todo_categories 
-- WHERE user_id = 1 
-- ORDER BY display_order ASC, created_at ASC;

-- 2. Buscar tarefas de uma categoria específica (ordenadas)
-- SELECT * FROM todo_lists 
-- WHERE user_id = 1 AND category_id = 5 
-- ORDER BY is_completed ASC, display_order ASC, created_at DESC;

-- 3. Buscar TODAS as tarefas agrupadas por categoria
-- SELECT 
--     c.id as category_id,
--     c.name as category_name,
--     c.color as category_color,
--     t.id as task_id,
--     t.title,
--     t.is_completed,
--     t.priority,
--     t.due_date
-- FROM todo_categories c
-- LEFT JOIN todo_lists t ON c.id = t.category_id AND t.user_id = 1
-- WHERE c.user_id = 1
-- ORDER BY c.display_order ASC, t.display_order ASC;

-- 4. Contar tarefas por categoria
-- SELECT 
--     c.id,
--     c.name,
--     COUNT(t.id) as total_tasks,
--     SUM(CASE WHEN t.is_completed = TRUE THEN 1 ELSE 0 END) as completed_tasks
-- FROM todo_categories c
-- LEFT JOIN todo_lists t ON c.id = t.category_id
-- WHERE c.user_id = 1
-- GROUP BY c.id, c.name
-- ORDER BY c.display_order ASC;

-- 5. Reordenar tarefas dentro de uma categoria (drag and drop)
-- UPDATE todo_lists SET display_order = 1 WHERE id = 10;
-- UPDATE todo_lists SET display_order = 2 WHERE id = 15;
-- UPDATE todo_lists SET display_order = 3 WHERE id = 8;
