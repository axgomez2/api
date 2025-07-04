<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Artist extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image'
    ];

    protected $casts = [
        'image' => 'string'
    ];

    // Relacionamento com VinylMaster através da tabela pivot
    public function vinylMasters(): BelongsToMany
    {
        return $this->belongsToMany(VinylMaster::class, 'artist_vinyl_master');
    }

    // Relacionamento com produtos através dos vinyl masters
    public function products()
    {
        return $this->hasManyThrough(
            Product::class,
            VinylMaster::class,
            'id', // Foreign key on vinyl_masters table
            'productable_id', // Foreign key on products table
            'id', // Local key on artists table (through pivot)
            'id' // Local key on vinyl_masters table
        )->where('products.productable_type', 'App\\Models\\VinylMaster');
    }
}
