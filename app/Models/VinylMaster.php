<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class VinylMaster extends Model
{
    protected $fillable = [
        'title', 
        'slug', 
        'description', 
        'cover_image', 
        'release_year',
        'country',
        'record_label_id'
    ];

    // Relacionamento polimórfico com Product
    public function product(): MorphOne
    {
        return $this->morphOne(Product::class, 'productable');
    }

    // Relacionamento com Track (faixas do disco)
    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }
    
    // Relacionamento com VinylSec (dados secundários do disco)
    public function vinylSec()
    {
        return $this->hasOne(VinylSec::class);
    }

    // Relacionamento com RecordLabel
    public function recordLabel(): BelongsTo
    {
        return $this->belongsTo(RecordLabel::class);
    }
    
    // Relacionamento com Artists através da tabela pivot artist_vinyl_master
    public function artists()
    {
        return $this->belongsToMany(Artist::class, 'artist_vinyl_master');
    }
    
    // Relacionamento com CatStyleShop através da tabela pivot cat_style_shop_vinyl_master
    public function categories()
    {
        return $this->belongsToMany(
            CatStyleShop::class, 
            'cat_style_shop_vinyl_master', 
            'vinyl_master_id', 
            'cat_style_shop_id'
        );
    }
    
    // Relacionamento polimórfico com Media (imagens)
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
