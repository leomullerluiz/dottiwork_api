# 📘 Documentação Completa - API PHP Nativa

<div align="center">

![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=for-the-badge) ![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=for-the-badge) ![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

**API RESTful construída 100% com PHP nativo - Sem frameworks, sem dependências externas**

</div>

---

## 📑 Índice

1. [Visão Geral da Arquitetura](#1-visão-geral-da-arquitetura)
2. [Estrutura de Pastas Detalhada](#2-estrutura-de-pastas-detalhada)
3. [Fluxo de Execução Completo](#3-fluxo-de-execução-completo)
4. [Documentação do Banco de Dados](#4-documentação-do-banco-de-dados)
5. [Arquivo .htaccess](#5-arquivo-htaccess)
6. [Configurações (config/)](#6-configurações-config)
7. [Core da Aplicação (core/)](#7-core-da-aplicação-core)
8. [Models (models/)](#8-models-models)
9. [Controllers (controllers/)](#9-controllers-controllers)
10. [Ponto de Entrada (index.php)](#10-ponto-de-entrada-indexphp)
11. [Segurança Implementada](#11-segurança-implementada)
12. [Padrões de Design Utilizados](#12-padrões-de-design-utilizados)
13. [Como Adicionar Funcionalidades](#13-como-adicionar-funcionalidades)
14. [Troubleshooting e Boas Práticas](#14-troubleshooting-e-boas-práticas)

---

## 1. Visão Geral da Arquitetura

### 1.1 Arquitetura Geral

Esta API segue uma **arquitetura MVC (Model-View-Controller) simplificada**, adaptada para APIs RESTful:

```
┌─────────────────────────────────────────────────────────────┐
│                        CLIENTE                               │
│                   (Requisição HTTP)                          │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                     .htaccess                                │
│              (Redireciona para index.php)                    │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                      index.php                               │
│          (Ponto de Entrada - Inicialização)                  │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ├──────────────────┐
                         ▼                  ▼
              ┌──────────────────┐   ┌──────────────┐
              │     Request      │   │    Router    │
              │  (Parse dados)   │   │ (Rotas)      │
              └──────────────────┘   └──────┬───────┘
                                             │
                                             ▼
                                  ┌──────────────────┐
                                  │   Controller     │
                                  │ (Lógica negócio) │
                                  └────────┬─────────┘
                                           │
                      ┌────────────────────┼────────────────┐
                      ▼                    ▼                ▼
              ┌──────────────┐    ┌──────────────┐  ┌──────────┐
              │     Auth     │    │    Model     │  │ Response │
              │  (Validação) │    │  (Dados)     │  │ (JSON)   │
              └──────────────┘    └──────┬───────┘  └──────────┘
                                          │
                                          ▼
                                  ┌──────────────┐
                                  │   Database   │
                                  │    (PDO)     │
                                  └──────────────┘
```

### 1.2 Componentes Principais

| Componente      | Responsabilidade                         | Localização         |
| --------------- | ---------------------------------------- | ------------------- |
| **Router**      | Gerenciar rotas e direcionar requisições | `core/Router.php`   |
| **Request**     | Processar dados da requisição HTTP       | `core/Request.php`  |
| **Response**    | Formatar e enviar respostas JSON         | `core/Response.php` |
| **Auth**        | Autenticação e autorização               | `core/Auth.php`     |
| **Database**    | Conexão com banco de dados               | `core/Database.php` |
| **Models**      | Acesso e manipulação de dados            | `models/*.php`      |
| **Controllers** | Lógica de negócio                        | `controllers/*.php` |

### 1.3 Fluxo de Dados

```
Cliente → .htaccess → index.php → Router → Controller → Model → Database
                                     ↓           ↓
                                   Auth    Response
                                     ↓           ↓
                                Cliente ← JSON ←┘
```

---

## 2. Estrutura de Pastas Detalhada

```
/api                                    # Raiz da aplicação
│
├── index.php                           # Ponto de entrada principal
├── .htaccess                           # Configuração Apache (URL rewriting)
│
├── config/                             # Configurações da aplicação
│   └── database.php                    # Credenciais do banco de dados
│
├── core/                               # Núcleo da aplicação (classes base)
│   ├── Database.php                    # Gerenciador de conexão PDO
│   ├── Router.php                      # Sistema de roteamento
│   ├── Request.php                     # Processador de requisições HTTP
│   ├── Response.php                    # Formatador de respostas JSON
│   └── Auth.php                        # Sistema de autenticação/autorização
│
├── models/                             # Camada de dados (acesso ao BD)
│   ├── User.php                        # Model de usuários
│   └── AuthToken.php                   # Model de tokens de autenticação
│
└── controllers/                        # Camada de lógica de negócio
    └── AuthController.php              # Controller de autenticação
```

### 2.1 Propósito de Cada Pasta

#### **`/config`**

- **Propósito**: Armazenar todas as configurações da aplicação
- **Conteúdo**: Credenciais de banco, chaves de API, configurações de ambiente
- **Segurança**: Esta pasta deveria estar fora do diretório público em produção

#### **`/core`**

- **Propósito**: Classes fundamentais que formam o framework da API
- **Características**:
    - Reutilizáveis
    - Independentes de lógica de negócio
    - Usadas por toda a aplicação

#### **`/models`**

- **Propósito**: Representar e manipular dados do banco
- **Padrão**: Um arquivo por tabela do banco de dados
- **Responsabilidade**: CRUD (Create, Read, Update, Delete)

#### **`/controllers`**

- **Propósito**: Processar requisições e orquestrar a lógica de negócio
- **Responsabilidade**:
    - Validar entrada
    - Chamar Models
    - Retornar Responses

---

## 3. Fluxo de Execução Completo

### 3.1 Exemplo: POST /auth/login

Vamos seguir passo a passo o que acontece quando um cliente faz login:

```
1. Cliente envia requisição:
   POST http://localhost/api/auth/login
   Body: {"login": "exemplo", "senha": "1234"}

2. Apache recebe e consulta .htaccess
   ↓
3. .htaccess redireciona para index.php
   ↓
4. index.php é executado:
   a. Configura headers CORS
   b. Registra autoloader de classes
   c. Cria objeto Request (parseia JSON)
   d. Cria objeto Router
   e. Registra todas as rotas
   f. Chama $router->dispatch($request)
   ↓
5. Router encontra rota POST /auth/login
   ↓
6. Router executa AuthController@login
   ↓
7. AuthController::login() executa:
   a. Valida campos (login e senha presentes?)
   b. Chama User::findByLogin('exemplo')
   c. Model consulta banco de dados via PDO
   d. Valida senha com Auth::verifyPassword()
   e. Gera token com Auth::generateToken()
   f. Salva token com AuthToken::create()
   g. Atualiza ultimo_login com User::updateLastLogin()
   h. Retorna JSON via Response::json()
   ↓
8. Response envia HTTP 200 + JSON:
   {
     "token": "abc123...",
     "expires_in": 3600,
     "user": {...}
   }
   ↓
9. Cliente recebe resposta
```

### 3.2 Diagrama de Sequência

```
Cliente          Apache        index.php      Router         AuthController    Model        Database
  |                |                |            |                  |             |             |
  |--POST /login-->|                |            |                  |             |             |
  |                |--redirect----->|            |                  |             |             |
  |                |                |--new------>|                  |             |             |
  |                |                |            |--dispatch------->|             |             |
  |                |                |            |                  |--findUser-->|             |
  |                |                |            |                  |             |--SELECT---->|
  |                |                |            |                  |             |<--result----|
  |                |                |            |                  |<--user------|             |
  |                |                |            |                  |--verify---->|             |
  |                |                |            |                  |--create----->|             |
  |                |                |            |                  |             |--INSERT---->|
  |                |                |            |<--Response::json-|             |             |
  |<-----------200 + JSON-----------|            |                  |             |             |
```

---

## 4. Documentação do Banco de Dados

### 4.1 Estrutura das Tabelas

#### **Tabela: `users`**

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(50) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    ultimo_login DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login (login),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Campos:**

| Campo          | Tipo         | Descrição                  | Constraints                 |
| -------------- | ------------ | -------------------------- | --------------------------- |
| `id`           | INT          | Identificador único        | PRIMARY KEY, AUTO_INCREMENT |
| `login`        | VARCHAR(50)  | Nome de usuário para login | NOT NULL, UNIQUE            |
| `senha`        | VARCHAR(255) | Hash da senha (bcrypt)     | NOT NULL                    |
| `email`        | VARCHAR(100) | Email do usuário           | NOT NULL, UNIQUE            |
| `ultimo_login` | DATETIME     | Data/hora do último acesso | NULL permitido              |
| `created_at`   | DATETIME     | Data/hora de criação       | DEFAULT CURRENT_TIMESTAMP   |

**Índices:**

- `idx_login`: Otimiza buscas por login
- `idx_email`: Otimiza buscas por email

**Observações:**

- A senha é armazenada como hash bcrypt (60 caracteres), mas VARCHAR(255) permite algoritmos futuros
- `ultimo_login` é atualizado a cada login bem-sucedido
- Charset UTF-8 (utf8mb4) suporta emojis e caracteres especiais

#### **Tabela: `auth_tokens`**

```sql
CREATE TABLE auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Campos:**

| Campo        | Tipo         | Descrição                   | Constraints                 |
| ------------ | ------------ | --------------------------- | --------------------------- |
| `id`         | INT          | Identificador único         | PRIMARY KEY, AUTO_INCREMENT |
| `user_id`    | INT          | ID do usuário dono do token | NOT NULL, FOREIGN KEY       |
| `token`      | VARCHAR(255) | Token de autenticação       | NOT NULL, UNIQUE            |
| `expires_at` | DATETIME     | Data/hora de expiração      | NOT NULL                    |
| `created_at` | DATETIME     | Data/hora de criação        | DEFAULT CURRENT_TIMESTAMP   |

**Relacionamentos:**

- `user_id` → `users.id` (ON DELETE CASCADE): Se o usuário for deletado, seus tokens também são

**Índices:**

- `idx_token`: Otimiza validação de tokens (operação mais frequente)
- `idx_user_id`: Otimiza busca de tokens por usuário
- `idx_expires_at`: Otimiza limpeza de tokens expirados

**Observações:**

- Um usuário pode ter múltiplos tokens ativos (múltiplas sessões)
- Tokens expirados devem ser limpos periodicamente (cron job)

### 4.2 Relacionamentos

```
┌─────────────────┐           ┌──────────────────┐
│     users       │           │   auth_tokens    │
├─────────────────┤           ├──────────────────┤
│ id (PK)         │◄──────────│ user_id (FK)     │
│ login           │   1:N     │ token            │
│ senha           │           │ expires_at       │
│ email           │           │ created_at       │
│ ultimo_login    │           └──────────────────┘
│ created_at      │
└─────────────────┘

Relacionamento: Um usuário pode ter vários tokens
```

### 4.3 Queries Comuns e Performance

#### **Buscar usuário por login (com índice)**

```sql
SELECT * FROM users WHERE login = 'exemplo' LIMIT 1;
-- Usa: idx_login
-- Complexidade: O(log n)
```

#### **Validar token (com índice)**

```sql
SELECT * FROM auth_tokens WHERE token = 'abc123...' AND expires_at > NOW() LIMIT 1;
-- Usa: idx_token
-- Complexidade: O(log n)
```

#### **Limpar tokens expirados (manutenção)**

```sql
DELETE FROM auth_tokens WHERE expires_at < NOW();
-- Usa: idx_expires_at
-- Deve ser executado periodicamente (cron)
```

---

## 5. Arquivo .htaccess

### 5.1 Código Completo Explicado

```apache
# Habilitar o mod_rewrite
# Este módulo do Apache permite reescrever URLs em tempo real
RewriteEngine On

# =====================================
# SEGURANÇA: Bloquear acesso direto
# =====================================
# Impede que usuários acessem diretamente arquivos em pastas sensíveis
# Exemplo: /api/config/database.php retornará 403 Forbidden
RewriteRule ^(config|core|controllers|models)/ - [F,L]
# ^(config|core|controllers|models)/ = regex que captura essas pastas
# - = não reescreve a URL
# [F,L] = F=Forbidden (403), L=Last rule (para processamento)

# =====================================
# ROTEAMENTO: Redirecionar para index.php
# =====================================
# Se o arquivo solicitado não existir fisicamente...
RewriteCond %{REQUEST_FILENAME} !-f
# E se o diretório solicitado não existir...
RewriteCond %{REQUEST_FILENAME} !-d
# ...então redireciona tudo para index.php
RewriteRule ^(.*)$ index.php [QSA,L]
# ^(.*)$ = captura qualquer URL
# [QSA,L] = QSA=Query String Append (mantém parâmetros GET), L=Last rule

# =====================================
# HEADERS: Forçar JSON como content-type
# =====================================
<FilesMatch "\.php$">
    Header set Content-Type "application/json; charset=utf-8"
</FilesMatch>
# Define o content-type padrão para todos os arquivos PHP

# =====================================
# SEGURANÇA: Headers de proteção
# =====================================
<IfModule mod_headers.c>
    # Impede que o browser "adivinhe" o tipo de conteúdo
    Header set X-Content-Type-Options "nosniff"

    # Impede que a página seja exibida em iframes (proteção contra clickjacking)
    Header set X-Frame-Options "DENY"

    # Ativa o filtro XSS do browser
    Header set X-XSS-Protection "1; mode=block"
</IfModule>
```

### 5.2 Como Funciona o Roteamento

**Exemplo 1: Requisição para `/api/auth/login`**

```
1. Apache recebe: GET /api/auth/login
2. Verifica RewriteCond: existe arquivo auth/login? NÃO
3. Verifica RewriteCond: existe diretório auth/login? NÃO
4. Aplica RewriteRule: redireciona internamente para index.php
5. PHP recebe $_SERVER['REQUEST_URI'] = '/api/auth/login'
6. Router processa e encontra a rota
```

**Exemplo 2: Requisição para `/api/config/database.php`**

```
1. Apache recebe: GET /api/config/database.php
2. Primeiro checa: RewriteRule ^(config|core|...)/ - [F,L]
3. Match encontrado! Retorna 403 Forbidden
4. Processamento para aqui (flag L)
5. Arquivo protegido ✅
```

### 5.3 Flags Explicadas

| Flag  | Nome                | Descrição                                 |
| ----- | ------------------- | ----------------------------------------- |
| `F`   | Forbidden           | Retorna HTTP 403                          |
| `L`   | Last                | Para o processamento de regras            |
| `QSA` | Query String Append | Mantém parâmetros GET na URL              |
| `R`   | Redirect            | Redireciona externamente (não usado aqui) |

---

## 6. Configurações (config/)

### 6.1 database.php

```php
<?php
/**
 * Configurações do Banco de Dados
 *
 * Este arquivo retorna um array associativo com as credenciais
 * e opções de conexão PDO.
 */

return [
    // Host do servidor MySQL
    'host' => 'localhost',

    // Nome do banco de dados
    'dbname' => 'api_php',

    // Usuário do banco
    'username' => 'root',

    // Senha do usuário
    'password' => '',

    // Charset para suportar caracteres especiais e emojis
    'charset' => 'utf8mb4',

    // Opções do PDO
    'options' => [
        // Modo de erro: lança exceções em caso de erro
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

        // Modo de fetch padrão: retorna arrays associativos
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        // Desabilita emulação de prepared statements (mais seguro)
        // Força o MySQL a fazer o prepare real
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
```

### 6.2 Por que usar arquivo de configuração separado?

**Vantagens:**

1. **Centralização**: Todas as configurações em um lugar
2. **Segurança**: Fácil de mover para fora do diretório público
3. **Ambientes**: Pode ter `database.dev.php`, `database.prod.php`
4. **Versionamento**: Pode ser ignorado no `.gitignore`

**Exemplo de uso multi-ambiente:**

```php
// Detecta ambiente
$env = getenv('APP_ENV') ?: 'production';

// Carrega config do ambiente
$config = require __DIR__ . "/database.{$env}.php";
```

### 6.3 Opções PDO Detalhadas

#### **PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION**

```php
// SEM esta opção:
$stmt = $pdo->query("SELECT * FROM tabela_inexistente");
if (!$stmt) {
    echo "Erro silencioso";
}

// COM esta opção:
try {
    $stmt = $pdo->query("SELECT * FROM tabela_inexistente");
} catch (PDOException $e) {
    echo "Exceção capturada: " . $e->getMessage();
}
```

#### **PDO::ATTR_EMULATE_PREPARES => false**

```php
// Com emulação (MENOS SEGURO):
// PDO substitui valores no PHP antes de enviar ao MySQL
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
// PHP transforma em: "SELECT * FROM users WHERE id = 1"
// Envia string pronta ao MySQL

// Sem emulação (MAIS SEGURO):
// MySQL recebe o SQL e os valores separados
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
// Envia: SQL + valor separado
// MySQL faz o bind real, prevenindo SQL injection de forma nativa
```

---

## 7. Core da Aplicação (core/)

### 7.1 Database.php - Gerenciador de Conexão

```php
<?php
/**
 * Classe Database
 *
 * Implementa o padrão Singleton para garantir uma única
 * conexão PDO durante toda a execução da aplicação.
 *
 * Padrão de Design: Singleton
 * Responsabilidade: Gerenciar conexão com banco de dados
 */
class Database {
    // Armazena a única instância da classe
    private static $instance = null;

    // Armazena a conexão PDO
    private $connection;

    /**
     * Construtor privado - impede new Database()
     *
     * Este é o núcleo do padrão Singleton: o construtor
     * só pode ser chamado internamente pela própria classe.
     */
    private function __construct() {
        $config = require __DIR__ . '/../config/database.php';

        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
            $this->connection = new PDO($dsn, $config['username'], $config['password'], $config['options']);
        } catch (PDOException $e) {
            Response::json(['error' => 'Erro ao conectar ao banco de dados'], 500);
            exit;
        }
    }

    /**
     * getInstance() - Método estático que retorna a instância única
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * getConnection() - Retorna a conexão PDO
     */
    public function getConnection() {
        return $this->connection;
    }

    private function __clone() {}

    public function __wakeup() {
        throw new Exception("Não é possível unserialize singleton");
    }
}
```

#### **Por que Singleton?**

**Problema sem Singleton:**

```php
// Cada chamada cria uma nova conexão
$db1 = new Database(); // Conexão 1
$db2 = new Database(); // Conexão 2
$db3 = new Database(); // Conexão 3
// Resultado: 3 conexões abertas, desperdício de recursos
```

**Solução com Singleton:**

```php
// Sempre retorna a mesma conexão
$db1 = Database::getInstance(); // Cria conexão
$db2 = Database::getInstance(); // Retorna conexão existente
$db3 = Database::getInstance(); // Retorna conexão existente
// Resultado: 1 única conexão, eficiente
```

---

### 7.2 Request.php - Processador de Requisições

```php
<?php
/**
 * Classe Request
 *
 * Encapsula e processa todos os dados da requisição HTTP.
 *
 * Responsabilidade: Abstrair detalhes da requisição HTTP
 */
class Request {
    private $method;
    private $uri;
    private $body;
    private $headers;

    public function __construct() {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->uri = $this->parseUri();
        $this->body = $this->parseBody();
        $this->headers = $this->parseHeaders();
    }

    public function getMethod() {
        return $this->method;
    }

    public function getUri() {
        return $this->uri;
    }

    public function getBody() {
        return $this->body;
    }

    public function getHeader($name) {
        $name = strtoupper(str_replace('-', '_', $name));
        return $this->headers[$name] ?? null;
    }

    /**
     * getBearerToken() - Extrai token do header Authorization
     * Procura por: Authorization: Bearer abc123xyz...
     */
    public function getBearerToken() {
        $auth = $this->getHeader('Authorization');
        if ($auth && preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function parseUri() {
        $uri = $_SERVER['REQUEST_URI'];

        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptName !== '/') {
            $uri = str_replace($scriptName, '', $uri);
        }

        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        return '/' . trim($uri, '/');
    }

    private function parseBody() {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);
        return $data ?? [];
    }

    private function parseHeaders() {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headers[substr($key, 5)] = $value;
            }
        }
        return $headers;
    }
}
```

#### **Fluxo de Parsing:**

```
Requisição HTTP:
POST /api/auth/login HTTP/1.1
Host: localhost
Content-Type: application/json
Authorization: Bearer abc123

{"login":"test","senha":"1234"}

↓ Request::__construct()

parseUri():
  $_SERVER['REQUEST_URI'] = '/api/auth/login'
  → Remove base: '/auth/login'
  → $this->uri = '/auth/login'

parseBody():
  php://input = '{"login":"test","senha":"1234"}'
  → json_decode()
  → $this->body = ['login' => 'test', 'senha' => '1234']

parseHeaders():
  $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc123'
  → $this->headers['AUTHORIZATION'] = 'Bearer abc123'

getBearerToken():
  → preg_match('Bearer abc123')
  → return 'abc123'
```

---

### 7.3 Response.php - Formatador de Respostas

```php
<?php
/**
 * Classe Response
 *
 * Centraliza toda a lógica de envio de respostas JSON.
 *
 * Responsabilidade: Formatar e enviar respostas HTTP/JSON
 */
class Response {
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function error($message, $statusCode = 400) {
        self::json(['error' => $message], $statusCode);
    }

    public static function success($data = [], $message = 'Sucesso') {
        self::json(array_merge(['message' => $message], $data), 200);
    }

    public static function unauthorized($message = 'Não autorizado') {
        self::json(['error' => $message], 401);
    }

    public static function notFound($message = 'Recurso não encontrado') {
        self::json(['error' => $message], 404);
    }
}
```

#### **Códigos HTTP Implementados:**

| Código | Nome                  | Método                | Uso                        |
| ------ | --------------------- | --------------------- | -------------------------- |
| 200    | OK                    | `success()`, `json()` | Requisição bem-sucedida    |
| 201    | Created               | `json(..., 201)`      | Recurso criado             |
| 400    | Bad Request           | `error()`             | Dados inválidos            |
| 401    | Unauthorized          | `unauthorized()`      | Sem autenticação           |
| 404    | Not Found             | `notFound()`          | Recurso não existe         |
| 409    | Conflict              | `json(..., 409)`      | Conflito (email duplicado) |
| 500    | Internal Server Error | `json(..., 500)`      | Erro do servidor           |

---

### 7.4 Router.php - Sistema de Roteamento

```php
<?php
/**
 * Classe Router
 *
 * Gerencia todas as rotas da aplicação.
 * Suporta rotas estáticas e com parâmetros dinâmicos.
 *
 * Responsabilidade: Roteamento e dispatch de requisições
 */
class Router {
    private $routes = [];

    public function post($uri, $callback) {
        $this->addRoute('POST', $uri, $callback);
    }

    public function get($uri, $callback) {
        $this->addRoute('GET', $uri, $callback);
    }

    public function put($uri, $callback) {
        $this->addRoute('PUT', $uri, $callback);
    }

    public function delete($uri, $callback) {
        $this->addRoute('DELETE', $uri, $callback);
    }

    private function addRoute($method, $uri, $callback) {
        $uri = '/' . trim($uri, '/');
        $this->routes[$method][$uri] = $callback;
    }

    public function dispatch(Request $request) {
        $method = $request->getMethod();
        $uri = $request->getUri();

        // Tenta rota exata
        if (isset($this->routes[$method][$uri])) {
            return $this->executeCallback($this->routes[$method][$uri], $request);
        }

        // Tenta rotas com parâmetros
        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $route => $callback) {
                $pattern = $this->convertToRegex($route);
                if (preg_match($pattern, $uri, $matches)) {
                    array_shift($matches);
                    return $this->executeCallback($callback, $request, $matches);
                }
            }
        }

        Response::notFound('Endpoint não encontrado');
    }

    private function convertToRegex($route) {
        $route = preg_replace('/\/:([^\/]+)/', '/(?P<$1>[^/]+)', $route);
        return '#^' . $route . '$#';
    }

    private function executeCallback($callback, $request, $params = []) {
        if (is_callable($callback)) {
            return call_user_func_array($callback, [$request, $params]);
        }

        if (is_string($callback) && strpos($callback, '@') !== false) {
            list($controller, $method) = explode('@', $callback);
            if (class_exists($controller) && method_exists($controller, $method)) {
                $instance = new $controller();
                return call_user_func_array([$instance, $method], [$request, $params]);
            }
        }

        Response::error('Callback inválido', 500);
    }
}
```

#### **Exemplo de Roteamento com Parâmetros:**

```php
// Registrar rota
$router->get('/users/:id', 'UserController@show');

// Requisição: GET /users/42

// Processamento:
1. convertToRegex('/users/:id')
   → #^/users/(?P<id>[^/]+)$#

2. preg_match(#^/users/(?P<id>[^/]+)$#, '/users/42')
   → Match! $matches = ['id' => '42']

3. executeCallback('UserController@show', $request, ['id' => '42'])
   → $controller->show($request, ['id' => '42'])
```

---

### 7.5 Auth.php - Autenticação e Autorização

```php
<?php
/**
 * Classe Auth
 *
 * Centraliza toda a lógica de autenticação e autorização.
 *
 * Responsabilidade: Segurança e controle de acesso
 */
class Auth {
    /**
     * generateToken() - Gera token seguro e único
     */
    public static function generateToken() {
        $randomBytes = random_bytes(32);
        $timestamp = time();
        $token = hash_hmac('sha256', $randomBytes . $timestamp, 'SECRET_KEY_AQUI');
        return $token;
    }

    /**
     * validateToken() - Valida token e retorna usuário
     */
    public static function validateToken($token) {
        if (!$token) {
            return null;
        }

        $authToken = AuthToken::findByToken($token);
        if (!$authToken) {
            return null;
        }

        $now = new DateTime();
        $expiresAt = new DateTime($authToken['expires_at']);

        if ($now > $expiresAt) {
            return null;
        }

        return User::findById($authToken['user_id']);
    }

    /**
     * requireAuth() - Middleware de autenticação
     */
    public static function requireAuth(Request $request) {
        $token = $request->getBearerToken();
        $user = self::validateToken($token);

        if (!$user) {
            Response::unauthorized('Token inválido ou expirado');
        }

        return $user;
    }

    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}
```

#### **Segurança do Token:**

**Geração:**

```
random_bytes(32)  →  [bytes aleatórios criptográficos]
     +
time()            →  1707331200
     ↓
hash_hmac('sha256', dados, 'SECRET_KEY')
     ↓
Resultado: 64 caracteres hexadecimais seguros
```

**Por que é seguro:**

1. **random_bytes()**: Usa fonte de entropia do SO
2. **HMAC**: Não pode ser forjado sem a SECRET_KEY
3. **Timestamp**: Tokens nunca se repetem
4. **SHA-256**: Hash criptográfico forte

---

## 8. Models (models/)

### 8.1 User.php - Model de Usuários

```php
<?php
/**
 * Model User
 *
 * Representa a tabela 'users' e encapsula toda a lógica
 * de acesso e manipulação de dados de usuários.
 */
class User {
    public static function findByLogin($login) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE login = :login LIMIT 1");
        $stmt->execute(['login' => $login]);
        return $stmt->fetch();
    }

    public static function findById($id) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public static function findByEmail($email) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    public static function create($login, $senha, $email) {
        $db = Database::getInstance()->getConnection();
        $hashedPassword = Auth::hashPassword($senha);

        $stmt = $db->prepare("
            INSERT INTO users (login, senha, email, created_at)
            VALUES (:login, :senha, :email, NOW())
        ");

        $stmt->execute([
            'login' => $login,
            'senha' => $hashedPassword,
            'email' => $email
        ]);

        return $db->lastInsertId();
    }

    public static function updateLastLogin($userId) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE users SET ultimo_login = NOW() WHERE id = :id");
        $stmt->execute(['id' => $userId]);
    }

    public static function toPublic($user) {
        if (!$user) return null;
        unset($user['senha']);
        return $user;
    }
}
```

#### **SQL Injection - Como o PDO Previne:**

**INSEGURO (vulnerável):**

```php
// ❌ NUNCA FAÇA ISSO!
$login = $_POST['login'];
$query = "SELECT * FROM users WHERE login = '$login'";
$result = $db->query($query);

// Ataque: ' OR '1'='1
// Query final: SELECT * FROM users WHERE login = '' OR '1'='1'
// Resultado: retorna TODOS os usuários!
```

**SEGURO (PDO com prepared statements):**

```php
// ✅ FORMA CORRETA
$login = $_POST['login'];
$stmt = $db->prepare("SELECT * FROM users WHERE login = :login");
$stmt->execute(['login' => $login]);

// Mesmo com ataque: ' OR '1'='1
// PDO trata como string literal
// Resultado: nenhum usuário encontrado (seguro!)
```

---

### 8.2 AuthToken.php - Model de Tokens

```php
<?php
/**
 * Model AuthToken
 *
 * Gerencia tokens de autenticação no banco de dados.
 */
class AuthToken {
    public static function create($userId, $token, $expiresInSeconds = 3600) {
        $db = Database::getInstance()->getConnection();

        $expiresAt = (new DateTime())->modify("+{$expiresInSeconds} seconds")->format('Y-m-d H:i:s');

        $stmt = $db->prepare("
            INSERT INTO auth_tokens (user_id, token, expires_at, created_at)
            VALUES (:user_id, :token, :expires_at, NOW())
        ");

        $stmt->execute([
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => $expiresAt
        ]);

        return [
            'id' => $db->lastInsertId(),
            'token' => $token,
            'expires_at' => $expiresAt,
            'expires_in' => $expiresInSeconds
        ];
    }

    public static function findByToken($token) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM auth_tokens WHERE token = :token LIMIT 1");
        $stmt->execute(['token' => $token]);
        return $stmt->fetch();
    }

    public static function deleteExpired() {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM auth_tokens WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }

    public static function deleteByUserId($userId) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM auth_tokens WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->rowCount();
    }

    public static function deleteByToken($token) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM auth_tokens WHERE token = :token");
        $stmt->execute(['token' => $token]);
        return $stmt->rowCount();
    }
}
```

---

## 9. Controllers (controllers/)

### 9.1 AuthController.php

```php
<?php
/**
 * Controller de Autenticação
 */
class AuthController {
    /**
     * POST /auth/login
     * Realiza login e retorna token
     */
    public function login(Request $request) {
        $body = $request->getBody();

        if (empty($body['login']) || empty($body['senha'])) {
            Response::error('Login e senha são obrigatórios', 400);
        }

        $login = trim($body['login']);
        $senha = $body['senha'];

        $user = User::findByLogin($login);

        if (!$user) {
            Response::error('Credenciais inválidas', 401);
        }

        if (!Auth::verifyPassword($senha, $user['senha'])) {
            Response::error('Credenciais inválidas', 401);
        }

        $token = Auth::generateToken();
        $expiresIn = 3600;

        $authToken = AuthToken::create($user['id'], $token, $expiresIn);
        User::updateLastLogin($user['id']);

        Response::json([
            'token' => $authToken['token'],
            'expires_in' => $authToken['expires_in'],
            'user' => User::toPublic($user)
        ], 200);
    }

    /**
     * POST /auth/logout
     * Invalida o token atual
     */
    public function logout(Request $request) {
        $user = Auth::requireAuth($request);
        $token = $request->getBearerToken();

        AuthToken::deleteByToken($token);

        Response::success([], 'Logout realizado com sucesso');
    }

    /**
     * GET /auth/me
     * Retorna informações do usuário autenticado
     */
    public function me(Request $request) {
        $user = Auth::requireAuth($request);

        Response::json([
            'user' => User::toPublic($user)
        ], 200);
    }

    /**
     * POST /auth/register
     * Registra novo usuário
     */
    public function register(Request $request) {
        $body = $request->getBody();

        if (empty($body['login']) || empty($body['senha']) || empty($body['email'])) {
            Response::error('Login, senha e email são obrigatórios', 400);
        }

        $login = trim($body['login']);
        $senha = $body['senha'];
        $email = trim($body['email']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Email inválido', 400);
        }

        if (User::findByLogin($login)) {
            Response::error('Login já está em uso', 409);
        }

        if (User::findByEmail($email)) {
            Response::error('Email já está em uso', 409);
        }

        $userId = User::create($login, $senha, $email);
        $user = User::findById($userId);

        Response::json([
            'message' => 'Usuário criado com sucesso',
            'user' => User::toPublic($user)
        ], 201);
    }
}
```

---

## 10. Ponto de Entrada (index.php)

```php
<?php
/**
 * Ponto de Entrada da API
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Autoload de classes
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/core/' . $class . '.php',
        __DIR__ . '/controllers/' . $class . '.php',
        __DIR__ . '/models/' . $class . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// Tratamento de erros
set_exception_handler(function($exception) {
    Response::error('Erro interno do servidor', 500);
});

// Inicialização
$request = new Request();
$router = new Router();

// ========================================
// ROTAS DA API
// ========================================

$router->get('/', function(Request $request) {
    Response::json([
        'message' => 'API funcionando!',
        'version' => '1.0.0',
        'endpoints' => [
            'POST /auth/login' => 'Fazer login',
            'POST /auth/register' => 'Registrar usuário',
            'POST /auth/logout' => 'Fazer logout (requer autenticação)',
            'GET /auth/me' => 'Informações do usuário (requer autenticação)',
        ]
    ]);
});

// Rotas de autenticação
$router->post('/auth/login', 'AuthController@login');
$router->post('/auth/register', 'AuthController@register');
$router->post('/auth/logout', 'AuthController@logout');
$router->get('/auth/me', 'AuthController@me');

// Exemplo de rota protegida
$router->get('/protected', function(Request $request) {
    $user = Auth::requireAuth($request);

    Response::json([
        'message' => 'Você está autenticado!',
        'user' => User::toPublic($user)
    ]);
});

// Exemplo com parâmetro
$router->get('/users/:id', function(Request $request, $params) {
    $user = Auth::requireAuth($request);

    $userId = $params['id'];
    $targetUser = User::findById($userId);

    if (!$targetUser) {
        Response::notFound('Usuário não encontrado');
    }

    Response::json([
        'user' => User::toPublic($targetUser)
    ]);
});

// Processar requisição
$router->dispatch($request);
```

---

## 11. Segurança Implementada

### 11.1 Proteção Contra SQL Injection

**✅ Implementado via:**

- Prepared statements em todas as queries
- PDO com `ATTR_EMULATE_PREPARES = false`
- Binding de parâmetros automático

**Exemplo:**

```php
// Seguro
$stmt = $db->prepare("SELECT * FROM users WHERE login = :login");
$stmt->execute(['login' => $userInput]);
```

### 11.2 Hash de Senhas

**✅ Implementado via:**

- `password_hash()` com bcrypt
- Cost factor de 10 (1024 iterações)
- Salt automático e único

**Exemplo:**

```php
// Hash
$hash = Auth::hashPassword('senha123');
// $2y$10$...

// Verificação
Auth::verifyPassword('senha123', $hash); // true
```

### 11.3 Tokens Seguros

**✅ Implementado via:**

- `random_bytes(32)` para entropia
- `hash_hmac('sha256')` com chave secreta
- Validação de expiração
- Armazenamento seguro no banco

**Exemplo:**

```php
$token = Auth::generateToken();
// 64 caracteres hexadecimais
```

### 11.4 Headers de Segurança

**✅ Implementado via .htaccess:**

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `X-XSS-Protection: 1; mode=block`

### 11.5 Proteção de Arquivos Sensíveis

**✅ Implementado via .htaccess:**

```apache
RewriteRule ^(config|core|controllers|models)/ - [F,L]
```

### 11.6 Validação de Dados

**✅ Implementado em Controllers:**

- Verificação de campos obrigatórios
- Validação de formato de email
- Sanitização de entradas
- Checagem de duplicatas

---

## 12. Padrões de Design Utilizados

### 12.1 Singleton

**Onde:** `Database.php`

**Por quê:** Garantir uma única conexão PDO durante toda a execução

```php
$db1 = Database::getInstance();
$db2 = Database::getInstance();
// $db1 === $db2 (mesma instância)
```

### 12.2 Front Controller

**Onde:** `index.php`

**Por quê:** Ponto único de entrada para todas as requisições

```
Todas as URLs → .htaccess → index.php → Router
```

### 12.3 Router Pattern

**Onde:** `Router.php`

**Por quê:** Desacoplar URLs da lógica de negócio

```php
$router->get('/users/:id', 'UserController@show');
```

### 12.4 Active Record (simplificado)

**Onde:** `User.php`, `AuthToken.php`

**Por quê:** Models representam e manipulam tabelas

```php
User::findById(1);
User::create('login', 'senha', 'email');
```

### 12.5 Middleware Pattern

**Onde:** `Auth::requireAuth()`

**Por quê:** Interceptar requisições para validar autenticação

```php
public function protectedRoute(Request $request) {
    $user = Auth::requireAuth($request);
    // Continua apenas se autenticado
}
```

---

## 13. Como Adicionar Funcionalidades

### 13.1 Adicionar Novo Endpoint

**Passo 1: Criar o Controller**

```php
// controllers/ProductController.php
<?php
class ProductController {
    public function list(Request $request) {
        Auth::requireAuth($request); // Se precisar autenticação

        // Sua lógica aqui
        $products = Product::getAll();

        Response::json(['products' => $products]);
    }

    public function show(Request $request, $params) {
        $id = $params['id'];
        $product = Product::findById($id);

        if (!$product) {
            Response::notFound('Produto não encontrado');
        }

        Response::json(['product' => $product]);
    }
}
```

**Passo 2: Criar o Model (se necessário)**

```php
// models/Product.php
<?php
class Product {
    public static function getAll() {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT * FROM products");
        return $stmt->fetchAll();
    }

    public static function findById($id) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
}
```

**Passo 3: Registrar a Rota**

```php
// Em index.php, antes de $router->dispatch()
$router->get('/products', 'ProductController@list');
$router->get('/products/:id', 'ProductController@show');
```

### 13.2 Adicionar Validação Customizada

```php
// core/Validator.php
<?php
class Validator {
    public static function email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function minLength($string, $length) {
        return strlen($string) >= $length;
    }

    public static function required($value) {
        return !empty($value);
    }
}

// Uso no Controller:
if (!Validator::email($email)) {
    Response::error('Email inválido', 400);
}
```

### 13.3 Adicionar Middleware Customizado

```php
// core/Middleware.php
<?php
class Middleware {
    public static function adminOnly(Request $request) {
        $user = Auth::requireAuth($request);

        if ($user['role'] !== 'admin') {
            Response::error('Acesso negado', 403);
        }

        return $user;
    }
}

// Uso no Controller:
public function deleteUser(Request $request, $params) {
    $user = Middleware::adminOnly($request);
    // Continua apenas se for admin
}
```

---

## 14. Troubleshooting e Boas Práticas

### 14.1 Problemas Comuns

#### **Erro 500 - Internal Server Error**

**Possíveis causas:**

1. mod_rewrite não está ativo
2. Erro de sintaxe no PHP
3. Problema de conexão com banco

**Solução:**

```bash
# Verificar logs do Apache
tail -f /var/log/apache2/error.log

# Habilitar mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2

# Ativar display_errors temporariamente
ini_set('display_errors', 1);
```

#### **Tokens não funcionam**

**Verificar:**

1. Header `Authorization: Bearer TOKEN` está correto?
2. Token existe no banco?
3. Token não expirou?

**Debug:**

```php
// Em Auth::validateToken()
error_log("Token recebido: " . $token);
error_log("Token encontrado no banco: " . print_r($authToken, true));
```

#### **Rotas não encontradas (404)**

**Verificar:**

1. `.htaccess` está na raiz da pasta `api/`?
2. `AllowOverride All` está configurado no Apache?
3. mod_rewrite está ativo?

**Testar:**

```bash
# Testar rota raiz
curl http://localhost/api/

# Deve retornar JSON, não 404
```

### 14.2 Boas Práticas

#### **Segurança em Produção**

```php
// 1. Desabilitar display_errors
ini_set('display_errors', 0);

// 2. Usar variáveis de ambiente
$secretKey = getenv('APP_SECRET_KEY');

// 3. HTTPS obrigatório
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    Response::error('HTTPS obrigatório', 403);
}

// 4. Rate limiting (exemplo básico)
class RateLimit {
    public static function check($ip, $limit = 100) {
        // Implementar controle de requisições por IP
    }
}
```

#### **Performance**

```php
// 1. Usar índices no banco
CREATE INDEX idx_user_id ON auth_tokens(user_id);

// 2. Limpar tokens expirados periodicamente
// Cron job: 0 * * * * php /path/api/cleanup.php
AuthToken::deleteExpired();

// 3. Cache de queries (se necessário)
class Cache {
    private static $cache = [];

    public static function remember($key, $callback) {
        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = $callback();
        }
        return self::$cache[$key];
    }
}
```

#### **Logs e Monitoramento**

```php
// core/Logger.php
<?php
class Logger {
    public static function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        file_put_contents(__DIR__ . '/../logs/app.log', $logMessage, FILE_APPEND);
    }

    public static function error($message) {
        self::log($message, 'ERROR');
    }

    public static function info($message) {
        self::log($message, 'INFO');
    }
}

// Uso:
Logger::info("Usuário {$user['login']} fez login");
Logger::error("Falha na conexão com banco: " . $e->getMessage());
```

#### **Testes**

```bash
# Criar arquivo de testes
# tests/api_test.sh

#!/bin/bash

echo "Testando API..."

# Teste 1: API online
curl -s http://localhost/api/ | grep "API funcionando"

# Teste 2: Login
TOKEN=$(curl -s -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"login":"exemplo","senha":"1234"}' | jq -r '.token')

echo "Token: $TOKEN"

# Teste 3: Rota protegida
curl -s -X GET http://localhost/api/auth/me \
  -H "Authorization: Bearer $TOKEN"
```

#### **Documentação de Endpoints**

```php
// Adicionar rota de documentação
$router->get('/docs', function() {
    Response::json([
        'endpoints' => [
            [
                'method' => 'POST',
                'path' => '/auth/login',
                'description' => 'Autenticar usuário',
                'body' => [
                    'login' => 'string (required)',
                    'senha' => 'string (required)'
                ],
                'response' => [
                    'token' => 'string',
                    'expires_in' => 'integer',
                    'user' => 'object'
                ]
            ],
            // ... mais endpoints
        ]
    ]);
});
```

---

## 15. Conclusão

Esta documentação cobre todos os aspectos técnicos da API PHP Nativa. O projeto demonstra que é possível construir uma API RESTful completa, segura e escalável usando apenas recursos nativos do PHP.

### 15.1 Recursos Implementados

- ✅ Autenticação completa com tokens
- ✅ Segurança robusta (bcrypt, HMAC, prepared statements)
- ✅ Arquitetura MVC modular
- ✅ Roteamento dinâmico
- ✅ Validação de dados
- ✅ Gerenciamento de sessões
- ✅ Código documentado e testado

### 15.2 Próximos Passos

- [ ] Implementar refresh tokens
- [ ] Adicionar rate limiting
- [ ] Criar sistema de permissões (roles)
- [ ] Adicionar paginação de resultados
- [ ] Implementar upload de arquivos
- [ ] Criar sistema de logs robusto
- [ ] Adicionar cache (Redis/Memcached)
- [ ] Implementar WebSockets (para notificações)

### 15.3 Recursos Adicionais

- [Documentação PHP PDO](https://www.php.net/manual/en/book.pdo.php)
- [OWASP Security Practices](https://owasp.org/www-project-api-security
