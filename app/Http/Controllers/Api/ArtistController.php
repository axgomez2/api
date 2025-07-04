<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Product;
use Illuminate\Http\Request;

class ArtistController extends Controller
{
    /**
     * Listar todos os artistas
     */
    public function index()
    {
        $artists = Artist::withCount('vinylMasters')
            ->has('vinylMasters')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $artists
        ]);
    }

    /**
     * Mostrar detalhes de um artista específico
     */
    public function show($slug)
    {
        $artist = Artist::where('slug', $slug)
            ->withCount('vinylMasters')
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $artist
        ]);
    }

    /**
     * Listar produtos de um artista específico
     */
    public function productsByArtist($slug)
    {
        $artist = Artist::where('slug', $slug)->firstOrFail();

        // Buscar produtos que têm vinyl_masters relacionados a este artista
        $products = Product::whereHasMorph(
                'productable',
                ['App\\Models\\VinylMaster'],
                function ($query) use ($artist) {
                    $query->whereHas('artists', function($artistQuery) use ($artist) {
                        $artistQuery->where('artists.id', $artist->id);
                    });
                }
            )
            ->with([
                'productable.recordLabel',
                'productable.artists',
                'productable.vinylSec',
                'productable.categories',
                'productable.media',
                'productable.tracks'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'status' => 'success',
            'artist' => $artist->name,
            'data' => $products
        ]);
    }
}
