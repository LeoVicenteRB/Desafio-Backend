# Laravel Subadquirer Integration API

Sistema profissional de integração com subadquirentes de pagamento (SubadqA e SubadqB) desenvolvido em Laravel 10+ com PHP 8+.

## 📋 Características

- ✅ Integração com múltiplas subadquirentes usando padrão Strategy/Adapter
- ✅ Processamento de PIX e Saques (Withdraws)
- ✅ Sistema de webhooks simulados com processamento assíncrono
- ✅ Idempotência garantida nos webhooks
- ✅ Autenticação via Laravel Sanctum
- ✅ Testes automatizados (Feature + Unit)
- ✅ Queue system configurável (Redis ou Sync)
- ✅ Logging completo e observabilidade
- ✅ Arquitetura extensível para novas subadquirentes

## 🚀 Instalação

### Pré-requisitos

- PHP 8.1+
- Composer
- MySQL 5.7+
- Redis (opcional, para queues assíncronas)

### Passos

1. **Clone o repositório e instale as dependências:**

```bash
composer install
```

2. **Configure o ambiente:**

```bash
cp .env.example .env
php artisan key:generate
```

3. **Configure o arquivo `.env`:**

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=

# Subadquirentes (URLs dos mocks Postman)
SUBADQA_BASE_URL=https://subadqa.mock
SUBADQA_API_KEY=
SUBADQA_API_SECRET=
SUBADQA_TIMEOUT=30
SUBADQA_MERCHANT_ID=m123

SUBADQB_BASE_URL=https://subadqb.mock
SUBADQB_API_KEY=
SUBADQB_API_SECRET=
SUBADQB_TIMEOUT=30

# Documentação das APIs:
# SubadqA: https://documenter.getpostman.com/view/49994027/2sB3WvMJ8p
# SubadqB: https://documenter.getpostman.com/view/49994027/2sB3WvMJD7

# Queue (use 'redis' para processamento assíncrono ou 'sync' para síncrono)
QUEUE_CONNECTION=sync
# ou
QUEUE_CONNECTION=redis

# Redis (se usar queue com Redis)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

4. **Execute as migrations e seeders:**

```bash
php artisan migrate --seed
```

O seeder criará 3 usuários de teste com tokens de API. Os tokens serão exibidos no console.

5. **Inicie o servidor:**

```bash
php artisan serve
```

6. **Inicie o worker de queue (se usar Redis):**

```bash
php artisan queue:work
```

## 📚 Endpoints da API

### Autenticação

Todos os endpoints (exceto `/api/health` e `/api/login`) requerem autenticação via Bearer Token (Laravel Sanctum).

### Health Check

```bash
GET /api/health
```

### Login

```bash
POST /api/login
Content-Type: application/json

{
  "email": "userA@example.com",
  "password": "password"
}
```

**Resposta de sucesso (200):**

```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "User SubadqA",
      "email": "userA@example.com",
      "subadquirer": "SubadqA"
    },
    "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer"
  }
}
```

### Me (Informações do Usuário)

```bash
GET /api/me
Authorization: Bearer {TOKEN}
```

**Resposta de sucesso (200):**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "User SubadqA",
    "email": "userA@example.com",
    "subadquirer": "SubadqA",
    "created_at": "2025-01-13T10:00:00Z"
  }
}
```

### Logout

```bash
POST /api/logout
Authorization: Bearer {TOKEN}
```

Revoga o token atual do usuário.

### Logout All

```bash
POST /api/logout-all
Authorization: Bearer {TOKEN}
```

Revoga todos os tokens do usuário.

### Criar PIX

```bash
POST /api/pix
Authorization: Bearer {TOKEN}
Content-Type: application/json

{
  "amount": 125.50,
  "reference": "order-123",
  "metadata": {}
}
```

**Resposta de sucesso (201):**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "external_pix_id": "PIX123456789",
    "subadquirer": "SubadqA",
    "amount": 125.50,
    "status": "PROCESSING",
    "payer_name": null,
    "payer_document": null,
    "reference": "order-123",
    "payment_date": null,
    "metadata": {},
    "created_at": "2025-01-13T10:00:00Z",
    "updated_at": "2025-01-13T10:00:00Z"
  }
}
```

### Criar Saque (Withdraw)

```bash
POST /api/withdraw
Authorization: Bearer {TOKEN}
Content-Type: application/json

{
  "amount": 500.00,
  "bank": {
    "bank": "Itaú",
    "agency": "0001",
    "account": "1234567-8"
  },
  "metadata": {}
}
```

