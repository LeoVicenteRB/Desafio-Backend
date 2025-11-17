<?php

namespace App\Services;

use App\Adapters\Contracts\SubadquirerInterface;
use App\Jobs\ProcessWebhookJob;
use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WithdrawService
{
    public function __construct(
        private readonly SubadquirerService $subadquirerService
    ) {
    }

    /**
     * Create a new withdraw request.
     *
     * @param array<string, mixed> $data
     */
    public function createWithdraw(User $user, array $data): Withdraw
    {
        $adapter = $this->subadquirerService->getAdapterForUser($user);

        if (!$adapter) {
            throw new \RuntimeException('No subadquirer configured for user');
        }

        return DB::transaction(function () use ($user, $adapter, $data) {
            // Validar amount antes de prosseguir
            $amount = (float) ($data['amount'] ?? 0);
            
            if ($amount <= 0) {
                throw new \InvalidArgumentException('Amount must be greater than 0');
            }

            // Create withdraw record
            $withdraw = Withdraw::create([
                'user_id' => $user->id,
                'subadquirer' => $adapter->getName(),
                'amount' => $amount,
                'status' => 'PENDING',
                'bank_info' => $data['bank'] ?? [],
                'metadata' => $data['metadata'] ?? [],
                'requested_at' => now(),
            ]);

            // Send to subadquirer
            $response = $adapter->createWithdraw([
                'amount' => $amount,
                'bank' => $data['bank'] ?? [],
                'metadata' => $data['metadata'] ?? [],
            ]);

            if ($response->success) {
                $withdraw->update([
                    'external_withdraw_id' => $response->externalId,
                    'status' => 'PROCESSING',
                ]);

                // Simulate webhooks - dispatch multiple webhook jobs
                $this->simulateWebhooks($withdraw, $adapter);
            } else {
                $withdraw->update([
                    'status' => 'FAILED',
                    'metadata' => array_merge($withdraw->metadata ?? [], ['error' => $response->error]),
                ]);
            }

            return $withdraw->fresh();
        });
    }

    /**
     * Simulate webhooks by dispatching multiple jobs.
     */
    private function simulateWebhooks(Withdraw $withdraw, SubadquirerInterface $adapter): void
    {
        // Dispatch 3 webhook jobs with slight delays to simulate real scenario
        for ($i = 0; $i < 3; $i++) {
            $webhookPayload = $this->generateWebhookPayload($withdraw, $adapter, $i);
            
            ProcessWebhookJob::dispatch($webhookPayload, $adapter->getName(), 'withdraw')
                ->delay(now()->addSeconds($i));
        }
    }

    /**
     * Generate webhook payload for simulation.
     *
     * @return array<string, mixed>
     */
    private function generateWebhookPayload(Withdraw $withdraw, SubadquirerInterface $adapter, int $index): array
    {
        if ($adapter->getName() === 'SubadqA') {
            return [
                'event' => 'withdraw_completed',
                'withdraw_id' => $withdraw->external_withdraw_id ?? 'WD' . $withdraw->id,
                'transaction_id' => 'T' . $withdraw->id . $index,
                'status' => 'SUCCESS',
                'amount' => (float) $withdraw->amount,
                'requested_at' => $withdraw->requested_at?->toIso8601String(),
                'completed_at' => now()->toIso8601String(),
                'metadata' => [
                    'source' => 'SubadqA',
                    'destination_bank' => $withdraw->bank_info['bank'] ?? 'Itaú',
                ],
            ];
        }

        // SubadqB format
        return [
            'type' => 'withdraw.status_update',
            'data' => [
                'id' => $withdraw->external_withdraw_id ?? 'WDX' . $withdraw->id,
                'status' => 'DONE',
                'amount' => (float) $withdraw->amount,
                'bank_account' => $withdraw->bank_info ?? [
                    'bank' => 'Nubank',
                    'agency' => '0001',
                    'account' => '1234567-8',
                ],
                'processed_at' => now()->toIso8601String(),
            ],
            'signature' => 'aabbccddeeff112233' . $index,
        ];
    }

    /**
     * Process webhook notification.
     */
    public function processWebhook(array $payload, string $source): void
    {
        if (empty($payload) || !is_array($payload)) {
            Log::error('WithdrawService processWebhook: Invalid payload', [
                'source' => $source,
                'payload' => $payload,
            ]);
            return;
        }

        $adapter = $this->subadquirerService->resolveAdapter($source);

        if (!$adapter) {
            Log::error('WithdrawService processWebhook: Unknown subadquirer', [
                'source' => $source,
            ]);
            return;
        }

        $dto = $adapter->parseWithdrawWebhook($payload);

        if (!$dto) {
            Log::warning('WithdrawService processWebhook: Could not parse webhook', [
                'source' => $source,
                'payload' => $payload,
            ]);
            return;
        }

        if (empty($dto->externalId)) {
            Log::error('WithdrawService processWebhook: Missing external ID in DTO', [
                'source' => $source,
                'dto' => $dto,
            ]);
            return;
        }

        DB::transaction(function () use ($dto, $source) {
            $withdraw = Withdraw::where('external_withdraw_id', $dto->externalId)
                ->where('subadquirer', $source)
                ->lockForUpdate()
                ->first();

            if (!$withdraw) {
                Log::warning('WithdrawService processWebhook: Withdraw not found', [
                    'external_id' => $dto->externalId,
                    'source' => $source,
                ]);
                return;
            }

            $withdraw->update([
                'status' => $dto->getInternalStatus(),
                'transaction_id' => $dto->transactionId ?? $withdraw->transaction_id,
                'completed_at' => $dto->completedAt ? new \DateTime($dto->completedAt) : $withdraw->completed_at,
                'bank_info' => $dto->bankInfo ?? $withdraw->bank_info,
                'metadata' => array_merge($withdraw->metadata ?? [], $dto->metadata ?? []),
            ]);

            if ($withdraw->isCompleted()) {
                event(new \App\Events\WithdrawCompleted($withdraw));
            }
        });
    }
}

