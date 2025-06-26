<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ShippingQuote extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cart_id',
        'cep_destino',
        'quote_data',
        'selected_service',
        'expires_at',
    ];

    protected $casts = [
        'quote_data' => 'array',
        'selected_service' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * Relacionamento com usuário
     */
    public function user()
    {
        return $this->belongsTo(ClientUser::class, 'user_id');
    }

    /**
     * Relacionamento com carrinho
     */
    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    /**
     * Accessor para CEP formatado
     */
    public function getFormattedCepDestinoAttribute()
    {
        if (!$this->cep_destino) return null;
        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $this->cep_destino);
    }

    /**
     * Mutator para CEP (remove formatação)
     */
    public function setCepDestinoAttribute($value)
    {
        $this->attributes['cep_destino'] = preg_replace('/[^0-9]/', '', $value);
    }

    /**
     * Verificar se a cotação ainda é válida
     */
    public function isValid()
    {
        return $this->expires_at && $this->expires_at->isFuture();
    }

    /**
     * Scope para cotações válidas
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope para cotações expiradas
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope para cotações de um usuário
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para cotações de um carrinho
     */
    public function scopeForCart($query, $cartId)
    {
        return $query->where('cart_id', $cartId);
    }

    /**
     * Definir validade padrão (24 horas)
     */
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->expires_at) {
                $model->expires_at = Carbon::now()->addHours(24);
            }
        });
    }
}