**Resposta de sucesso (201):**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "external_withdraw_id": "WDX54321",
    "transaction_id": null,
    "subadquirer": "SubadqB",
    "amount": 500.00,
    "status": "PROCESSING",
    "bank_info": {
      "bank": "Itaú",
      "agency": "0001",
      "account": "1234567-8"
    },
    "requested_at": "2025-01-13T10:00:00Z",
    "completed_at": null,
    "metadata": {},
    "created_at": "2025-01-13T10:00:00Z",
    "updated_at": "2025-01-13T10:00:00Z"
  }
}
```

## 🔄 Simulação de Webhooks

O sistema simula automaticamente o recebimento de webhooks após criar um PIX ou Withdraw. Por padrão, 3 webhooks são enfileirados para cada transação.

### Comando para simular webhooks manualmente

```bash
php artisan simulate:webhooks {pix_id} --type=pix --count=30 --rate=3
```

**Parâmetros:**
- `id`: ID do PIX ou Withdraw
- `--type`: Tipo de transação (`pix` ou `withdraw`)
- `--count`: Número de webhooks a simular (padrão: 10)
- `--rate`: Taxa por segundo (padrão: 3)

**Exemplo:**

```bash
# Simular 30 webhooks para um PIX com ID 1, a uma taxa de 3 por segundo
php artisan simulate:webhooks 1 --type=pix --count=30 --rate=3

# Simular 10 webhooks para um withdraw com ID 2
php artisan simulate:webhooks 2 --type=withdraw --count=10 --rate=3
```

## 🧪 Testes

Execute os testes automatizados:

```bash
php artisan test
```

### Cobertura de testes

- ✅ Criação de PIX (happy path)
- ✅ Criação de PIX com erro da subadquirente
- ✅ Validação de dados de entrada
- ✅ Processamento de webhook de PIX
- ✅ Idempotência de webhooks
- ✅ Criação de Withdraw
- ✅ Processamento de webhook de Withdraw

## 🏗️ Arquitetura

### Estrutura de Pastas

```
app/
├── Adapters/
│   ├── Contracts/
│   │   └── SubadquirerInterface.php
│   ├── SubadqAAdapter.php
│   └── SubadqBAdapter.php
├── DTOs/
│   ├── PixNotificationDTO.php
│   ├── SubadqResponse.php
│   └── WithdrawNotificationDTO.php
├── Events/
│   ├── PixConfirmed.php
│   └── WithdrawCompleted.php
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── HealthController.php
│   │       ├── PixController.php
│   │       └── WithdrawController.php
│   └── Resources/
│       ├── PixResource.php
│       └── WithdrawResource.php
├── Jobs/
│   └── ProcessWebhookJob.php
├── Listeners/
│   ├── SendNotificationOnPixConfirmed.php
│   └── SendNotificationOnWithdrawCompleted.php
├── Models/
│   ├── Pix.php
│   ├── User.php
│   ├── UserSubadquirer.php
│   ├── WebhookLog.php
│   └── Withdraw.php
├── Providers/
│   └── SubadquirerServiceProvider.php
└── Services/
    ├── PixService.php
    ├── SubadquirerService.php
    └── WithdrawService.php
```

### Padrões de Projeto Utilizados

1. **Strategy/Adapter Pattern**: Para abstrair as diferenças entre subadquirentes
2. **Service Layer**: Lógica de negócio isolada dos controllers
3. **DTOs (Data Transfer Objects)**: Para padronizar dados entre camadas
4. **Repository Pattern**: (Opcional, pode ser adicionado)
5. **Factory Pattern**: Para criação de objetos complexos
6. **Dependency Injection**: Via Service Providers

## ➕ Como Adicionar uma Nova Subadquirente

Para adicionar uma nova subadquirente (ex: SubadqC), siga estes passos:

### 1. Criar o Adapter

Crie `app/Adapters/SubadqCAdapter.php` implementando `SubadquirerInterface`:

```php
<?php

namespace App\Adapters;

use App\Adapters\Contracts\SubadquirerInterface;
use App\DTOs\PixNotificationDTO;
use App\DTOs\SubadqResponse;
use App\DTOs\WithdrawNotificationDTO;
use Illuminate\Support\Facades\Http;

class SubadqCAdapter implements SubadquirerInterface
{
    public function __construct(
        private readonly string $baseUrl
    ) {
    }

    public function getName(): string
    {
        return 'SubadqC';
    }

    public function createPix(array $payload): SubadqResponse
    {
        // Implementar chamada HTTP para criar PIX
    }

    public function createWithdraw(array $payload): SubadqResponse
    {
        // Implementar chamada HTTP para criar withdraw
    }

    public function parsePixWebhook(array $payload): ?PixNotificationDTO
    {
        // Implementar parsing do webhook de PIX
    }

