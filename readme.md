# 🚀 API PHP Nativa - Guia de Instalação e Uso

## 📋 Pré-requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Apache com mod_rewrite habilitado

## 🔧 Instalação

### 1. Configure o Banco de Dados

Execute o script SQL fornecido (`database.sql`) no seu MySQL:

```bash
mysql -u root -p < database.sql
```

### 2. Configure as Credenciais

Edite o arquivo `api/config/database.php` com suas credenciais:

```php
return [
    'host' => 'localhost',
    'dbname' => 'api_php',
    'username' => 'seu_usuario',
    'password' => 'sua_senha',
    // ...
];
```

### 3. Configure o Apache

Certifique-se de que o `mod_rewrite` está habilitado:

```bash
sudo a2enmod rewrite
sudo service apache2 restart
```

### 4. Ajuste Permissões

```bash
chmod -R 755 /caminho/para/api
```

## 🧪 Testando a API

### 1. Verificar se está funcionando

```bash
curl http://localhost/api/
```

**Resposta esperada:**

```json
{
    "message": "API funcionando!",
    "version": "1.0.0"
}
```

### 2. Registrar um novo usuário

```bash
curl -X POST http://localhost/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "login": "joao",
    "senha": "senha123",
    "email": "joao@email.com"
  }'
```

### 3. Fazer Login

```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "login": "exemplo",
    "senha": "1234"
  }'
```

**Resposta:**

```json
{
    "token": "abc123xyz...",
    "expires_in": 3600,
    "user": {
        "id": 1,
        "login": "exemplo",
        "email": "exemplo@email.com"
    }
}
```

### 4. Acessar Rota Protegida

```bash
curl -X GET http://localhost/api/auth/me \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

### 5. Fazer Logout

```bash
curl -X POST http://localhost/api/auth/logout \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

## 🔐 Segurança Implementada

✅ **Senhas:** Hashing com `password_hash()` e `password_verify()`  
✅ **Tokens:** Gerados com `random_bytes()` + `hash_hmac()`  
✅ **SQL Injection:** Prevenido com prepared statements (PDO)  
✅ **Headers de Segurança:** X-Content-Type-Options, X-Frame-Options, etc.  
✅ **Validação de Tokens:** Verificação de expiração e existência

## 📁 Estrutura de Endpoints

| Método | Endpoint         | Autenticação | Descrição                    |
| ------ | ---------------- | ------------ | ---------------------------- |
| GET    | `/`              | ❌           | Informações da API           |
| POST   | `/auth/register` | ❌           | Registrar usuário            |
| POST   | `/auth/login`    | ❌           | Fazer login                  |
| POST   | `/auth/logout`   | ✅           | Fazer logout                 |
| GET    | `/auth/me`       | ✅           | Dados do usuário autenticado |
| GET    | `/protected`     | ✅           | Exemplo de rota protegida    |
| GET    | `/users/:id`     | ✅           | Buscar usuário por ID        |

## 🔧 Como Adicionar Novos Endpoints

### 1. Criar um Controller

```php
// api/controllers/ProductController.php
class ProductController {
    public function list(Request $request) {
        Auth::requireAuth($request); // Se precisar autenticação

        // Sua lógica aqui
        Response::json(['products' => []]);
    }
}
```

### 2. Registrar a Rota

No arquivo `api/index.php`, adicione:

```php
$router->get('/products', 'ProductController@list');
```

## 🧪 Testes Unitários

### Executar os testes

```bash
# Rodar todos os testes
./vendor/bin/phpunit

# Rodar com saída detalhada
./vendor/bin/phpunit --testdox

# Rodar um arquivo de teste específico
./vendor/bin/phpunit tests/TaskCategoryTest.php

# Rodar um método de teste específico
./vendor/bin/phpunit --filter testFindAllByUserId
```

> Os testes também rodam automaticamente a cada `git commit` via Husky. Se algum teste falhar, o commit é bloqueado.

---

### Como escrever um teste unitário

Os testes ficam na pasta `tests/` e devem seguir o padrão `NomeDaClasseTest.php`. O arquivo `tests/bootstrap.php` carrega automaticamente todas as classes do projeto.

#### Estrutura básica

```php
<?php

use PHPUnit\Framework\TestCase;

class MeuModelTest extends TestCase
{
    private $mockDb;
    private $mockStmt;

    // Executado antes de cada teste
    protected function setUp(): void
    {
        $this->mockStmt = $this->createMock(PDOStatement::class);
        $this->mockDb   = $this->createMock(PDO::class);
        $this->mockDb->method('prepare')->willReturn($this->mockStmt);

        // Injeta o banco de dados falso na classe que será testada
        \MeuModel::setDatabase($this->mockDb);
    }

    public function testAlgumComportamento(): void
    {
        // 1. Arrange — configure o que o mock deve retornar
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['user_id' => 1])
            ->willReturn(true);

        $this->mockStmt->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'Exemplo'],
        ]);

        // 2. Act — chame o método que está sendo testado
        $result = \MeuModel::findAllByUserId(1);

        // 3. Assert — verifique o resultado
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('Exemplo', $result[0]['name']);
    }
}
```

#### Assertions mais usadas

| Assertion | Descrição |
|-----------|-----------|
| `$this->assertEquals($esperado, $real)` | Verifica igualdade |
| `$this->assertIsArray($valor)` | Verifica se é array |
| `$this->assertCount($n, $array)` | Verifica tamanho do array |
| `$this->assertNull($valor)` | Verifica se é null |
| `$this->assertTrue($valor)` | Verifica se é true |
| `$this->assertInstanceOf(Classe::class, $obj)` | Verifica tipo do objeto |

#### Adicionando suporte a `setDatabase()` num Model

Para que o mock de PDO funcione, o model precisa expor um método estático para injeção:

```php
class MeuModel
{
    private static PDO $db;

    public static function setDatabase(PDO $db): void
    {
        self::$db = $db;
    }

    // ... restante dos métodos
}
```

---

## 🚀 Próximos Passos

- Adicionar rate limiting
- Implementar refresh tokens
- Adicionar logs de auditoria
- Criar endpoints CRUD completos
- Implementar paginação
- Adicionar validação de campos avançada

## 🐛 Troubleshooting

### Erro 500 - Internal Server Error

- Verifique se o mod_rewrite está ativo
- Confira as credenciais do banco em `config/database.php`
- Veja os logs do Apache: `tail -f /var/log/apache2/error.log`

### Tokens não funcionam

- Verifique se a tabela `auth_tokens` existe
- Confirme que o header `Authorization: Bearer TOKEN` está correto

### Rotas não encontradas

- Certifique-se de que o arquivo `.htaccess` está na raiz da pasta `api`
- Verifique se o `AllowOverride All` está configurado no Apache

