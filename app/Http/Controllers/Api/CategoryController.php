<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CatStyleShop;
use App\Models\Product;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Listar todas as categorias/estilos
     */
    public function index()
    {
        $categories = CatStyleShop::withCount('vinylMasters')
            ->has('vinylMasters') // Only get categories that have related vinyl records
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $categories
        ]);
    }

    /**
     * Mostrar detalhes de uma categoria específica
     */
    public function show($slug)
    {
        $category = CatStyleShop::where('slug', $slug)->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $category
        ]);
    }

    /**
     * Listar produtos de uma categoria específica
     */
    public function productsByCategory($slug)
    {
        $category = CatStyleShop::where('slug', $slug)
            ->firstOrFail();

        // Encontrar os IDs dos vinyl_masters relacionados a esta categoria
        $vinylMasterIds = $category->vinylMasters()
            ->pluck('vinyl_masters.id')
            ->toArray();

        // Buscar produtos que têm esses vinyl_masters
        $products = Product::whereHasMorph(
                'productable',
                ['App\\Models\\VinylMaster'],
                function ($query) use ($vinylMasterIds) {
                    $query->whereIn('id', $vinylMasterIds);
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
            ->paginate(15);

        return response()->json([
            'status' => 'success',
            'category' => $category->name,
            'data' => $products
        ]);
    }

    /**
     * Listar todas as categorias com alguns de seus produtos
     */
    public function fetchWithProducts()
    {
        $categories = CatStyleShop::whereHas('vinylMasters')
            ->with(['vinylMasters' => function ($query) {
                $query->with([
                    'productable.recordLabel',
                    'productable.artists',
                    'productable.vinylSec',
                    'productable.categories',
                    'productable.media',
                    'productable.tracks'
                ])->take(5);
            }])
            ->get();

        // Estrutura a resposta para corresponder ao que o front-end espera
        $formattedData = $categories->map(function ($category) {
            return [
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ],
                'products' => $category->vinylMasters->map(function ($vinylMaster) {
                    return $vinylMaster->product; // Retorna o 'product' associado
                })
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formattedData
        ]);
    }
}
