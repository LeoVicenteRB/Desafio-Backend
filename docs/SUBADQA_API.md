# Documentação da API SubadqA

## Referência Oficial

A documentação completa da API SubadqA está disponível no Postman:
- **Link**: https://documenter.getpostman.com/view/49994027/2sB3WvMJ8p

## Endpoints Implementados

### Criar PIX

**Endpoint**: `POST /pix/create`

**Payload** (conforme documentação oficial):
```json
{
  "merchant_id": "m123",
  "amount": 12345,
  "currency": "BRL",
  "order_id": "order_001",
  "payer": {
    "name": "Fulano",
    "cpf_cnpj": "00000000000"
  },
  "expires_in": 3600
}
```

**Nota**: O `amount` deve ser enviado em **centavos** (ex: R$ 110.00 = 11000 centavos).

**Resposta de Sucesso**:
```json
{
  "transaction_id": "SP_SUBADQA_3f277190-9b19-4008-becb-6ccd15e33908",
  "location": "https://subadqA.com/pix/loc/613",
  "qrcode": "00020126530014BR.GOV.BCB.PIX0131backendtest@superpagamentos.com52040000530398654075000.005802BR5901N6001C6205050116304ACDA",
  "expires_at": "1763400080",
  "status": "PENDING"
}
```

### Criar Withdraw

**Endpoint**: `POST /withdraw/create`

**Payload**:
```json
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

**Resposta de Sucesso**:
```json
{
  "withdraw_id": "WD123456789",
  "status": "PENDING",
  ...
}
```

## Webhooks

### PIX Payment Confirmed

**Evento**: `pix_payment_confirmed`

**Payload**:
```json
{
  "event": "pix_payment_confirmed",
  "transaction_id": "f1a2b3c4d5e6",
  "pix_id": "PIX123456789",
  "status": "CONFIRMED",
  "amount": 125.50,
  "payer_name": "João da Silva",
  "payer_cpf": "12345678900",
  "payment_date": "2025-11-13T14:25:00Z",
  "metadata": {
    "source": "SubadqA",
    "environment": "sandbox"
  }
}
```

### Withdraw Completed

**Evento**: `withdraw_completed`

**Payload**:
```json
{
  "event": "withdraw_completed",
  "withdraw_id": "WD123456789",
  "transaction_id": "T987654321",
  "status": "SUCCESS",
  "amount": 500.00,
  "requested_at": "2025-11-13T13:10:00Z",
  "completed_at": "2025-11-13T13:12:30Z",
  "metadata": {
    "source": "SubadqA",
    "destination_bank": "Itaú"
  }
}
```

## Configuração

As configurações da SubadqA são feitas através de variáveis de ambiente no arquivo `.env`:

```env
# URL base da API
SUBADQA_BASE_URL=https://subadqa.mock

# Autenticação (opcional, se a API exigir)
SUBADQA_API_KEY=your_api_key_here
SUBADQA_API_SECRET=your_api_secret_here

# Timeout das requisições (opcional, padrão: 30 segundos)
SUBADQA_TIMEOUT=30

# Merchant ID (obrigatório)
SUBADQA_MERCHANT_ID=m123
```

### Headers de Autenticação

O adapter envia automaticamente os headers de autenticação se configurados:
- `X-API-Key`: Enviado se `SUBADQA_API_KEY` estiver configurado
- `X-API-Secret`: Enviado se `SUBADQA_API_SECRET` estiver configurado

**Nota**: Se a SubadqA usar um método de autenticação diferente (Bearer Token, Basic Auth, etc.), será necessário ajustar o adapter em `app/Adapters/SubadqAAdapter.php`.

## Implementação

O adapter está localizado em: `app/Adapters/SubadqAAdapter.php`

Para ajustar a implementação conforme a documentação oficial, edite o adapter mantendo a interface `SubadquirerInterface`.

