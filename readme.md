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

## 📞 Suporte

Para dúvidas, consulte a documentação do PHP: https://www.php.net/manual/
