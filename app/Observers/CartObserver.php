<?php

namespace App\Observers;

use App\Models\Cart;
use Illuminate\Support\Facades\Log;

/**
 * Observer para gerenciar automaticamente carrinhos vazios
 * 
 * Responsabilidades:
 * - Excluir carrinho automaticamente quando ficar vazio
 * - Manter integridade de dados
 * - Log de operações para auditoria
 */
class CartObserver
{
    /**
     * Handle the Cart "updated" event.
     * 
     * Dispara após atualização do carrinho
     * Verifica se ficou vazio e exclui automaticamente
     */
    public function updated(Cart $cart): void
    {
        $this->deleteIfEmpty($cart);
    }

    /**
     * Handle the Cart "deleting" event.
     * 
     * Dispara ANTES de excluir o carrinho
     * Log para auditoria
     */
    public function deleting(Cart $cart): void
    {
        Log::info('🗑️ Carrinho sendo excluído', [
            'cart_id' => $cart->id,
            'user_id' => $cart->user_id,
            'items_count' => $cart->items()->count(),
            'status' => $cart->status
        ]);
    }

    /**
     * Handle the Cart "deleted" event.
     * 
     * Dispara APÓS exclusão do carrinho
     * Log de confirmação
     */
    public function deleted(Cart $cart): void
    {
        Log::info('✅ Carrinho excluído com sucesso', [
            'cart_id' => $cart->id,
            'user_id' => $cart->user_id
        ]);
    }

    /**
     * Verifica se o carrinho está vazio e o exclui automaticamente
     * 
     * @param Cart $cart
     * @return bool True se carrinho foi excluído, False caso contrário
     */
    private function deleteIfEmpty(Cart $cart): bool
    {
        // Recarregar contagem de itens do banco (evita cache)
        $itemsCount = $cart->items()->count();

        // Se carrinho está vazio e ativo, excluir
        if ($itemsCount === 0 && $cart->status === 'active') {
            Log::info('🧹 Carrinho vazio detectado, excluindo automaticamente', [
                'cart_id' => $cart->id,
                'user_id' => $cart->user_id
            ]);

            try {
                // Excluir carrinho vazio
                // CartItems já foram excluídos (cascade)
                $cart->delete();

                return true;
            } catch (\Exception $e) {
                Log::error('❌ Erro ao excluir carrinho vazio', [
                    'cart_id' => $cart->id,
                    'error' => $e->getMessage()
                ]);

                return false;
            }
        }

        return false;
    }
}
