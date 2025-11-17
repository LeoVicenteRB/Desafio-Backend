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
                    'payload' => $payload,
                ]);
                return SubadqResponse::error(
                    error: 'Amount must be greater than 0',
                    data: ['amount' => $amount]
                );
            }

            $requestPayload = [
                'value' => round($amount, 2),
            ];

            if (isset($payload['reference']) && !empty($payload['reference'])) {
                $requestPayload['reference'] = $payload['reference'];
            }

            if (isset($payload['metadata']) && !empty($payload['metadata'])) {
                $requestPayload['metadata'] = $payload['metadata'];
            }

            Log::info('SubadqB createPix request', [
                'url' => "{$this->baseUrl}/api/pix",
                'payload' => $requestPayload,
            ]);

            $headers = ['Content-Type' => 'application/json'];

            if ($this->apiKey) {
                $headers['X-API-Key'] = $this->apiKey;
            }

            if ($this->apiSecret) {
                $headers['X-API-Secret'] = $this->apiSecret;
            }

            $response = Http::timeout($this->timeout)
                ->withHeaders($headers)
                ->post("{$this->baseUrl}/api/pix", $requestPayload);

            $responseData = $response->json();
            $statusCode = $response->status();

            Log::info('SubadqB createPix response', [
                'status_code' => $statusCode,
                'response' => $responseData,
            ]);

            if ($response->successful()) {
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
                'payload' => $payload,
            ]);

            return SubadqResponse::error(error: $e->getMessage());
        }
    }

    public function createWithdraw(array $payload): SubadqResponse
    {
        try {
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
                    'payload' => $payload,
                ]);
                return SubadqResponse::error(
                    error: 'Amount must be greater than 0',
                    data: ['amount' => $amount]
                );
            }

            $requestPayload = [
                'amount' => round($amount, 2),
            ];

            if (isset($payload['bank']) && is_array($payload['bank']) && !empty($payload['bank'])) {
                $requestPayload['bank_account'] = $payload['bank'];
            }

            if (isset($payload['metadata']) && !empty($payload['metadata'])) {
                $requestPayload['metadata'] = $payload['metadata'];
            }

            Log::info('SubadqB createWithdraw request', [
                'url' => "{$this->baseUrl}/api/withdraw",
                'payload' => $requestPayload,
            ]);

            $headers = ['Content-Type' => 'application/json'];

            if ($this->apiKey) {
                $headers['X-API-Key'] = $this->apiKey;
            }

            if ($this->apiSecret) {
                $headers['X-API-Secret'] = $this->apiSecret;
            }

            $response = Http::timeout($this->timeout)
                ->withHeaders($headers)
                ->post("{$this->baseUrl}/api/withdraw", $requestPayload);

            $responseData = $response->json();
            $statusCode = $response->status();

            Log::info('SubadqB createWithdraw response', [
                'status_code' => $statusCode,
                'response' => $responseData,
            ]);

            if ($response->successful()) {
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
                'payload' => $payload,
            ]);

            return SubadqResponse::error(error: $e->getMessage());
        }
    }

    public function parsePixWebhook(array $payload): ?PixNotificationDTO
    {
        if (!isset($payload['type']) || $payload['type'] !== 'pix.status_update') {
            return null;
        }

        if (!isset($payload['data']) || !is_array($payload['data'])) {
            Log::warning('SubadqB parsePixWebhook: Missing or invalid data field', [
                'payload' => $payload,
            ]);
            return null;
        }

        $data = $payload['data'];
        $externalId = $data['id'] ?? null;

        if (!$externalId || !is_string($externalId)) {
            Log::warning('SubadqB parsePixWebhook: Missing or invalid external ID', [
                'data' => $data,
            ]);
            return null;
        }

        $amount = isset($data['value']) ? (float) $data['value'] : 0;
        if ($amount < 0) {
            Log::warning('SubadqB parsePixWebhook: Invalid amount', [
                'amount' => $amount,
                'data' => $data,
            ]);
            return null;
        }

        $status = $data['status'] ?? 'PENDING';
        if (!is_string($status)) {
            Log::warning('SubadqB parsePixWebhook: Invalid status type', [
                'status' => $status,
                'data' => $data,
            ]);
            $status = 'PENDING';
        }

        $payerName = null;
        $payerDocument = null;

        if (isset($data['payer']) && is_array($data['payer'])) {
            $payerName = $data['payer']['name'] ?? null;
            $payerDocument = $data['payer']['document'] ?? null;
        }

        return new PixNotificationDTO(
            externalId: $externalId,
            status: $status,
            amount: $amount,
            payerName: $payerName,
            payerDocument: $payerDocument,
            paymentDate: $data['confirmed_at'] ?? null,
            metadata: ['signature' => $payload['signature'] ?? null],
        );
    }

    public function parseWithdrawWebhook(array $payload): ?WithdrawNotificationDTO
    {
        if (!isset($payload['type']) || $payload['type'] !== 'withdraw.status_update') {
            return null;
        }

        if (!isset($payload['data']) || !is_array($payload['data'])) {
            Log::warning('SubadqB parseWithdrawWebhook: Missing or invalid data field', [
                'payload' => $payload,
            ]);
            return null;
        }

        $data = $payload['data'];
        $externalId = $data['id'] ?? null;

        if (!$externalId || !is_string($externalId)) {
            Log::warning('SubadqB parseWithdrawWebhook: Missing or invalid external ID', [
                'data' => $data,
            ]);
            return null;
        }

        $amount = isset($data['amount']) ? (float) $data['amount'] : 0;
        if ($amount < 0) {
            Log::warning('SubadqB parseWithdrawWebhook: Invalid amount', [
                'amount' => $amount,
                'data' => $data,
            ]);
            return null;
        }

        $status = $data['status'] ?? 'PENDING';
        if (!is_string($status)) {
            Log::warning('SubadqB parseWithdrawWebhook: Invalid status type', [
                'status' => $status,
                'data' => $data,
            ]);
            $status = 'PENDING';
        }

        return new WithdrawNotificationDTO(
            externalId: $externalId,
            transactionId: null,
            status: $status,
            amount: $amount,
            completedAt: $data['processed_at'] ?? null,
            bankInfo: $data['bank_account'] ?? null,
            metadata: ['signature' => $payload['signature'] ?? null],
        );
    }
}
