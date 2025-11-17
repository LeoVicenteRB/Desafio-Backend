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
        Schema::create('pix', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('subadquirer')->comment('Subadquirente usada (SubadqA ou SubadqB)');
            $table->string('external_pix_id')->nullable()->comment('ID retornado pela subadquirente');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['PENDING', 'PROCESSING', 'CONFIRMED', 'PAID', 'FAILED', 'CANCELLED'])->default('PENDING');
            $table->string('payer_name')->nullable();
            $table->string('payer_document')->nullable();
            $table->string('reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('payment_date')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('external_pix_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pix');
    }
};

