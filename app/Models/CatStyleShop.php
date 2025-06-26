<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CatStyleShop extends Model
{
    protected $table = 'cat_style_shop';
    
    protected $fillable = [
        'name',
        'slug',
        'description'
    ];

    // Relacionamento muitos para muitos com VinylMaster
    public function vinylMasters(): BelongsToMany
    {
        return $this->belongsToMany(
            VinylMaster::class, 
            'cat_style_shop_vinyl_master', 
            'cat_style_shop_id', 
            'vinyl_master_id'
        );
    }
}
