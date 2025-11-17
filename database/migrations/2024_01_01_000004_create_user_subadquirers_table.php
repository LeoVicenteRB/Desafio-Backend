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
        Schema::create('user_subadquirers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('subadquirer')->comment('Subadquirente: SubadqA ou SubadqB');
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable()->comment('Configurações específicas do usuário para esta subadquirente');
            $table->timestamps();

            $table->unique(['user_id', 'subadquirer']);
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_subadquirers');
    }
};

