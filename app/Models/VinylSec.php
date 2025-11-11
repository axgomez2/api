<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VinylSec extends Model
{
    protected $fillable = [
        'vinyl_master_id',
        'weight_id',
        'dimension_id',
        'midia_status_id',
        'cover_status_id',
        'stock',
        'price',
        'notes',
        'is_new',
        'is_presale',
        'presale_arrival_date',
        'release_date',
        'promotional_price',
        'is_promotional',
        'in_stock'
    ];

    // Relacionamento com VinylMaster
    public function vinylMaster(): BelongsTo
    {
        return $this->belongsTo(VinylMaster::class);
    }

    // Relacionamentos com as dimensões, pesos e status (se necessário)
    public function weight()
    {
        return $this->belongsTo(Weight::class);
    }

    public function dimension()
    {
        return $this->belongsTo(Dimension::class);
    }

    public function midiaStatus()
    {
        return $this->belongsTo(MidiaStatus::class);
    }

    public function coverStatus()
    {
        return $this->belongsTo(CoverStatus::class);
    }
}
