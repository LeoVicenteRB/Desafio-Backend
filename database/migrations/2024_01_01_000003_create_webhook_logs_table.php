<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source')->comment('SubadqA ou SubadqB');
            $table->string('external_id')->comment('ID externo do webhook (transaction_id, pix_id, withdraw_id)');
            $table->string('type')->comment('Tipo: pix ou withdraw');
            $table->json('payload');
            $table->enum('status', ['PENDING', 'PROCESSED', 'FAILED'])->default('PENDING');
            $table->integer('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id', 'type'], 'webhook_unique');
            $table->index(['source', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};

