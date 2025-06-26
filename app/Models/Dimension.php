<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dimension extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'height',
        'width',
        'depth',
        'unit'
    ];

    public function vinylSecs(): HasMany
    {
        return $this->hasMany(VinylSec::class);
    }
}
