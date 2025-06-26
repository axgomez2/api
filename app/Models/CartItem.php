<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity'
    ];

    protected $appends = ['price'];

    // Relacionamentos
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->with([
            'productable.artists',
            'productable.vinylSec'
        ]);
    }

    // Accessor para preço do item
    public function getPriceAttribute(): float
    {
        if (!$this->product) {
            return 0;
        }

        // Tentar pegar o preço do vinylSec primeiro
        if ($this->product->productable && $this->product->productable->vinylSec) {
            return (float) $this->product->productable->vinylSec->price;
        }

        // Fallback para preço direto do produto
        return (float) ($this->product->price ?? 0);
    }

    // Verificar se produto ainda está disponível
    public function isAvailable(): bool
    {
        if (!$this->product) {
            return false;
        }

        return $this->product->in_stock;
    }
}
