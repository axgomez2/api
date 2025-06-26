<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecordLabel extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo'
    ];
    
    protected $casts = [
        'logo' => 'string'
    ];

    public function vinylMasters(): HasMany
    {
        return $this->hasMany(VinylMaster::class);
    }
}
