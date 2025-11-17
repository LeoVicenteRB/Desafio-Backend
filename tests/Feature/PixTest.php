<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PixTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_pix_successfully(): void
    {
        $user = User::factory()->create(['subadquirer' => 'SubadqA']);
        Sanctum::actingAs($user);

        Http::fake([
            '*' => Http::response([
                'pix_id' => 'PIX123456789',
                'status' => 'PENDING',
            ], 200),
        ]);

        $response = $this->postJson('/api/pix', [
            'amount' => 125.50,
            'reference' => 'order-123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'external_pix_id',
                    'subadquirer',
                    'amount',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('pix', [
            'user_id' => $user->id,
            'amount' => 125.50,
            'reference' => 'order-123',
            'subadquirer' => 'SubadqA',
        ]);
    }

    public function test_pix_creation_fails_with_invalid_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/pix', [
            'amount' => -10,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_pix_creation_fails_when_subadquirer_returns_error(): void
    {
        $user = User::factory()->create(['subadquirer' => 'SubadqA']);
        Sanctum::actingAs($user);

        Http::fake([
            '*' => Http::response([
                'message' => 'Insufficient funds',
            ], 400),
        ]);

        $response = $this->postJson('/api/pix', [
            'amount' => 125.50,
            'reference' => 'order-123',
        ]);

        $response->assertStatus(201); // Still returns 201, but status is FAILED

        $this->assertDatabaseHas('pix', [
            'user_id' => $user->id,
            'status' => 'FAILED',
        ]);
    }

    public function test_pix_webhook_processing_is_idempotent(): void
    {
        $user = User::factory()->create(['subadquirer' => 'SubadqA']);
        $pix = \App\Models\Pix::create([
            'user_id' => $user->id,
            'subadquirer' => 'SubadqA',
            'external_pix_id' => 'PIX123456789',
            'amount' => 125.50,
            'status' => 'PROCESSING',
        ]);

        $webhookPayload = [
            'event' => 'pix_payment_confirmed',
            'transaction_id' => 'f1a2b3c4d5e6',
            'pix_id' => 'PIX123456789',
            'status' => 'CONFIRMED',
            'amount' => 125.50,
            'payer_name' => 'João da Silva',
            'payer_cpf' => '12345678900',
            'payment_date' => now()->toIso8601String(),
            'metadata' => ['source' => 'SubadqA'],
        ];

        // Process webhook first time
        $job = new \App\Jobs\ProcessWebhookJob($webhookPayload, 'SubadqA', 'pix');
        $job->handle(
            app(\App\Services\PixService::class),
            app(\App\Services\WithdrawService::class)
        );

        $pix->refresh();
        $this->assertEquals('CONFIRMED', $pix->status);

        // Process same webhook again (should be idempotent)
        $job2 = new \App\Jobs\ProcessWebhookJob($webhookPayload, 'SubadqA', 'pix');
        $job2->handle(
            app(\App\Services\PixService::class),
            app(\App\Services\WithdrawService::class)
        );

        // Status should remain the same
        $pix->refresh();
        $this->assertEquals('CONFIRMED', $pix->status);

        // Should have only one processed webhook log
        $this->assertDatabaseCount('webhook_logs', 1);
        $this->assertDatabaseHas('webhook_logs', [
            'source' => 'SubadqA',
            'external_id' => 'PIX123456789',
            'type' => 'pix',
            'status' => 'PROCESSED',
        ]);
    }
}

