# Exemplos de Uso da API

## Obter Token de Autenticação

Após executar `php artisan migrate --seed`, os tokens serão exibidos no console. Use esses tokens para autenticar as requisições.

## Exemplos com cURL

### 1. Health Check

```bash
curl http://localhost:8000/api/health
```

**Resposta:**
```json
{
  "status": "ok",
  "database": "ok",
  "timestamp": "2025-01-13T10:00:00Z"
}
```

### 2. Criar PIX

```bash
curl -X POST http://localhost:8000/api/pix \
  -H "Authorization: Bearer {SEU_TOKEN_AQUI}" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 125.50,
    "reference": "order-123",
    "metadata": {
      "order_id": "12345",
      "customer_id": "67890"
    }
  }'
```

**Resposta (201):**
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
    "metadata": {
      "order_id": "12345",
      "customer_id": "67890"
    },
    "created_at": "2025-01-13T10:00:00Z",
    "updated_at": "2025-01-13T10:00:00Z"
  }
}
```

### 3. Criar Withdraw

```bash
curl -X POST http://localhost:8000/api/withdraw \
  -H "Authorization: Bearer {SEU_TOKEN_AQUI}" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 500.00,
    "bank": {
      "bank": "Itaú",
      "agency": "0001",
      "account": "1234567-8"
    },
    "metadata": {
      "reason": "Monthly payout"
    }
  }'
```

**Resposta (201):**
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
    "metadata": {
      "reason": "Monthly payout"
    },
    "created_at": "2025-01-13T10:00:00Z",
    "updated_at": "2025-01-13T10:00:00Z"
  }
}
```

### 4. Simular Webhooks

```bash
# Simular 30 webhooks para um PIX com ID 1
php artisan simulate:webhooks 1 --type=pix --count=30 --rate=3

# Simular 10 webhooks para um withdraw com ID 2
php artisan simulate:webhooks 2 --type=withdraw --count=10 --rate=3
```

## Exemplos com PHP (Guzzle)

```php
<?php

use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'http://localhost:8000',
    'headers' => [
        'Authorization' => 'Bearer {SEU_TOKEN_AQUI}',
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ],
]);

// Criar PIX
$response = $client->post('/api/pix', [
    'json' => [
        'amount' => 125.50,
        'reference' => 'order-123',
    ],
]);

$pix = json_decode($response->getBody(), true);
echo "PIX criado: " . $pix['data']['id'] . "\n";

// Criar Withdraw
$response = $client->post('/api/withdraw', [
    'json' => [
        'amount' => 500.00,
        'bank' => [
            'bank' => 'Itaú',
            'agency' => '0001',
            'account' => '1234567-8',
        ],
    ],
]);

$withdraw = json_decode($response->getBody(), true);
echo "Withdraw criado: " . $withdraw['data']['id'] . "\n";
```

## Exemplos com JavaScript (Fetch)

```javascript
const API_BASE_URL = 'http://localhost:8000';
const TOKEN = '{SEU_TOKEN_AQUI}';

// Criar PIX
async function createPix() {
  const response = await fetch(`${API_BASE_URL}/api/pix`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${TOKEN}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      amount: 125.50,
      reference: 'order-123',
    }),
  });

  const data = await response.json();
  console.log('PIX criado:', data.data);
  return data.data;
}

// Criar Withdraw
async function createWithdraw() {
  const response = await fetch(`${API_BASE_URL}/api/withdraw`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${TOKEN}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      amount: 500.00,
      bank: {
        bank: 'Itaú',
        agency: '0001',
        account: '1234567-8',
      },
    }),
  });

  const data = await response.json();
  console.log('Withdraw criado:', data.data);
  return data.data;
}

// Health Check
async function healthCheck() {
  const response = await fetch(`${API_BASE_URL}/api/health`);
  const data = await response.json();
  console.log('Health:', data);
  return data;
}
```

## Status de Transações

### Status de PIX

- `PENDING`: Aguardando processamento
- `PROCESSING`: Em processamento
- `CONFIRMED`: Confirmado/Pago
- `PAID`: Pago
- `FAILED`: Falhou
- `CANCELLED`: Cancelado

### Status de Withdraw

- `PENDING`: Aguardando processamento
- `PROCESSING`: Em processamento
- `SUCCESS`: Sucesso
- `DONE`: Concluído
- `FAILED`: Falhou
- `CANCELLED`: Cancelado

## Tratamento de Erros

### Erro de Validação (422)

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "amount": ["The amount field is required."]
  }
}
```

### Erro de Autenticação (401)

```json
{
  "message": "Unauthenticated."
}
```

### Erro de Servidor (500)

```json
{
  "success": false,
  "message": "An error occurred while creating PIX"
}
```

