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

    public function __construct(
        private readonly array $payload,
        private readonly string $source,
        private readonly string $type
    ) {
    }

    public function handle(PixService $pixService, WithdrawService $withdrawService): void
    {
        if (!in_array($this->type, ['pix', 'withdraw'], true)) {
            Log::error('ProcessWebhookJob: Invalid webhook type', [
                'type' => $this->type,
                'source' => $this->source,
            ]);
            return;
        }

        if (empty($this->payload) || !is_array($this->payload)) {
            Log::error('ProcessWebhookJob: Invalid payload', [
                'source' => $this->source,
                'type' => $this->type,
            ]);
            return;
        }

        $externalId = $this->getExternalId();

        if (!$externalId || !is_string($externalId)) {
            Log::warning('ProcessWebhookJob: Missing or invalid external ID', [
                'source' => $this->source,
                'type' => $this->type,
                'payload' => $this->payload,
            ]);
            return;
        }

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

        if ($webhookLog->status === 'PROCESSED') {
            Log::info('ProcessWebhookJob: Webhook already processed', [
                'source' => $this->source,
                'external_id' => $externalId,
                'type' => $this->type,
            ]);
            return;
        }

        try {
            $webhookLog = WebhookLog::where('id', $webhookLog->id)
                ->lockForUpdate()
                ->first();

            if ($webhookLog->status === 'PROCESSED') {
                return;
            }

            if ($this->type === 'pix') {
                $pixService->processWebhook($this->payload, $this->source);
            } else {
                $withdrawService->processWebhook($this->payload, $this->source);
            }

            $webhookLog->markAsProcessed();

            Log::info('ProcessWebhookJob: Webhook processed successfully', [
                'source' => $this->source,
                'external_id' => $externalId,
                'type' => $this->type,
            ]);
        } catch (\Exception $e) {
            $webhookLog->markAsFailed($e->getMessage());

            Log::error('ProcessWebhookJob: Webhook processing failed', [
                'source' => $this->source,
                'external_id' => $externalId,
                'type' => $this->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function getExternalId(): ?string
    {
        if (isset($this->payload['pix_id']) && is_string($this->payload['pix_id'])) {
            return $this->payload['pix_id'];
        }

        if (isset($this->payload['withdraw_id']) && is_string($this->payload['withdraw_id'])) {
            return $this->payload['withdraw_id'];
        }

        if (isset($this->payload['transaction_id']) && is_string($this->payload['transaction_id'])) {
            return $this->payload['transaction_id'];
        }

        if (isset($this->payload['data']['id']) && is_string($this->payload['data']['id'])) {
            return $this->payload['data']['id'];
        }

        return null;
    }
}
