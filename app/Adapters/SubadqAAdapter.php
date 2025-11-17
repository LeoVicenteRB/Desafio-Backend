<?php

namespace App\Adapters;

use App\Adapters\Contracts\SubadquirerInterface;
use App\DTOs\PixNotificationDTO;
use App\DTOs\SubadqResponse;
use App\DTOs\WithdrawNotificationDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubadqAAdapter implements SubadquirerInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey = null,
        private readonly ?string $apiSecret = null,
        private readonly int $timeout = 30,
        private readonly bool $useCents = false // Se true, converte reais para centavos
    ) {
    }

    public function getName(): string
    {
        return 'SubadqA';
    }

    public function createPix(array $payload): SubadqResponse
    {
        try {
            // Validar e formatar amount
            if (!isset($payload['amount']) || $payload['amount'] === null) {
                return SubadqResponse::error(
                    error: 'Amount is required',
                    data: ['payload' => $payload]
                );
            }

            $amount = (float) $payload['amount'];
            
            if ($amount <= 0) {
                Log::warning('SubadqA createPix: Invalid amount', [
                    'amount' => $amount,
                    'original' => $payload['amount'],
                    'payload' => $payload,
                ]);
                return SubadqResponse::error(
                    error: 'Amount must be greater than 0',
                    data: ['amount' => $amount, 'original' => $payload['amount']]
                );
            }

            // Formatar payload conforme documentação oficial da SubadqA
            // Documentação: https://documenter.getpostman.com/view/49994027/2sB3WvMJ8p
            // Formato esperado:
            // {
            //   "merchant_id": "m123",
            //   "amount": 12345,  // em centavos
            //   "currency": "BRL",
            //   "order_id": "order_001",
            //   "payer": { "name": "Fulano", "cpf_cnpj": "00000000000" },
            //   "expires_in": 3600
            // }
            
            // Converter reais para centavos (a API espera em centavos)
            $amountInCents = (int) round($amount * 100);
            
            // Garantir que o amount seja um inteiro válido
            if ($amountInCents <= 0) {
                Log::error('SubadqA createPix: Amount in cents is invalid', [
                    'amount' => $amount,
                    'amount_in_cents' => $amountInCents,
                ]);
                return SubadqResponse::error(
                    error: 'Invalid amount: must be greater than 0',
                    data: ['amount' => $amount, 'amount_in_cents' => $amountInCents]
                );
            }

            $requestPayload = [
                'merchant_id' => config('services.subadq_a.merchant_id', 'default_merchant'),
                'amount' => $amountInCents, // Enviar como inteiro
                'currency' => 'BRL',
                'order_id' => $payload['reference'] ?? 'order_' . time(),
            ];

            // Adicionar payer se fornecido no metadata
            if (isset($payload['metadata']['payer'])) {
                $requestPayload['payer'] = $payload['metadata']['payer'];
            } elseif (isset($payload['payer'])) {
                $requestPayload['payer'] = $payload['payer'];
            }

            // Adicionar expires_in se fornecido (padrão: 3600 segundos = 1 hora)
            if (isset($payload['expires_in'])) {
                $requestPayload['expires_in'] = (int) $payload['expires_in'];
            } elseif (isset($payload['metadata']['expires_in'])) {
                $requestPayload['expires_in'] = (int) $payload['metadata']['expires_in'];
            } else {
                $requestPayload['expires_in'] = 3600; // 1 hora padrão
            }

            // Log detalhado incluindo o JSON serializado
            $jsonPayload = json_encode($requestPayload, JSON_UNESCAPED_SLASHES);
            Log::info('SubadqA createPix request', [
                'url' => "{$this->baseUrl}/pix/create",
                'payload' => $requestPayload,
                'amount_in_cents' => $amountInCents,
                'amount_type' => gettype($requestPayload['amount']),
                'amount_value' => $requestPayload['amount'],
                'json_payload' => $jsonPayload,
            ]);

            // Preparar headers exatamente como no cURL
            $headers = [
                'Content-Type' => 'application/json',
            ];

            // Adicionar autenticação se configurada
            if ($this->apiKey) {
                $headers['X-API-Key'] = $this->apiKey;
            }

            if ($this->apiSecret) {
                $headers['X-API-Secret'] = $this->apiSecret;
            }

            // Enviar exatamente como no cURL - usar withBody para garantir formato idêntico
            $jsonBody = json_encode($requestPayload, JSON_UNESCAPED_SLASHES);
            
            $response = Http::timeout($this->timeout)
                ->withHeaders($headers)
                ->withBody($jsonBody, 'application/json')
                ->post("{$this->baseUrl}/pix/create");
            
            // Log da resposta bruta para debug
            Log::debug('SubadqA createPix raw response', [
                'status_code' => $response->status(),
                'body' => $response->body(),
            ]);

            $responseData = $response->json();
            $statusCode = $response->status();

            Log::info('SubadqA createPix response', [
                'status_code' => $statusCode,
                'response' => $responseData,
            ]);

            if ($response->successful()) {
                // SubadqA retorna transaction_id conforme documentação
                $externalId = $responseData['transaction_id'] 
                    ?? $responseData['pix_id'] 
                    ?? $responseData['id'] 
                    ?? null;
                
                if (!$externalId) {
                    Log::warning('SubadqA createPix: No external ID in response', [
                        'response' => $responseData,
                    ]);
                    return SubadqResponse::error(
                        error: 'No external ID returned from SubadqA',
                        data: $responseData
                    );
                }

                return SubadqResponse::success(
                    externalId: $externalId,
                    status: $responseData['status'] ?? 'PENDING',
                    data: $responseData
                );
            }

            $errorMessage = $responseData['message'] 
                ?? $responseData['error'] 
                ?? "HTTP {$statusCode}: Failed to create PIX";

            return SubadqResponse::error(
                error: $errorMessage,
                data: $responseData
            );
        } catch (\Exception $e) {
            Log::error('SubadqA createPix error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
            ]);

            return SubadqResponse::error(error: $e->getMessage());
        }
    }

    public function createWithdraw(array $payload): SubadqResponse
    {
        try {
            // Validar e formatar amount
            if (!isset($payload['amount']) || $payload['amount'] === null) {
                return SubadqResponse::error(
                    error: 'Amount is required',
                    data: ['payload' => $payload]
                );
            }

            $amount = (float) $payload['amount'];
            
            if ($amount <= 0) {
                Log::warning('SubadqA createWithdraw: Invalid amount', [
                    'amount' => $amount,
                    'original' => $payload['amount'],
                    'payload' => $payload,
                ]);
                return SubadqResponse::error(
                    error: 'Amount must be greater than 0',
                    data: ['amount' => $amount, 'original' => $payload['amount']]
                );
            }

            // Formatar payload conforme esperado pela SubadqA
            // A API pode esperar o valor em centavos ou em reais
            if ($this->useCents) {
                // Converter reais para centavos
                $formattedAmount = (int) round($amount * 100);
            } else {
                // Enviar em reais
                if ($amount == (int) $amount) {
                    // Se for número inteiro, enviar como int
                    $formattedAmount = (int) $amount;
                } else {
                    // Se tiver decimais, manter como float com 2 casas
                    $formattedAmount = round($amount, 2);
                }
            }

            $requestPayload = [
                'amount' => $formattedAmount,
            ];

            // Adicionar dados bancários se fornecidos
            if (isset($payload['bank']) && is_array($payload['bank']) && !empty($payload['bank'])) {
                $requestPayload['bank'] = $payload['bank'];
            }

            // Adicionar metadata se fornecido
            if (isset($payload['metadata']) && !empty($payload['metadata'])) {
                $requestPayload['metadata'] = $payload['metadata'];
            }

            Log::info('SubadqA createWithdraw request', [
                'url' => "{$this->baseUrl}/withdraw/create",
                'payload' => $requestPayload,
            ]);

            $httpClient = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]);

            // Adicionar autenticação se configurada
            if ($this->apiKey) {
                $httpClient->withHeaders([
                    'X-API-Key' => $this->apiKey,
                ]);
            }

            if ($this->apiSecret) {
                $httpClient->withHeaders([
                    'X-API-Secret' => $this->apiSecret,
                ]);
            }

            // Enviar payload diretamente - Laravel Http já serializa corretamente
            $response = $httpClient->post("{$this->baseUrl}/withdraw/create", $requestPayload);

            $responseData = $response->json();
            $statusCode = $response->status();

            Log::info('SubadqA createWithdraw response', [
                'status_code' => $statusCode,
                'response' => $responseData,
            ]);

            if ($response->successful()) {
                // SubadqA pode retornar withdraw_id ou id
                $externalId = $responseData['withdraw_id'] ?? $responseData['id'] ?? null;
                
                if (!$externalId) {
                    Log::warning('SubadqA createWithdraw: No external ID in response', [
                        'response' => $responseData,
                    ]);
                    return SubadqResponse::error(
                        error: 'No external ID returned from SubadqA',
                        data: $responseData
                    );
                }

                return SubadqResponse::success(
                    externalId: $externalId,
                    status: $responseData['status'] ?? 'PENDING',
                    data: $responseData
                );
            }

            $errorMessage = $responseData['message'] 
                ?? $responseData['error'] 
                ?? "HTTP {$statusCode}: Failed to create withdraw";

            return SubadqResponse::error(
                error: $errorMessage,
                data: $responseData
            );
        } catch (\Exception $e) {
            Log::error('SubadqA createWithdraw error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
            ]);

            return SubadqResponse::error(error: $e->getMessage());
        }
    }

    public function parsePixWebhook(array $payload): ?PixNotificationDTO
    {
        // SubadqA format: { "event": "pix_payment_confirmed", "transaction_id": "...", "pix_id": "...", ... }
        if (!isset($payload['event']) || $payload['event'] !== 'pix_payment_confirmed') {
            return null;
        }

        // SubadqA retorna amount em centavos, converter para reais
        $amountInCents = (int) ($payload['amount'] ?? 0);
        $amountInReais = $amountInCents / 100;

        return new PixNotificationDTO(
            externalId: $payload['pix_id'] ?? $payload['transaction_id'] ?? '',
            status: $payload['status'] ?? 'PENDING',
            amount: (float) $amountInReais,
            payerName: $payload['payer_name'] ?? $payload['payer']['name'] ?? null,
            payerDocument: $payload['payer_cpf'] ?? $payload['payer']['cpf_cnpj'] ?? null,
            paymentDate: $payload['payment_date'] ?? null,
            metadata: $payload['metadata'] ?? null,
        );
    }

    public function parseWithdrawWebhook(array $payload): ?WithdrawNotificationDTO
    {
        // SubadqA format: { "event": "withdraw_completed", "withdraw_id": "...", ... }
        if (!isset($payload['event']) || $payload['event'] !== 'withdraw_completed') {
            return null;
        }

        // SubadqA retorna amount em centavos, converter para reais
        $amountInCents = (int) ($payload['amount'] ?? 0);
        $amountInReais = $amountInCents / 100;

        return new WithdrawNotificationDTO(
            externalId: $payload['withdraw_id'] ?? '',
            transactionId: $payload['transaction_id'] ?? null,
            status: $payload['status'] ?? 'PENDING',
            amount: (float) $amountInReais,
            completedAt: $payload['completed_at'] ?? null,
            bankInfo: $payload['metadata']['destination_bank'] ?? null ? ['bank' => $payload['metadata']['destination_bank']] : null,
            metadata: $payload['metadata'] ?? null,
        );
    }
}

