<?php

// app/Models/ClientUser.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Notifications\ClientVerifyEmail;

class ClientUser extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    protected $table = 'client_users';

    /**
     * Indica que o ID não é auto-incremento
     */
    public $incrementing = false;

    /**
     * O tipo de dados da chave primária
     */
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'email', 'password', 'google_id', 'phone', 'cpf', 'birth_date'
    ];

    protected $hidden = [
        'password', 'remember_token'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'id' => 'string',
        'birth_date' => 'date',
        'password' => 'hashed',
    ];

    /**
     * Get the user's wishlist items
     */
    public function wishlistItems(): HasMany
    {
        return $this->hasMany(Wishlist::class, 'user_id');
    }

    /**
     * Get the user's wantlist items
     */
    public function wantlistItems(): HasMany
    {
        return $this->hasMany(Wantlist::class, 'user_id');
    }

    /**
     * Get the user's cart
     */
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class, 'user_id');
    }

    /**
     * Get the user's cart items directly
     */
    public function cartItems(): HasMany
    {
        return $this->hasManyThrough(CartItem::class, Cart::class, 'user_id', 'cart_id');
    }

    /**
     * Relacionamento com endereços
     */
    public function addresses()
    {
        return $this->hasMany(Address::class, 'user_id');
    }

    /**
     * Endereço padrão do usuário
     */
    public function defaultAddress()
    {
        return $this->hasOne(Address::class, 'user_id')->where('is_default', true);
    }

    /**
     * Relacionamento com quotes de frete
     */
    public function shippingQuotes()
    {
        return $this->hasMany(ShippingQuote::class, 'user_id');
    }

    /**
     * Accessor para CPF formatado
     */
    public function getFormattedCpfAttribute()
    {
        if (!$this->cpf) return null;
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $this->cpf);
    }

    /**
     * Accessor para telefone formatado
     */
    public function getFormattedPhoneAttribute()
    {
        if (!$this->phone) return null;
        return preg_replace('/(\d{2})(\d{4,5})(\d{4})/', '($1) $2-$3', $this->phone);
    }

    /**
     * Mutator para CPF (remove formatação)
     */
    public function setCpfAttribute($value)
    {
        $this->attributes['cpf'] = preg_replace('/[^0-9]/', '', $value);
    }

    /**
     * Mutator para telefone (remove formatação)
     */
    public function setPhoneAttribute($value)
    {
        $this->attributes['phone'] = preg_replace('/[^0-9]/', '', $value);
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new ClientVerifyEmail);
    }
}
