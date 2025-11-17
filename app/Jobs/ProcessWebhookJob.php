<?php

namespace App\Jobs;

use App\Models\WebhookLog;
use App\Services\PixService;
use App\Services\WithdrawService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly array $payload,
        private readonly string $source,
        private readonly string $type
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(PixService $pixService, WithdrawService $withdrawService): void
    {
        // Get external ID for idempotency check
        $externalId = $this->getExternalId();

        if (!$externalId) {
            Log::warning('Webhook payload missing external ID', [
                'source' => $this->source,
                'type' => $this->type,
                'payload' => $this->payload,
            ]);
            return;
        }

        // Idempotency check - use firstOrCreate with lock
        $webhookLog = DB::transaction(function () use ($externalId) {
            return WebhookLog::firstOrCreate(
                [
                    'source' => $this->source,
                    'external_id' => $externalId,
                    'type' => $this->type,
                ],
                [
                    'payload' => $this->payload,
                    'status' => 'PENDING',
                ]
            );
        });

        // If already processed, skip
        if ($webhookLog->status === 'PROCESSED') {
            Log::info('Webhook already processed, skipping', [
                'source' => $this->source,
                'external_id' => $externalId,
                'type' => $this->type,
            ]);
            return;
        }

        try {
            // Lock the webhook log for update
            $webhookLog = WebhookLog::where('id', $webhookLog->id)
                ->lockForUpdate()
                ->first();

            // Double-check after lock
            if ($webhookLog->status === 'PROCESSED') {
                return;
            }

            // Process webhook based on type
            if ($this->type === 'pix') {
                $pixService->processWebhook($this->payload, $this->source);
            } elseif ($this->type === 'withdraw') {
                $withdrawService->processWebhook($this->payload, $this->source);
            } else {
                throw new \InvalidArgumentException("Unknown webhook type: {$this->type}");
            }

            // Mark as processed
            $webhookLog->markAsProcessed();

            Log::info('Webhook processed successfully', [
                'source' => $this->source,
                'external_id' => $externalId,
                'type' => $this->type,
            ]);
        } catch (\Exception $e) {
            $webhookLog->markAsFailed($e->getMessage());

            Log::error('Webhook processing failed', [
                'source' => $this->source,
                'external_id' => $externalId,
                'type' => $this->type,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get external ID from payload.
     */
    private function getExternalId(): ?string
    {
        // SubadqA format
        if (isset($this->payload['pix_id'])) {
            return $this->payload['pix_id'];
        }
        if (isset($this->payload['withdraw_id'])) {
            return $this->payload['withdraw_id'];
        }
        if (isset($this->payload['transaction_id'])) {
            return $this->payload['transaction_id'];
        }

        // SubadqB format
        if (isset($this->payload['data']['id'])) {
            return $this->payload['data']['id'];
        }

        return null;
    }
}

