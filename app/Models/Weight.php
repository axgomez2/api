<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Weight extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'value',
        'unit'
    ];

    public function vinylSecs(): HasMany
    {
        return $this->hasMany(VinylSec::class);
    }
}
