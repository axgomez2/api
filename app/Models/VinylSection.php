<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VinylSection extends Model
{
    protected $fillable = [
        'vinyl_master_id',
        'title',
        'duration',
        'track_number',
        'side' // A, B, etc. para vinis
    ];

    public function vinylMaster(): BelongsTo
    {
        return $this->belongsTo(VinylMaster::class);
    }
}
