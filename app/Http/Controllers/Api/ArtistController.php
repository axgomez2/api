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

    /**
     * Buscar artistas relacionados usando diferentes estratégias
     */
    public function relatedArtists($slug)
    {
        try {
            $artist = Artist::where('slug', $slug)->firstOrFail();
            
            \Log::info('Buscando artistas relacionados para: ' . $artist->name);

            // Estratégia 1: Artistas com discos nas mesmas categorias
            $relatedArtists = $this->getArtistsByCategories($artist);
            \Log::info('Estratégia 1 (categorias): ' . $relatedArtists->count() . ' artistas encontrados');

            // Estratégia 2: Se não encontrou por categorias, buscar por gravadora
            if ($relatedArtists->isEmpty()) {
                $relatedArtists = $this->getArtistsByLabel($artist);
                \Log::info('Estratégia 2 (gravadora): ' . $relatedArtists->count() . ' artistas encontrados');
            }

            // Estratégia 3: Se ainda não encontrou, buscar artistas aleatórios
            if ($relatedArtists->isEmpty()) {
                $relatedArtists = $this->getRandomArtists($artist);
                \Log::info('Estratégia 3 (aleatórios): ' . $relatedArtists->count() . ' artistas encontrados');
            }

            \Log::info('Total de artistas relacionados retornados: ' . $relatedArtists->count());

            return response()->json([
                'status' => 'success',
                'data' => $relatedArtists,
                'debug' => [
                    'artist_id' => $artist->id,
                    'artist_name' => $artist->name,
                    'count' => $relatedArtists->count()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in relatedArtists:', ['error' => $e->getMessage(), 'slug' => $slug]);
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => []
            ]);
        }
    }

    /**
     * Buscar artistas por categorias dos discos
     */
    private function getArtistsByCategories($artist)
    {
        // Buscar IDs das categorias dos discos deste artista
        $categoryIds = \DB::table('cat_style_shop_vinyl_master')
            ->join('vinyl_masters', 'cat_style_shop_vinyl_master.vinyl_master_id', '=', 'vinyl_masters.id')
            ->join('artist_vinyl_master', 'vinyl_masters.id', '=', 'artist_vinyl_master.vinyl_master_id')
            ->where('artist_vinyl_master.artist_id', $artist->id)
            ->pluck('cat_style_shop_vinyl_master.cat_style_shop_id')
            ->unique();

        if ($categoryIds->isEmpty()) {
            return collect();
        }

        // Buscar outros artistas com discos nas mesmas categorias
        return Artist::whereHas('vinylMasters', function($query) use ($categoryIds) {
                $query->whereHas('categories', function($catQuery) use ($categoryIds) {
                    $catQuery->whereIn('cat_style_shop.id', $categoryIds);
                });
            })
            ->where('id', '!=', $artist->id)
            ->withCount('vinylMasters')
            ->inRandomOrder()
            ->limit(5)
            ->get();
    }

    /**
     * Buscar artistas pela mesma gravadora
     */
    private function getArtistsByLabel($artist)
    {
        // Buscar IDs das gravadoras dos discos deste artista
        $labelIds = \DB::table('vinyl_masters')
            ->join('artist_vinyl_master', 'vinyl_masters.id', '=', 'artist_vinyl_master.vinyl_master_id')
            ->where('artist_vinyl_master.artist_id', $artist->id)
            ->whereNotNull('vinyl_masters.record_label_id')
            ->pluck('vinyl_masters.record_label_id')
            ->unique();

        if ($labelIds->isEmpty()) {
            return collect();
        }

        // Buscar outros artistas com discos nas mesmas gravadoras
        return Artist::whereHas('vinylMasters', function($query) use ($labelIds) {
                $query->whereIn('record_label_id', $labelIds);
            })
            ->where('id', '!=', $artist->id)
            ->withCount('vinylMasters')
            ->inRandomOrder()
            ->limit(5)
            ->get();
    }

    /**
     * Buscar artistas aleatórios como fallback
     */
    private function getRandomArtists($artist)
    {
        \Log::info('Executando estratégia 3: artistas aleatórios');
        
        $randomArtists = Artist::where('id', '!=', $artist->id)
            ->has('vinylMasters')
            ->withCount('vinylMasters')
            ->inRandomOrder()
            ->limit(5)
            ->get();
            
        \Log::info('Query aleatórios executada, encontrados: ' . $randomArtists->count());
        
        return $randomArtists;
    }
}
