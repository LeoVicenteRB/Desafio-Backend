<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWebhookJob;
use App\Models\Pix;
use App\Models\Withdraw;
use App\Services\SubadquirerService;
use Illuminate\Console\Command;

class SimulateWebhooksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'simulate:webhooks 
                            {id : The ID of the PIX or Withdraw}
                            {--type=pix : Type of transaction (pix or withdraw)}
                            {--count=10 : Number of webhooks to simulate}
                            {--rate=3 : Rate per second}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate multiple webhooks for a PIX or Withdraw transaction';

    /**
     * Execute the console command.
     */
    public function handle(SubadquirerService $subadquirerService): int
    {
        $id = $this->argument('id');
        $type = $this->option('type');
        $count = (int) $this->option('count');
        $rate = (int) $this->option('rate');

        if (!in_array($type, ['pix', 'withdraw'])) {
            $this->error('Type must be either "pix" or "withdraw"');
            return Command::FAILURE;
        }

        // Find the transaction
        if ($type === 'pix') {
            $transaction = Pix::find($id);
        } else {
            $transaction = Withdraw::find($id);
        }

        if (!$transaction) {
            $this->error("{$type} with ID {$id} not found");
            return Command::FAILURE;
        }

        $adapter = $subadquirerService->resolveAdapter($transaction->subadquirer);

        if (!$adapter) {
            $this->error("Adapter for {$transaction->subadquirer} not found");
            return Command::FAILURE;
        }

        $this->info("Simulating {$count} webhooks for {$type} #{$id} at rate of {$rate}/s");

        $delay = 0;
        $batchSize = $rate;
        $batches = ceil($count / $batchSize);

        for ($batch = 0; $batch < $batches; $batch++) {
            $batchStartTime = now()->addSeconds($delay);
            $itemsInBatch = min($batchSize, $count - ($batch * $batchSize));

            for ($i = 0; $i < $itemsInBatch; $i++) {
                $webhookPayload = $this->generateWebhookPayload($transaction, $adapter, $batch * $batchSize + $i);
                
                // Calculate delay: each item in batch spaced by (1/rate) seconds
                $itemDelay = $batchStartTime->copy()->addMilliseconds(($i / $rate) * 1000);
                
                ProcessWebhookJob::dispatch($webhookPayload, $adapter->getName(), $type)
                    ->delay($itemDelay);
            }

            $delay += 1; // Next batch after 1 second
        }

        $this->info("Dispatched {$count} webhook jobs. Process them with: php artisan queue:work");

        return Command::SUCCESS;
    }

    /**
     * Generate webhook payload.
     *
     * @param Pix|Withdraw $transaction
     * @return array<string, mixed>
     */
    private function generateWebhookPayload($transaction, $adapter, int $index): array
    {
        if ($transaction instanceof Pix) {
            if ($adapter->getName() === 'SubadqA') {
                return [
                    'event' => 'pix_payment_confirmed',
                    'transaction_id' => 'f1a2b3c4d5e6' . $index,
                    'pix_id' => $transaction->external_pix_id ?? 'PIX' . $transaction->id,
                    'status' => 'CONFIRMED',
                    'amount' => (float) $transaction->amount,
                    'payer_name' => 'João da Silva',
                    'payer_cpf' => '12345678900',
                    'payment_date' => now()->toIso8601String(),
                    'metadata' => ['source' => 'SubadqA', 'environment' => 'sandbox'],
                ];
            }

            // SubadqB
            return [
                'type' => 'pix.status_update',
                'data' => [
                    'id' => $transaction->external_pix_id ?? 'PX' . $transaction->id,
                    'status' => 'PAID',
                    'value' => (float) $transaction->amount,
                    'payer' => [
                        'name' => 'Maria Oliveira',
                        'document' => '98765432100',
                    ],
                    'confirmed_at' => now()->toIso8601String(),
                ],
                'signature' => 'd1c4b6f98eaa' . $index,
            ];
        }

        // Withdraw
        if ($adapter->getName() === 'SubadqA') {
            return [
                'event' => 'withdraw_completed',
                'withdraw_id' => $transaction->external_withdraw_id ?? 'WD' . $transaction->id,
                'transaction_id' => 'T' . $transaction->id . $index,
                'status' => 'SUCCESS',
                'amount' => (float) $transaction->amount,
                'requested_at' => $transaction->requested_at?->toIso8601String(),
                'completed_at' => now()->toIso8601String(),
                'metadata' => [
                    'source' => 'SubadqA',
                    'destination_bank' => $transaction->bank_info['bank'] ?? 'Itaú',
                ],
            ];
        }

        // SubadqB
        return [
            'type' => 'withdraw.status_update',
            'data' => [
                'id' => $transaction->external_withdraw_id ?? 'WDX' . $transaction->id,
                'status' => 'DONE',
                'amount' => (float) $transaction->amount,
                'bank_account' => $transaction->bank_info ?? [
                    'bank' => 'Nubank',
                    'agency' => '0001',
                    'account' => '1234567-8',
                ],
                'processed_at' => now()->toIso8601String(),
            ],
            'signature' => 'aabbccddeeff112233' . $index,
        ];
    }
}

