<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
        'zip_code',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Relacionamento com usuário
     */
    public function user()
    {
        return $this->belongsTo(ClientUser::class, 'user_id');
    }

    /**
     * Accessor para CEP formatado
     */
    public function getFormattedZipCodeAttribute()
    {
        if (!$this->zip_code) return null;
        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $this->zip_code);
    }

    /**
     * Mutator para CEP (remove formatação)
     */
    public function setZipCodeAttribute($value)
    {
        $this->attributes['zip_code'] = preg_replace('/[^0-9]/', '', $value);
    }

    /**
     * Accessor para endereço completo
     */
    public function getFullAddressAttribute()
    {
        $address = "{$this->street}, {$this->number}";

        if ($this->complement) {
            $address .= ", {$this->complement}";
        }

        $address .= " - {$this->neighborhood}, {$this->city}/{$this->state}";
        $address .= " - CEP: {$this->formatted_zip_code}";

        return $address;
    }

    /**
     * Scope para endereços padrão
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope para endereços de um usuário
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
