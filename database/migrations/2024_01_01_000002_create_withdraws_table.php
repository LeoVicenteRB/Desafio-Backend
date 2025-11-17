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
        Schema::create('withdraws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('subadquirer')->comment('Subadquirente usada (SubadqA ou SubadqB)');
            $table->string('external_withdraw_id')->nullable()->comment('ID retornado pela subadquirente');
            $table->string('transaction_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['PENDING', 'PROCESSING', 'SUCCESS', 'DONE', 'FAILED', 'CANCELLED'])->default('PENDING');
            $table->json('bank_info')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('external_withdraw_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdraws');
    }
};

