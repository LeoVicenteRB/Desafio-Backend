# Personalização dos Adapters

Este documento explica como ajustar os adapters para se adequar às especificações exatas de cada subadquirente.

## Estrutura dos Adapters

Todos os adapters implementam a interface `SubadquirerInterface` localizada em:
- `app/Adapters/Contracts/SubadquirerInterface.php`

## Ajustando o SubadqAAdapter

O adapter da SubadqA está em: `app/Adapters/SubadqAAdapter.php`

### Formato de Requisição

O adapter atual envia requisições no seguinte formato:

**PIX:**
```json
POST /pix/create
{
  "amount": 125.50,
  "reference": "order-123",  // opcional
  "metadata": {}              // opcional
}
```

**Withdraw:**
```json
POST /withdraw/create
{
  "amount": 500.00,
  "bank": {                   // opcional
    "bank": "Itaú",
    "agency": "0001",
    "account": "1234567-8"
  },
  "metadata": {}              // opcional
}
```

### Ajustes Comuns

#### 1. Alterar Formato do Payload

Se a SubadqA esperar um formato diferente, edite os métodos `createPix()` ou `createWithdraw()`:

```php
// Exemplo: Se a API esperar "value" ao invés de "amount"
$requestPayload = [
    'value' => (float) $payload['amount'],  // Mude aqui
    'reference_id' => $payload['reference'] ?? null,  // E aqui
];
```

#### 2. Alterar Método de Autenticação

O adapter atual suporta headers `X-API-Key` e `X-API-Secret`. Para outros métodos:

**Bearer Token:**
```php
$httpClient->withToken($this->apiKey);
```

**Basic Auth:**
```php
$httpClient->withBasicAuth($this->apiKey, $this->apiSecret);
```

**Header customizado:**
```php
$httpClient->withHeaders([
    'Authorization' => 'Custom ' . $this->apiKey,
]);
```

#### 3. Alterar Endpoints

Se os endpoints forem diferentes:

```php
// Em createPix()
$response = $httpClient->post("{$this->baseUrl}/api/v1/pix", $requestPayload);

// Em createWithdraw()
$response = $httpClient->post("{$this->baseUrl}/api/v1/withdrawals", $requestPayload);
```

#### 4. Alterar Tratamento de Resposta

Se a resposta vier em formato diferente:

```php
// Exemplo: Se retornar "pix.id" ao invés de "pix_id"
$externalId = $responseData['pix']['id'] ?? $responseData['id'] ?? null;
```

### Logs

O adapter registra logs detalhados de todas as requisições e respostas. Verifique:
- `storage/logs/laravel.log`

Os logs incluem:
- URL da requisição
- Payload enviado
- Status code da resposta
- Resposta completa

### Testando Mudanças

1. Faça as alterações no adapter
2. Teste via Postman usando a collection
3. Verifique os logs em `storage/logs/laravel.log`
4. Ajuste conforme necessário

## Consultando a Documentação Oficial

Sempre consulte a documentação oficial da SubadqA:
- **Link**: https://documenter.getpostman.com/view/49994027/2sB3WvMJ8p

Compare os exemplos da documentação com o código atual e ajuste conforme necessário.

