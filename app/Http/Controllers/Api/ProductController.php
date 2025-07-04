<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Listar todos os produtos com paginação
     */
    public function index()
    {
        $products = Product::with(['productable'])
            ->paginate(15);

        // Para cada produto na coleção, verificamos se é do tipo VinylMaster
        // e carregamos as relações adicionais se for necessário
        $products->each(function ($product) {
            if ($product->productable_type === 'App\\Models\\VinylMaster') {
                $product->load([
                    'productable.tracks' => function ($query) {
                        $query->orderBy('position', 'asc');
                    },
                    'productable.recordLabel',
                    'productable.artists',
                    'productable.vinylSec',
                    'productable.vinylSec.weight',
                    'productable.vinylSec.dimension',
                    'productable.vinylSec.midiaStatus',
                    'productable.vinylSec.coverStatus',
                    'productable.categories',
                    'productable.media'
                ]);
            }
        });

        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }

    /**
     * Exibir detalhes de um produto específico pelo slug
     */
    public function show($slug)
    {
        $product = Product::where('slug', $slug)
            ->with(['productable'])
            ->firstOrFail();

        // Verificamos se o produto é um disco de vinil e carregamos dados específicos
        if ($product->productable_type === 'App\Models\VinylMaster') {
            $product->load([
                'productable.tracks' => function ($query) {
                    $query->orderBy('position', 'asc');
                },
                'productable.recordLabel',
                'productable.artists',
                'productable.vinylSec',
                'productable.vinylSec.weight',
                'productable.vinylSec.dimension',
                'productable.vinylSec.midiaStatus',
                'productable.vinylSec.coverStatus',
                'productable.categories',
                'productable.media'
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $product
        ]);
    }

    /**
     * Buscar produtos por termo de pesquisa
     */
    public function search(Request $request)
    {
        $query = $request->get('q');

        $products = Product::where('name', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->with(['productable'])
            ->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }

    /**
     * Filtrar produtos por tipo
     */
    public function filterByType($typeId)
    {
        $products = Product::where('product_type_id', $typeId)
            ->with(['productable'])
            ->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }

    /**
     * Listar apenas produtos que são discos de vinil
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function vinylProducts(Request $request)
    {
        try {
            // Iniciar a consulta filtrando por tipo de produto (vinil)
            $query = Product::where('productable_type', 'App\\Models\\VinylMaster');

            // Adicionar filtros opcionais
            if ($request->has('year')) {
                $year = $request->input('year');
                $query->whereHas('productable', function($q) use ($year) {
                    $q->where('release_year', $year);
                });
            }

            if ($request->has('artist_id')) {
                $artistId = $request->input('artist_id');
                $query->whereHas('productable.artists', function($q) use ($artistId) {
                    $q->where('artists.id', $artistId);
                });
            }

            if ($request->has('label_id')) {
                $labelId = $request->input('label_id');
                $query->whereHas('productable', function($q) use ($labelId) {
                    $q->where('record_label_id', $labelId);
                });
            }

            if ($request->has('category_id')) {
                $categoryId = $request->input('category_id');
                $query->whereHas('productable.categories', function($q) use ($categoryId) {
                    $q->where('cat_style_shop.id', $categoryId);
                });
            }

            // Definir ordenação
            $sortField = $request->input('sort_by', 'created_at');
            $sortDirection = $request->input('sort_direction', 'desc');

            // Lista de campos permitidos para ordenação
            $allowedSortFields = ['created_at', 'name', 'price'];

            // Verificar se o campo de ordenação é válido
            if (in_array($sortField, $allowedSortFields)) {
                // Se for ordenação por preço, precisamos ordenar pelo relacionamento vinylSec
                if ($sortField === 'price') {
                    $query->join('vinyl_masters', function ($join) {
                        $join->on('products.productable_id', '=', 'vinyl_masters.id');
                        $join->where('products.productable_type', '=', 'App\\Models\\VinylMaster');
                    })
                    ->join('vinyl_secs', 'vinyl_masters.id', '=', 'vinyl_secs.vinyl_master_id')
                    ->orderBy('vinyl_secs.price', $sortDirection)
                    ->select('products.*');
                } else {
                    $query->orderBy($sortField, $sortDirection);
                }
            } else {
                $query->orderBy('created_at', 'desc');
            }

            // Carregar relações
            $query->with([
                'productable.recordLabel',
                'productable.tracks' => function($q) {
                    $q->orderBy('position', 'asc');
                },
                'productable.vinylSec',
                'productable.vinylSec.weight',
                'productable.vinylSec.dimension',
                'productable.vinylSec.midiaStatus',
                'productable.vinylSec.coverStatus',
                'productable.artists',
                'productable.categories',
                'productable.media'
            ]);

            // Definir campos a selecionar e paginação
            $perPage = $request->input('per_page', 20);
            $products = $query->select(
                'products.id',
                'products.name',
                'products.slug',
                'products.description',
                'products.productable_id',
                'products.productable_type',
                'products.created_at'
            )
            ->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $products
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in vinylProducts:', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar produtos de vinil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exibir detalhes de um disco de vinil específico
     */
    public function vinylDetail($slug)
    {
        $product = Product::where('slug', $slug)
            ->where('productable_type', 'App\\Models\\VinylMaster')
            ->with([
                'productable.tracks' => function($query) {
                    $query->orderBy('position', 'asc');
                },
                'productable.recordLabel',
                'productable.artists',
                'productable.vinylSec',
                'productable.vinylSec.weight',
                'productable.vinylSec.dimension',
                'productable.vinylSec.midiaStatus',
                'productable.vinylSec.coverStatus',
                'productable.categories',
                'productable.media'
            ])
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $product
        ]);
    }

    /**
     * Retorna os últimos discos cadastrados
     *
     * @param int $limit Quantidade máxima de discos a retornar
     * @return \Illuminate\Http\JsonResponse
     */
    public function latestVinyls($limit = 20)
    {
        $products = Product::where('productable_type', 'App\\Models\\VinylMaster')
            ->with([
                'productable.recordLabel',
                'productable.artists',
                'productable.vinylSec',
                'productable.categories',
                'productable.media'
            ])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }
}
