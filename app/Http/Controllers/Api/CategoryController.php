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

    /**
     * Buscar os últimos discos de uma categoria específica (otimizado para carousel)
     * 
     * @param int $id ID da categoria
     * @param int $limit Limite de discos (padrão: 10)
     * @return \Illuminate\Http\JsonResponse
     */
    public function latestVinylsByCategory($id, $limit = 10)
    {
        try {
            // Verificar se a categoria existe
            $category = CatStyleShop::find($id);
            
            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Categoria não encontrada'
                ], 404);
            }

            // Buscar os últimos discos desta categoria usando a tabela pivot
            $products = Product::where('productable_type', 'App\\Models\\VinylMaster')
                ->whereHas('productable', function ($query) use ($id) {
                    $query->whereHas('categories', function ($categoryQuery) use ($id) {
                        $categoryQuery->where('cat_style_shops.id', $id);
                    });
                })
                ->with([
                    'productable.recordLabel:id,name',
                    'productable.artists:id,name,slug',
                    'productable.vinylSec:id,vinyl_master_id,price,promotional_price,is_new',
                    'productable.categories:id,name,slug',
                    'productable.media'
                ])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $products,
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug
                ],
                'message' => "Retornando os {$products->count()} discos mais recentes da categoria '{$category->name}'"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar discos da categoria: ' . $e->getMessage()
            ], 500);
        }
    }
}
