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

    public function removeItem(int $productId): bool
    {
        return $this->items()->where('product_id', $productId)->delete() > 0;
    }

    public function hasItem(int $productId): bool
    {
        return $this->items()->where('product_id', $productId)->exists();
    }

    public function clear(): bool
    {
        return $this->items()->delete() > 0;
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
