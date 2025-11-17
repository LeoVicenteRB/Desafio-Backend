# Histórico do Chat - Desafio Backend

## Resumo do Projeto

Este documento contém o histórico completo do desenvolvimento de um módulo de integração com subadquirentes de pagamento (SubadqA e SubadqB) em Laravel 10+ (PHP 8+).

## Requisitos Principais

### Tecnologias
- PHP 8+
- Laravel 10+
- MySQL (migrations + seeders)
- Eloquent ORM
- Laravel Sanctum (autenticação API)
- Redis (filas)
- Laravel Queues e Jobs (processamento assíncrono)

### Padrões de Design
- Strategy/Adapter para subadquirentes
- Service Layer
- DTOs/Value Objects
- Factory
- Dependency Injection via Service Providers
- SOLID

### Funcionalidades
- Suporte a múltiplos subadquirentes por usuário
- Troca simples de subadquirente
- Processamento assíncrono de webhooks
- Idempotência de webhooks
- Logging completo
- Testes automatizados (Feature + Unit)

## Endpoints da API

### Autenticação
- `POST /api/login` - Login e obtenção de token
- `POST /api/logout` - Logout (revoga token atual)
- `POST /api/logout-all` - Logout de todos os dispositivos
- `GET /api/me` - Informações do usuário autenticado

### Pagamentos
- `POST /api/pix` - Criar PIX (requer autenticação)
- `POST /api/withdraw` - Criar saque (requer autenticação)

### Utilitários
- `GET /api/health` - Health check

## Estrutura do Projeto

### Adapters
- `app/Adapters/Contracts/SubadquirerInterface.php` - Interface para adapters
- `app/Adapters/SubadqAAdapter.php` - Adapter para SubadqA
- `app/Adapters/SubadqBAdapter.php` - Adapter para SubadqB

### Services
- `app/Services/SubadquirerService.php` - Resolve o adapter correto
- `app/Services/PixService.php` - Lógica de negócio para PIX
- `app/Services/WithdrawService.php` - Lógica de negócio para saques

### DTOs
- `app/DTOs/SubadqResponse.php` - Resposta padronizada dos adapters
- `app/DTOs/PixNotificationDTO.php` - DTO para notificações PIX
- `app/DTOs/WithdrawNotificationDTO.php` - DTO para notificações de saque

### Jobs
- `app/Jobs/ProcessWebhookJob.php` - Processa webhooks de forma assíncrona

### Models
- `app/Models/User.php` - Usuário com relacionamentos
- `app/Models/Pix.php` - Transações PIX
- `app/Models/Withdraw.php` - Saques
- `app/Models/WebhookLog.php` - Logs de webhooks
- `app/Models/UserSubadquirer.php` - Relação usuário-subadquirente

### Controllers
- `app/Http/Controllers/Api/AuthController.php` - Autenticação
- `app/Http/Controllers/Api/PixController.php` - PIX
- `app/Http/Controllers/Api/WithdrawController.php` - Saques
- `app/Http/Controllers/Api/HealthController.php` - Health check

## Problemas Encontrados e Soluções

### 1. Erro: `Class Illuminate\Foundation\Composer\Scripts is not autoloadable`
**Problema**: Scripts do composer tentando executar arquivos inexistentes.

**Solução**: 
- Criado arquivo `artisan`
- Ajustado `composer.json` removendo scripts que dependiam de arquivos não presentes

### 2. Erro: `The bootstrap/cache directory must be present and writable`
**Problema**: Diretórios de cache e storage não existiam.

**Solução**: 
- Criados diretórios necessários: `bootstrap/cache`, `storage/app`, `storage/framework`, `storage/logs`
- Adicionados arquivos `.gitignore` apropriados

### 3. Erro: `BadMethodCallException: Method ClosureCommand::hourly does not exist`
**Problema**: Tentativa de usar método `hourly()` diretamente em `routes/console.php`.

**Solução**: Removido `->hourly()` de `routes/console.php` (agendamento deve ser feito em `app/Console/Kernel.php`)

### 4. Erro: `cp: cannot stat '.env.example': No such file or directory`
**Problema**: Arquivo `.env.example` não existia.

**Solução**: Criado `.env.example` completo com todas as variáveis necessárias

### 5. Erro: `FileViewFinder::__construct(): Argument #2 ($paths) must be of type array, null given`
**Problema**: Arquivos de configuração faltando.

**Solução**: 
- Criado `config/view.php`
- Criados outros arquivos de configuração essenciais: `session.php`, `filesystems.php`, `cache.php`, `logging.php`
- Atualizado `config/app.php`

### 6. Erro: `Class "App\Http\Controllers\Controller" not found`
**Problema**: Classe base Controller não existia.

**Solução**: Criado `app/Http/Controllers/Controller.php`

### 7. Erro: `RouteNotFoundException: Route [login] not defined`
**Problema**: Middleware de autenticação tentando redirecionar para rota web inexistente.

**Solução**: 
- Modificado `app/Http/Middleware/Authenticate.php` para retornar `null` em requisições API
- Melhorado `app/Exceptions/Handler.php` para retornar JSON 401 em vez de redirecionamento

### 8. Erro: `"amount must be greater than 0"` da API SubadqA
**Problema**: API SubadqA esperava `amount` em centavos (inteiro), mas estava sendo enviado em reais (float).

