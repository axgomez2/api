<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'vinyl_id',
        'product_snapshot',
        'product_name',
        'product_sku',
        'product_image',
        'quantity',
        'unit_price',
        'promotional_price',
        'total_price',
        'artist_name',
        'album_title',
        'vinyl_condition',
        'cover_condition',
    ];

    protected $casts = [
        'product_snapshot' => 'array',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'promotional_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    // Relacionamentos
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vinyl(): BelongsTo
    {
        return $this->belongsTo(VinylSec::class, 'vinyl_id');
    }

    // Métodos auxiliares para o front-end
    public function getFinalPrice(): float
    {
        return $this->promotional_price ?? $this->unit_price;
    }

    public function getFormattedPrice(): string
    {
        return 'R$ ' . number_format($this->getFinalPrice(), 2, ',', '.');
    }

    public function getFormattedUnitPrice(): string
    {
        return 'R$ ' . number_format($this->unit_price, 2, ',', '.');
    }

    public function getFormattedTotal(): string
    {
        return 'R$ ' . number_format($this->total_price, 2, ',', '.');
    }

    public function hasPromotion(): bool
    {
        return !is_null($this->promotional_price);
    }

    public function getDiscountAmount(): float
    {
        if (!$this->hasPromotion()) {
            return 0;
        }

        return ($this->unit_price - $this->promotional_price) * $this->quantity;
    }

    public function getDiscountPercentage(): float
    {
        if (!$this->hasPromotion()) {
            return 0;
        }

        return (($this->unit_price - $this->promotional_price) / $this->unit_price) * 100;
    }

    public function getFormattedDiscountPercentage(): string
    {
        if (!$this->hasPromotion()) {
            return '';
        }

        return number_format($this->getDiscountPercentage(), 0) . '% OFF';
    }

    // Serialização para API
    public function toArray()
    {
        $array = parent::toArray();

        // Adiciona campos calculados
        $array['final_price'] = $this->getFinalPrice();
        $array['formatted_price'] = $this->getFormattedPrice();
        $array['formatted_unit_price'] = $this->getFormattedUnitPrice();
        $array['formatted_total'] = $this->getFormattedTotal();
        $array['has_promotion'] = $this->hasPromotion();
        $array['discount_amount'] = $this->getDiscountAmount();
        $array['discount_percentage'] = $this->getDiscountPercentage();
        $array['formatted_discount_percentage'] = $this->getFormattedDiscountPercentage();

        return $array;
    }
}
