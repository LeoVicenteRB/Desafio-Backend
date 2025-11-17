<?php

namespace App\Services;

use App\Adapters\Contracts\SubadquirerInterface;
use App\Jobs\ProcessWebhookJob;
use App\Models\Pix;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PixService
{
    public function __construct(
        private readonly SubadquirerService $subadquirerService
    ) {
    }

    /**
     * Create a new PIX transaction.
     *
     * @param array<string, mixed> $data
     */
    public function createPix(User $user, array $data): Pix
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

            // Create PIX record
            $pix = Pix::create([
                'user_id' => $user->id,
                'subadquirer' => $adapter->getName(),
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
                'status' => 'PENDING',
                'metadata' => $data['metadata'] ?? [],
            ]);

            // Send to subadquirer
            $response = $adapter->createPix([
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);

            if ($response->success) {
                $pix->update([
                    'external_pix_id' => $response->externalId,
                    'status' => 'PROCESSING',
                ]);

                // Simulate webhooks - dispatch multiple webhook jobs
                $this->simulateWebhooks($pix, $adapter);
            } else {
                $pix->update([
                    'status' => 'FAILED',
                    'metadata' => array_merge($pix->metadata ?? [], ['error' => $response->error]),
                ]);
            }

            return $pix->fresh();
        });
    }

    /**
     * Simulate webhooks by dispatching multiple jobs.
     */
    private function simulateWebhooks(Pix $pix, SubadquirerInterface $adapter): void
    {
        // Dispatch 3 webhook jobs with slight delays to simulate real scenario
        for ($i = 0; $i < 3; $i++) {
            $webhookPayload = $this->generateWebhookPayload($pix, $adapter, $i);
            
            ProcessWebhookJob::dispatch($webhookPayload, $adapter->getName(), 'pix')
                ->delay(now()->addSeconds($i));
        }
    }

    /**
     * Generate webhook payload for simulation.
     *
     * @return array<string, mixed>
     */
    private function generateWebhookPayload(Pix $pix, SubadquirerInterface $adapter, int $index): array
    {
        if ($adapter->getName() === 'SubadqA') {
            return [
                'event' => 'pix_payment_confirmed',
                'transaction_id' => 'f1a2b3c4d5e6' . $index,
                'pix_id' => $pix->external_pix_id ?? 'PIX' . $pix->id,
                'status' => 'CONFIRMED',
                'amount' => (float) $pix->amount,
                'payer_name' => 'João da Silva',
                'payer_cpf' => '12345678900',
                'payment_date' => now()->toIso8601String(),
                'metadata' => ['source' => 'SubadqA', 'environment' => 'sandbox'],
            ];
        }

        // SubadqB format
        return [
            'type' => 'pix.status_update',
            'data' => [
                'id' => $pix->external_pix_id ?? 'PX' . $pix->id,
                'status' => 'PAID',
                'value' => (float) $pix->amount,
                'payer' => [
                    'name' => 'Maria Oliveira',
                    'document' => '98765432100',
                ],
                'confirmed_at' => now()->toIso8601String(),
            ],
            'signature' => 'd1c4b6f98eaa' . $index,
        ];
    }

    /**
     * Process webhook notification.
     */
    public function processWebhook(array $payload, string $source): void
    {
        $adapter = $this->subadquirerService->resolveAdapter($source);

        if (!$adapter) {
            Log::error('Unknown subadquirer for webhook', ['source' => $source]);
            return;
        }

        $dto = $adapter->parsePixWebhook($payload);

        if (!$dto) {
            Log::warning('Could not parse PIX webhook', ['source' => $source, 'payload' => $payload]);
            return;
        }

        DB::transaction(function () use ($dto, $source) {
            $pix = Pix::where('external_pix_id', $dto->externalId)
                ->where('subadquirer', $source)
                ->lockForUpdate()
                ->first();

            if (!$pix) {
                Log::warning('PIX not found for webhook', [
                    'external_id' => $dto->externalId,
                    'source' => $source,
                ]);
                return;
            }

            // Update PIX status
            $pix->update([
                'status' => $dto->getInternalStatus(),
                'payer_name' => $dto->payerName ?? $pix->payer_name,
                'payer_document' => $dto->payerDocument ?? $pix->payer_document,
                'payment_date' => $dto->paymentDate ? new \DateTime($dto->paymentDate) : $pix->payment_date,
                'metadata' => array_merge($pix->metadata ?? [], $dto->metadata ?? []),
            ]);

            // Dispatch event if confirmed
            if ($pix->isConfirmed()) {
                event(new \App\Events\PixConfirmed($pix));
            }
        });
    }
}

