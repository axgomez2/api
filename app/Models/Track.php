<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Track extends Model
{
    protected $fillable = [
        'vinyl_master_id',
        'name',
        'position',
        'duration',
        'youtube_url'
    ];

    public function vinylMaster(): BelongsTo
    {
        return $this->belongsTo(VinylMaster::class);
    }
}
