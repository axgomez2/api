<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Exceptions\ProductNotAvailableException; // Criaremos esta exceção
use App\Exceptions\ProductAlreadyInCartException; // Criaremos esta exceção

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status'
    ];

    protected $appends = ['total_items', 'total_amount'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(ClientUser::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function getTotalItemsAttribute(): int
    {
        return $this->items()->count();
    }

    public function getTotalAmountAttribute(): float
    {
        return $this->items->sum(function($item) {
            return $item->price; // Usa o accesor 'price' do CartItem
        });
    }

    /**
     * Adiciona um produto ao carrinho.
     *
     * @param Product $product
     * @return CartItem
     * @throws ProductNotAvailableException
     * @throws ProductAlreadyInCartException
     */
    public function addItem(Product $product): CartItem
    {
        $qtyInStock = $product->productable->vinylSec->stock
                  ?? $product->stock
                  ?? 0;

    if ($qtyInStock <= 0) {
        throw new ProductNotAvailableException('Produto não está disponível em estoque.');
    }

    if ($this->hasItem($product->id)) {
        throw new ProductAlreadyInCartException('Produto já está no carrinho.');
    }

    return $this->items()->create([
        'product_id' => $product->id,
        'quantity' => 1
    ]);
    }

    /**
     * Remove um item do carrinho
     * Se o carrinho ficar vazio, será excluído automaticamente pelo Observer
     * 
     * @param int $productId
     * @return bool
     */
    public function removeItem(int $productId): bool
    {
        $deleted = $this->items()->where('product_id', $productId)->delete() > 0;
        
        if ($deleted) {
            // Recarregar relacionamento para refletir mudança
            $this->load('items');
            
            // Observer irá verificar e excluir se vazio
            $this->touch(); // Dispara evento 'updated'
        }
        
        return $deleted;
    }

    public function hasItem(int $productId): bool
    {
        return $this->items()->where('product_id', $productId)->exists();
    }

    /**
     * Limpa todos os itens do carrinho
     * O carrinho será excluído automaticamente após limpar (Observer)
     * 
     * @return bool
     */
    public function clear(): bool
    {
        $itemsCount = $this->items()->count();
        
        if ($itemsCount === 0) {
            return false; // Já está vazio
        }
        
        $deleted = $this->items()->delete() > 0;
        
        if ($deleted) {
            // Recarregar relacionamento
            $this->load('items');
            
            // Observer irá excluir o carrinho vazio
            $this->touch(); // Dispara evento 'updated'
        }
        
        return $deleted;
    }
    
    /**
     * Verifica se o carrinho está vazio
     * 
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->items()->count() === 0;
    }
    
    /**
     * Exclui o carrinho se estiver vazio
     * Método público para uso manual (Observer faz automaticamente)
     * 
     * @return bool True se foi excluído, False se não estava vazio ou erro
     */
    public function deleteIfEmpty(): bool
    {
        if ($this->isEmpty() && $this->status === 'active') {
            try {
                $this->delete();
                return true;
            } catch (\Exception $e) {
                \Log::error('Erro ao excluir carrinho vazio: ' . $e->getMessage());
                return false;
            }
        }
        
        return false;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public static function getActiveForUser($userId): self
    {
        return self::active()->forUser($userId)->firstOrCreate([
            'user_id' => $userId,
            'status' => 'active'
        ]);
    }
}
