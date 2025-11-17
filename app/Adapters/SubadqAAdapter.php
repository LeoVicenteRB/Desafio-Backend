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
        private readonly int $timeout = 30
    ) {
    }

    public function getName(): string
    {
        return 'SubadqA';
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
                Log::warning('SubadqA createPix: Invalid amount', [
                    'amount' => $amount,
                    'payload' => $payload,
                ]);
                return SubadqResponse::error(
                    error: 'Amount must be greater than 0',
                    data: ['amount' => $amount]
                );
            }

            $amountInCents = (int) round($amount * 100);
            
            if ($amountInCents <= 0) {
                return SubadqResponse::error(
                    error: 'Invalid amount: must be greater than 0',
                    data: ['amount' => $amount, 'amount_in_cents' => $amountInCents]
                );
            }

            $requestPayload = [
                'merchant_id' => config('services.subadq_a.merchant_id', 'default_merchant'),
                'amount' => $amountInCents,
                'currency' => 'BRL',
                'order_id' => $payload['reference'] ?? 'order_' . time(),
            ];

            if (isset($payload['metadata']['payer'])) {
                $requestPayload['payer'] = $payload['metadata']['payer'];
            } elseif (isset($payload['payer'])) {
                $requestPayload['payer'] = $payload['payer'];
            }

            $requestPayload['expires_in'] = (int) (
                $payload['expires_in'] 
                ?? $payload['metadata']['expires_in'] 
                ?? 3600
            );

            Log::info('SubadqA createPix request', [
                'url' => "{$this->baseUrl}/pix/create",
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
                ->withBody(json_encode($requestPayload, JSON_UNESCAPED_SLASHES), 'application/json')
                ->post("{$this->baseUrl}/pix/create");

            $responseData = $response->json();
            $statusCode = $response->status();

            Log::info('SubadqA createPix response', [
                'status_code' => $statusCode,
                'response' => $responseData,
            ]);

            if ($response->successful()) {
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
                Log::warning('SubadqA createWithdraw: Invalid amount', [
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
                $requestPayload['bank'] = $payload['bank'];
            }

            if (isset($payload['metadata']) && !empty($payload['metadata'])) {
                $requestPayload['metadata'] = $payload['metadata'];
            }

            Log::info('SubadqA createWithdraw request', [
                'url' => "{$this->baseUrl}/withdraw/create",
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
                ->post("{$this->baseUrl}/withdraw/create", $requestPayload);

            $responseData = $response->json();
            $statusCode = $response->status();

            Log::info('SubadqA createWithdraw response', [
                'status_code' => $statusCode,
                'response' => $responseData,
            ]);

            if ($response->successful()) {
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
                'payload' => $payload,
            ]);

            return SubadqResponse::error(error: $e->getMessage());
        }
    }

    public function parsePixWebhook(array $payload): ?PixNotificationDTO
    {
        if (!isset($payload['event']) || $payload['event'] !== 'pix_payment_confirmed') {
            return null;
        }

        $externalId = $payload['pix_id'] ?? $payload['transaction_id'] ?? null;
        if (!$externalId || !is_string($externalId)) {
            Log::warning('SubadqA parsePixWebhook: Missing or invalid external ID', [
                'payload' => $payload,
            ]);
            return null;
        }

        $amountInCents = isset($payload['amount']) ? (int) $payload['amount'] : 0;
        if ($amountInCents < 0) {
            Log::warning('SubadqA parsePixWebhook: Invalid amount', [
                'amount' => $amountInCents,
                'payload' => $payload,
            ]);
            return null;
        }

        $amountInReais = $amountInCents / 100;
        $status = $payload['status'] ?? 'PENDING';

        if (!is_string($status)) {
            Log::warning('SubadqA parsePixWebhook: Invalid status type', [
                'status' => $status,
                'payload' => $payload,
            ]);
            $status = 'PENDING';
        }

        $payerName = null;
        $payerDocument = null;

        if (isset($payload['payer']) && is_array($payload['payer'])) {
            $payerName = $payload['payer']['name'] ?? null;
            $payerDocument = $payload['payer']['cpf_cnpj'] ?? null;
        } else {
            $payerName = $payload['payer_name'] ?? null;
            $payerDocument = $payload['payer_cpf'] ?? null;
        }

        return new PixNotificationDTO(
            externalId: $externalId,
            status: $status,
            amount: (float) $amountInReais,
            payerName: $payerName,
            payerDocument: $payerDocument,
            paymentDate: $payload['payment_date'] ?? null,
            metadata: $payload['metadata'] ?? null,
        );
    }

    public function parseWithdrawWebhook(array $payload): ?WithdrawNotificationDTO
    {
        if (!isset($payload['event']) || $payload['event'] !== 'withdraw_completed') {
            return null;
        }

        $externalId = $payload['withdraw_id'] ?? null;
        if (!$externalId || !is_string($externalId)) {
            Log::warning('SubadqA parseWithdrawWebhook: Missing or invalid external ID', [
                'payload' => $payload,
            ]);
            return null;
        }

        $amountInCents = isset($payload['amount']) ? (int) $payload['amount'] : 0;
        if ($amountInCents < 0) {
            Log::warning('SubadqA parseWithdrawWebhook: Invalid amount', [
                'amount' => $amountInCents,
                'payload' => $payload,
            ]);
            return null;
        }

        $amountInReais = $amountInCents / 100;
        $status = $payload['status'] ?? 'PENDING';

        if (!is_string($status)) {
            Log::warning('SubadqA parseWithdrawWebhook: Invalid status type', [
                'status' => $status,
                'payload' => $payload,
            ]);
            $status = 'PENDING';
        }

        $bankInfo = null;
        if (isset($payload['metadata']['destination_bank']) && !empty($payload['metadata']['destination_bank'])) {
            $bankInfo = ['bank' => $payload['metadata']['destination_bank']];
        }

        return new WithdrawNotificationDTO(
            externalId: $externalId,
            transactionId: $payload['transaction_id'] ?? null,
            status: $status,
            amount: (float) $amountInReais,
            completedAt: $payload['completed_at'] ?? null,
            bankInfo: $bankInfo,
            metadata: $payload['metadata'] ?? null,
        );
    }
}
