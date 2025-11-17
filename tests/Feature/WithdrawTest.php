<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WithdrawTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_withdraw_successfully(): void
    {
        $user = User::factory()->create(['subadquirer' => 'SubadqB']);
        Sanctum::actingAs($user);

        Http::fake([
            '*' => Http::response([
                'id' => 'WDX54321',
                'status' => 'PENDING',
            ], 200),
        ]);

        $response = $this->postJson('/api/withdraw', [
            'amount' => 500.00,
            'bank' => [
                'bank' => 'Itaú',
                'agency' => '0001',
                'account' => '1234567-8',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'external_withdraw_id',
                    'subadquirer',
                    'amount',
                    'status',
                    'bank_info',
                ],
            ]);

        $this->assertDatabaseHas('withdraws', [
            'user_id' => $user->id,
            'amount' => 500.00,
            'subadquirer' => 'SubadqB',
        ]);
    }

    public function test_withdraw_creation_fails_with_invalid_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/withdraw', [
            'amount' => 500.00,
            // Missing bank
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bank']);
    }

    public function test_withdraw_webhook_processing_updates_status(): void
    {
        $user = User::factory()->create(['subadquirer' => 'SubadqB']);
        $withdraw = \App\Models\Withdraw::create([
            'user_id' => $user->id,
            'subadquirer' => 'SubadqB',
            'external_withdraw_id' => 'WDX54321',
            'amount' => 850.00,
            'status' => 'PROCESSING',
            'bank_info' => ['bank' => 'Nubank'],
        ]);

        $webhookPayload = [
            'type' => 'withdraw.status_update',
            'data' => [
                'id' => 'WDX54321',
                'status' => 'DONE',
                'amount' => 850.00,
                'bank_account' => [
                    'bank' => 'Nubank',
                    'agency' => '0001',
                    'account' => '1234567-8',
                ],
                'processed_at' => now()->toIso8601String(),
            ],
            'signature' => 'aabbccddeeff112233',
        ];

        $job = new \App\Jobs\ProcessWebhookJob($webhookPayload, 'SubadqB', 'withdraw');
        $job->handle(
            app(\App\Services\PixService::class),
            app(\App\Services\WithdrawService::class)
        );

        $withdraw->refresh();
        $this->assertEquals('SUCCESS', $withdraw->status);
        $this->assertNotNull($withdraw->completed_at);
    }
}

