<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MidiaStatus extends Model
{
    protected $table = 'midia_status';
    
    protected $fillable = [
        'title',
        'description'
    ];

    public function vinylSecs(): HasMany
    {
        return $this->hasMany(VinylSec::class);
    }
}
