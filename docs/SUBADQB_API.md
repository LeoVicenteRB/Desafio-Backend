# Documentação da API SubadqB

## Referência Oficial

A documentação completa da API SubadqB está disponível no Postman:
- **Link**: https://documenter.getpostman.com/view/49994027/2sB3WvMJD7

## Endpoints Implementados

### Criar PIX

**Endpoint**: `POST /api/pix`

**Payload**:
```json
{
  "value": 250.00,
  "reference": "order-123",
  "metadata": {}
}
```

**Nota**: SubadqB usa `value` ao invés de `amount`.

**Resposta de Sucesso**:
```json
{
  "id": "PX987654321",
  "status": "PENDING",
  ...
}
```

### Criar Withdraw

**Endpoint**: `POST /api/withdraw`

**Payload**:
```json
{
  "amount": 850.00,
  "bank_account": {
    "bank": "Nubank",
    "agency": "0001",
    "account": "1234567-8"
  },
  "metadata": {}
}
```

**Nota**: SubadqB usa `bank_account` ao invés de `bank`.

**Resposta de Sucesso**:
```json
{
  "id": "WDX54321",
  "status": "PENDING",
  ...
}
```

## Webhooks

### PIX Status Update

**Tipo**: `pix.status_update`

**Payload**:
```json
{
  "type": "pix.status_update",
  "data": {
    "id": "PX987654321",
    "status": "PAID",
    "value": 250.00,
    "payer": {
      "name": "Maria Oliveira",
      "document": "98765432100"
    },
    "confirmed_at": "2025-11-13T14:40:00Z"
  },
  "signature": "d1c4b6f98eaa"
}
```

### Withdraw Status Update

**Tipo**: `withdraw.status_update`

**Payload**:
```json
{
  "type": "withdraw.status_update",
  "data": {
    "id": "WDX54321",
    "status": "DONE",
    "amount": 850.00,
    "bank_account": {
      "bank": "Nubank",
      "agency": "0001",
      "account": "1234567-8"
    },
    "processed_at": "2025-11-13T13:45:10Z"
  },
  "signature": "aabbccddeeff112233"
}
```

## Diferenças em relação à SubadqA

1. **Campo de valor**: SubadqB usa `value` para PIX (SubadqA usa `amount`)
2. **Campo bancário**: SubadqB usa `bank_account` (SubadqA usa `bank`)
3. **Formato de webhook**: SubadqB usa estrutura `{type, data, signature}` (SubadqA usa `{event, ...}`)
4. **Endpoint**: SubadqB usa `/api/pix` e `/api/withdraw` (SubadqA usa `/pix/create` e `/withdraw/create`)

## Configuração

As configurações da SubadqB são feitas através de variáveis de ambiente no arquivo `.env`:

```env
# URL base da API
SUBADQB_BASE_URL=https://subadqb.mock

# Autenticação (opcional, se a API exigir)
SUBADQB_API_KEY=your_api_key_here
SUBADQB_API_SECRET=your_api_secret_here

# Timeout das requisições (opcional, padrão: 30 segundos)
SUBADQB_TIMEOUT=30
```

### Headers de Autenticação

O adapter envia automaticamente os headers de autenticação se configurados:
- `X-API-Key`: Enviado se `SUBADQB_API_KEY` estiver configurado
- `X-API-Secret`: Enviado se `SUBADQB_API_SECRET` estiver configurado

**Nota**: Se a SubadqB usar um método de autenticação diferente (Bearer Token, Basic Auth, etc.), será necessário ajustar o adapter em `app/Adapters/SubadqBAdapter.php`.

## Implementação

O adapter está localizado em: `app/Adapters/SubadqBAdapter.php`

Para ajustar a implementação conforme a documentação oficial, edite o adapter mantendo a interface `SubadquirerInterface`.

