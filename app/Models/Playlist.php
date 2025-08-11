<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Playlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'description',
        'chart_date',
        'social_links',
        'dj_photo',
        'is_active',
        'position'
    ];

    protected $casts = [
        'chart_date' => 'date',
        'social_links' => 'array',
        'is_active' => 'boolean'
    ];

    /**
     * Relacionamento com as faixas da playlist
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(PlaylistTrack::class)->orderBy('position');
    }

    /**
     * Relacionamento com as faixas da playlist incluindo produtos
     */
    public function tracksWithProducts(): HasMany
    {
        return $this->hasMany(PlaylistTrack::class)
            ->with(['product.productable.artists', 'product.productable.vinylSec'])
            ->orderBy('position');
    }

    /**
     * Scope para playlists de DJ
     */
    public function scopeDj($query)
    {
        return $query->where('type', 'dj');
    }

    /**
     * Scope para playlists de Chart
     */
    public function scopeChart($query)
    {
        return $query->where('type', 'chart');
    }

    /**
     * Scope para playlists ativas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para ordenação por posição
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('position')->orderBy('created_at', 'desc');
    }

    /**
     * Accessor para o tipo formatado
     */
    public function getTypeFormattedAttribute(): string
    {
        return match($this->type) {
            'dj' => 'DJ Set',
            'chart' => 'Chart',
            default => $this->type
        };
    }

    /**
     * Accessor para URL da foto do DJ
     */
    public function getDjPhotoUrlAttribute(): ?string
    {
        if (!$this->dj_photo) {
            return null;
        }

        // Se já é uma URL completa, retorna como está
        if (str_starts_with($this->dj_photo, 'http')) {
            return $this->dj_photo;
        }

        // Constrói URL baseada no CDN configurado
        $cdnUrl = config('app.cdn_url', config('app.url'));
        return rtrim($cdnUrl, '/') . '/storage/' . ltrim($this->dj_photo, '/');
    }
}
