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
        Schema::create('shipping_quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->unsignedBigInteger('cart_id');
            $table->string('cep_destino', 9); // Formato: 12345-678
            $table->json('quote_data'); // Resposta completa do Melhor Envio
            $table->json('selected_service')->nullable(); // Serviço selecionado pelo usuário
            $table->timestamp('expires_at'); // Validade da cotação
            $table->timestamps();

            // Índices
            $table->index(['user_id', 'cart_id']);
            $table->index('cep_destino');
            $table->index('expires_at');

            // Foreign Keys
            $table->foreign('user_id')->references('id')->on('client_users')->onDelete('cascade');
            $table->foreign('cart_id')->references('id')->on('carts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_quotes');
    }
};
