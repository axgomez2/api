<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adiciona campos para rastrear carrinhos convertidos em pedidos
     * Em vez de deletar, marcamos como "converted"
     */
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            // Timestamp de quando foi convertido em pedido
            $table->timestamp('converted_at')->nullable()->after('status');
            
            // Referência bidirecional: qual pedido foi criado a partir deste carrinho
            $table->foreignId('order_id')
                  ->nullable()
                  ->after('converted_at')
                  ->constrained('orders')
                  ->onDelete('set null');
            
            // Índice para consultas
            $table->index('converted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn(['converted_at', 'order_id']);
        });
    }
};