    public function parseWithdrawWebhook(array $payload): ?WithdrawNotificationDTO
    {
        // Implementar parsing do webhook de withdraw
    }
}
```

### 2. Registrar no Service Provider

Adicione o binding em `app/Providers/SubadquirerServiceProvider.php`:

```php
$this->app->singleton('subadquirer.SubadqC', function ($app) {
    return new SubadqCAdapter(
        baseUrl: config('services.subadq_c.base_url', env('SUBADQC_BASE_URL'))
    );
});
```

### 3. Adicionar Configuração

Adicione no `.env`:

```env
SUBADQC_BASE_URL=https://subadqc.mock
```

E em `config/services.php`:

```php
'subadq_c' => [
    'base_url' => env('SUBADQC_BASE_URL', 'https://subadqc.mock'),
],
```

### 4. Criar Testes

Adicione testes em `tests/Feature/` para validar a integração.

## 🔒 Segurança

- Autenticação via Laravel Sanctum
- Validação de entrada em todos os endpoints
- Idempotência nos webhooks (evita processamento duplicado)
- Logs de todas as operações críticas
- Transações de banco de dados para garantir consistência

## 📊 Observabilidade

- Logging completo via Laravel Log
- Tabela `webhook_logs` para rastreamento de webhooks
- Eventos disparados para PIX confirmado e Withdraw completado
- Health check endpoint para monitoramento

## 🗄️ Banco de Dados

### Tabelas Principais

- `users`: Usuários do sistema
- `pix`: Transações PIX
- `withdraws`: Solicitações de saque
- `webhook_logs`: Logs de webhooks processados
- `user_subadquirers`: Relação usuário-subadquirente (opcional)

### Multiadquirência

Cada usuário pode estar vinculado a uma subadquirente de duas formas:

1. **Campo direto**: Campo `subadquirer` na tabela `users`
2. **Relação**: Tabela `user_subadquirers` (permite múltiplas subadquirentes por usuário)

O sistema prioriza a relação `user_subadquirers` se existir, caso contrário usa o campo direto.

## 📮 Collection Postman

Uma collection completa do Postman está disponível para facilitar os testes da API:

### Importar Collection

1. **Collection**: Importe o arquivo `postman_collection.json` no Postman
2. **Environment**: Importe o arquivo `postman_environment.json` (opcional, mas recomendado)

### Configurar Variáveis

Após importar, configure as variáveis de ambiente:

- `base_url`: URL base da API (padrão: `http://localhost:8000`)
- `api_token`: Token de autenticação (obtido após executar `php artisan migrate --seed`)

### Estrutura da Collection

A collection inclui:

- **Health Check**: Verificar status da API
- **Authentication**:
  - Login (salva token automaticamente)
  - Login - User B (SubadqB)
  - Me (User Info)
  - Logout
  - Logout All
- **PIX**:
  - Criar PIX
  - Criar PIX - Valor Mínimo
  - Criar PIX - Sem Autenticação (teste de erro)
  - Criar PIX - Validação Erro
- **Withdraw**:
  - Criar Withdraw
  - Criar Withdraw - Nubank
  - Criar Withdraw - Sem Banco (teste de validação)

### Obter Tokens de Teste

Você pode obter tokens de duas formas:

1. **Via Login (Recomendado)**: Use o endpoint `/api/login` no Postman. O token será automaticamente salvo na variável `api_token`.

2. **Via Seeder**: Execute o seeder para obter tokens pré-criados:

```bash
php artisan migrate --seed
```

Os tokens serão exibidos no console. Copie e cole no Postman na variável `api_token`.

**Usuários de teste criados pelo seeder:**
- `userA@example.com` / `password` (SubadqA)
- `userB@example.com` / `password` (SubadqB)
- `userC@example.com` / `password` (SubadqA via relação)

## 📝 Exemplos de Uso

### Exemplo completo com cURL

```bash
# 1. Login para obter token
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "userA@example.com",
    "password": "password"
  }'

# Ou use o token criado pelo seeder (execute: php artisan migrate --seed)

# 2. Criar PIX
curl -X POST http://localhost:8000/api/pix \
  -H "Authorization: Bearer {SEU_TOKEN_AQUI}" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 125.50,
    "reference": "order-123"
  }'

# 3. Criar Withdraw
curl -X POST http://localhost:8000/api/withdraw \
  -H "Authorization: Bearer {SEU_TOKEN_AQUI}" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 500.00,
    "bank": {
      "bank": "Itaú",
      "agency": "0001",
      "account": "1234567-8"
    }
  }'

# 4. Health Check
curl http://localhost:8000/api/health
```

## 🐛 Troubleshooting

### Queue não processa jobs

Se estiver usando `QUEUE_CONNECTION=sync`, os jobs são processados síncronamente. Para processamento assíncrono:

1. Configure Redis no `.env`
2. Altere `QUEUE_CONNECTION=redis`
3. Execute `php artisan queue:work`

### Webhooks não são processados

Verifique:
1. Se o worker de queue está rodando (`php artisan queue:work`)
2. Logs em `storage/logs/laravel.log`
3. Tabela `webhook_logs` para ver status dos webhooks

### Erro de autenticação

Certifique-se de:
1. Passar o token no header: `Authorization: Bearer {TOKEN}`
2. Token foi criado pelo seeder ou via `$user->createToken()`

## 📄 Licença

MIT

## 👥 Contribuindo

1. Fork o projeto
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 📧 Contato

Para dúvidas ou sugestões, abra uma issue no repositório.

