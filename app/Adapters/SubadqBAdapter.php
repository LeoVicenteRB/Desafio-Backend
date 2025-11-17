<?php

namespace App\Adapters;

use App\Adapters\Contracts\SubadquirerInterface;
use App\DTOs\PixNotificationDTO;
use App\DTOs\SubadqResponse;
use App\DTOs\WithdrawNotificationDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubadqBAdapter implements SubadquirerInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey = null,
        private readonly ?string $apiSecret = null,
        private readonly int $timeout = 30
    ) {
    }

    public function getName(): string
    {
        return 'SubadqB';
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
                Log::warning('SubadqB createPix: Invalid amount', [
                    'amount' => $amount,
                    'original' => $payload['amount'],
                    'payload' => $payload,
                ]);
                return SubadqResponse::error(
                    error: 'Amount must be greater than 0',
                    data: ['amount' => $amount, 'original' => $payload['amount']]
                );
            }

            // SubadqB usa "value" ao invés de "amount"
            // Garantir que o value seja enviado como número, não string
            $requestPayload = [
                'value' => round($amount, 2), // Arredondar para 2 casas decimais
            ];

            // Adicionar reference se fornecido
            if (isset($payload['reference']) && !empty($payload['reference'])) {
                $requestPayload['reference'] = $payload['reference'];
            }

            // Adicionar metadata se fornecido
            if (isset($payload['metadata']) && !empty($payload['metadata'])) {
                $requestPayload['metadata'] = $payload['metadata'];
            }

            Log::info('SubadqB createPix request', [
                'url' => "{$this->baseUrl}/api/pix",
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

            $response = $httpClient->post("{$this->baseUrl}/api/pix", $requestPayload);

            $responseData = $response->json();
            $statusCode = $response->status();

            Log::info('SubadqB createPix response', [
                'status_code' => $statusCode,
                'response' => $responseData,
            ]);

            if ($response->successful()) {
                // SubadqB retorna "id" no formato padrão
                $externalId = $responseData['id'] ?? $responseData['pix_id'] ?? null;
                
                if (!$externalId) {
                    Log::warning('SubadqB createPix: No external ID in response', [
                        'response' => $responseData,
                    ]);
                    return SubadqResponse::error(
                        error: 'No external ID returned from SubadqB',
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
            Log::error('SubadqB createPix error', [
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
                Log::warning('SubadqB createWithdraw: Invalid amount', [
                    'amount' => $amount,
                    'original' => $payload['amount'],
                    'payload' => $payload,
                ]);
                return SubadqResponse::error(
                    error: 'Amount must be greater than 0',
                    data: ['amount' => $amount, 'original' => $payload['amount']]
                );
            }

            // SubadqB usa "bank_account" ao invés de "bank"
            // Garantir que o amount seja enviado como número, não string
            $requestPayload = [
                'amount' => round($amount, 2), // Arredondar para 2 casas decimais
            ];

            // Adicionar dados bancários se fornecidos (SubadqB espera "bank_account")
            if (isset($payload['bank']) && is_array($payload['bank']) && !empty($payload['bank'])) {
                $requestPayload['bank_account'] = $payload['bank'];
            }

            // Adicionar metadata se fornecido
            if (isset($payload['metadata']) && !empty($payload['metadata'])) {
                $requestPayload['metadata'] = $payload['metadata'];
            }

            Log::info('SubadqB createWithdraw request', [
                'url' => "{$this->baseUrl}/api/withdraw",
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

            $response = $httpClient->post("{$this->baseUrl}/api/withdraw", $requestPayload);

            $responseData = $response->json();
            $statusCode = $response->status();

            Log::info('SubadqB createWithdraw response', [
                'status_code' => $statusCode,
                'response' => $responseData,
            ]);

            if ($response->successful()) {
                // SubadqB retorna "id" no formato padrão
                $externalId = $responseData['id'] ?? $responseData['withdraw_id'] ?? null;
                
                if (!$externalId) {
                    Log::warning('SubadqB createWithdraw: No external ID in response', [
                        'response' => $responseData,
                    ]);
                    return SubadqResponse::error(
                        error: 'No external ID returned from SubadqB',
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
            Log::error('SubadqB createWithdraw error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
            ]);

            return SubadqResponse::error(error: $e->getMessage());
        }
    }

    public function parsePixWebhook(array $payload): ?PixNotificationDTO
    {
        // SubadqB format: { "type": "pix.status_update", "data": { ... } }
        if (!isset($payload['type']) || $payload['type'] !== 'pix.status_update') {
            return null;
        }

        $data = $payload['data'] ?? [];

        return new PixNotificationDTO(
            externalId: $data['id'] ?? '',
            status: $data['status'] ?? 'PENDING',
            amount: (float) ($data['value'] ?? 0),
            payerName: $data['payer']['name'] ?? null,
            payerDocument: $data['payer']['document'] ?? null,
            paymentDate: $data['confirmed_at'] ?? null,
            metadata: ['signature' => $payload['signature'] ?? null],
        );
    }

    public function parseWithdrawWebhook(array $payload): ?WithdrawNotificationDTO
    {
        // SubadqB format: { "type": "withdraw.status_update", "data": { ... } }
        if (!isset($payload['type']) || $payload['type'] !== 'withdraw.status_update') {
            return null;
        }

        $data = $payload['data'] ?? [];

        return new WithdrawNotificationDTO(
            externalId: $data['id'] ?? '',
            transactionId: null,
            status: $data['status'] ?? 'PENDING',
            amount: (float) ($data['amount'] ?? 0),
            completedAt: $data['processed_at'] ?? null,
            bankInfo: $data['bank_account'] ?? null,
            metadata: ['signature' => $payload['signature'] ?? null],
        );
    }
}

