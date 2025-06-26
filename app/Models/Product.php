<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'product_type_id',
        'productable_id',
        'productable_type',
        'price',
        'stock'
    ];

    // Relacionamento polimórfico
    public function productable(): MorphTo
    {
        return $this->morphTo();
    }

    // Relacionamento com ProductType
    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function getInStockAttribute(): bool {
        return ($this->stock ?? 0) > 0
            || optional($this->productable->vinylSec)->stock > 0;
    }
}