**Solução**: 
- Ajustado `SubadqAAdapter` para converter reais para centavos (`amount * 100`)
- Adicionado `merchant_id`, `currency`, `order_id`, `payer`, `expires_in` no payload
- Usado `Http::withBody()` com `json_encode` para garantir serialização exata
- Adicionado `SUBADQA_MERCHANT_ID` ao `.env.example` e `config/services.php`
- Ajustado parsing de webhooks para converter centavos de volta para reais

## Configurações Importantes

### Variáveis de Ambiente (.env)

```env
# SubadqA
SUBADQA_BASE_URL=https://0acdeaee-1729-4d55-80eb-d54a125e5e18.mock.pstmn.io
SUBADQA_API_KEY=
SUBADQA_API_SECRET=
SUBADQA_TIMEOUT=30
SUBADQA_MERCHANT_ID=m123

# SubadqB
SUBADQB_BASE_URL=https://subadqb.mock
SUBADQB_API_KEY=
SUBADQB_API_SECRET=
SUBADQB_TIMEOUT=30

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=desafio_backend
DB_USERNAME=root
DB_PASSWORD=

# Queue
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## Comandos Importantes

### Instalação
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

### Executar Queue Worker
```bash
php artisan queue:work redis
```

### Simular Webhooks
```bash
php artisan simulate:webhooks {pix_id} --count=10 --rate=3
```

### Executar Testes
```bash
php artisan test
```

## Documentação das APIs dos Subadquirentes

### SubadqA
- **Documentação**: https://documenter.getpostman.com/view/49994027/2sB3WvMJ8p
- **Formato PIX**: `amount` em centavos (inteiro), campos: `merchant_id`, `currency`, `order_id`, `payer`, `expires_in`
- **Headers**: `Content-Type: application/json`, `X-API-Key`, `X-API-Secret`, `x-mock-response-name` (para mock)

### SubadqB
- **Documentação**: https://documenter.getpostman.com/view/49994027/2sB3WvMJD7
- **Formato PIX**: `value` em reais (float), campo `reference`
- **Formato Withdraw**: `amount` em reais, campo `bank_account`
- **Headers**: `Content-Type: application/json`, `X-API-Key`, `X-API-Secret`

## Validações Implementadas

### Webhooks
- Validação de payload vazio ou inválido
- Validação de tipo de webhook (`pix` ou `withdraw`)
- Validação de `external_id` (presença e tipo string)
- Validação de `amount` (não negativo)
- Validação de `status` (tipo string)
- Validação de estrutura de dados específica de cada subadquirente
- Idempotência via `webhook_logs`

### Criação de PIX/Withdraw
- Validação de `amount` > 0
- Validação de campos obrigatórios
- Conversão de formatos (centavos para SubadqA, reais para SubadqB)

## Melhorias de Código Realizadas

### Limpeza
- Removidos comentários desnecessários
- Removido código redundante
- Simplificada construção de headers
- Removido parâmetro `useCents` não utilizado

### Validações
- Validações robustas em todos os métodos de parsing de webhooks
- Validações nos Services antes de processar webhooks
- Validações no ProcessWebhookJob
- Logs descritivos para debugging

## Arquivos de Documentação

- `README.md` - Documentação principal do projeto
- `EXAMPLES.md` - Exemplos de cURL
- `postman_collection.json` - Collection do Postman
- `postman_environment.json` - Variáveis de ambiente do Postman
- `docs/SUBADQA_API.md` - Documentação específica da SubadqA
- `docs/SUBADQB_API.md` - Documentação específica da SubadqB
- `docs/ADAPTER_CUSTOMIZATION.md` - Guia para adicionar novos adapters

## Estrutura de Banco de Dados

### Tabelas Principais
- `users` - Usuários do sistema
- `pix` - Transações PIX
- `withdraws` - Saques
- `webhook_logs` - Logs de webhooks (idempotência)
- `user_subadquirers` - Relação usuário-subadquirente
- `personal_access_tokens` - Tokens do Sanctum
- `jobs` - Fila de jobs
- `failed_jobs` - Jobs falhados

## Fluxo de Processamento

### Criação de PIX
1. Cliente faz POST `/api/pix` com autenticação
2. Controller valida dados
3. Service cria registro PIX com status `PENDING`
4. Service resolve adapter do subadquirente
5. Adapter formata payload e envia para API externa
6. Se sucesso, status muda para `PROCESSING`
7. Jobs são disparados para simular webhooks
8. Webhooks são processados assincronamente
9. Status é atualizado conforme notificações

### Processamento de Webhook
1. Webhook recebido (via Job)
2. Verificação de idempotência (`webhook_logs`)
3. Parsing do payload pelo adapter
4. Validações de dados
5. Busca do registro (PIX/Withdraw) por `external_id`
6. Atualização de status dentro de transação
7. Disparo de eventos se necessário
8. Marcação do webhook como processado

## Testes

### Feature Tests
- Criação de PIX (happy path)
- Criação de PIX (erro)
- Idempotência de webhooks
- Criação de saque

### Mocks
- HTTP requests para APIs externas usando `Http::fake()`

## Observações Finais

- O código segue os princípios SOLID
- Padrão Strategy/Adapter facilita adicionar novos subadquirentes
- Processamento assíncrono garante alta performance
- Idempotência previne processamento duplicado de webhooks
- Logging completo para debugging e observabilidade
- Validações robustas em todas as camadas
- Código limpo e bem documentado

## Próximos Passos Sugeridos

1. Adicionar mais testes unitários
2. Implementar rate limiting
3. Adicionar métricas e monitoramento
4. Implementar retry automático para webhooks falhados
5. Adicionar webhook signature validation
6. Implementar circuit breaker para APIs externas
7. Adicionar documentação Swagger/OpenAPI
