<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    use ApiResponse;

    /**
     * Listar produtos com paginação otimizada
     */
    public function index(Request $request)
    {
        try {
            $perPage = min($request->get('per_page', 20), 50); // Máximo 50 por página
            $cacheKey = "products.index.page.{$request->get('page', 1)}.per_page.{$perPage}";

            $products = Cache::remember($cacheKey, 300, function () use ($perPage) {
                return Product::with([
                    'productable.recordLabel:id,name,slug',
                    'productable.artists:id,name,slug',
                    'productable.vinylSec:id,vinyl_master_id,price,stock',
                    'productable.categories:id,name,slug',
                    'productable.media' => function ($query) {
                        $query->where('is_primary', true)->orWhere('order', '<=', 3);
                    }
                ])
                ->select('id', 'name', 'slug', 'description', 'productable_id', 'productable_type', 'created_at')
                ->latest()
                ->paginate($perPage);
            });

            return $this->successResponse(
                ProductResource::collection($products)->response()->getData(),
                'Produtos carregados com sucesso'
            );

        } catch (\Exception $e) {
            \Log::error('Erro ao listar produtos:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->serverErrorResponse('Erro ao carregar produtos');
        }
    }

    /**
     * Exibir produto específico
     */
    public function show(string $slug)
    {
        try {
            $cacheKey = "product.{$slug}";

            $product = Cache::remember($cacheKey, 600, function () use ($slug) {
                return Product::where('slug', $slug)
                    ->with([
                        'productable.tracks' => function ($query) {
                            $query->orderBy('position', 'asc');
                        },
                        'productable.recordLabel',
                        'productable.artists',
                        'productable.vinylSec.weight',
                        'productable.vinylSec.dimension',
                        'productable.vinylSec.midiaStatus',
                        'productable.vinylSec.coverStatus',
                        'productable.categories',
                        'productable.media'
                    ])
                    ->first();
            });

            if (!$product) {
                return $this->notFoundResponse('Produto não encontrado');
            }

            return $this->successResponse(
                new ProductResource($product),
                'Produto carregado com sucesso'
            );

        } catch (\Exception $e) {
            \Log::error('Erro ao exibir produto:', [
                'slug' => $slug,
                'error' => $e->getMessage()
            ]);

            return $this->serverErrorResponse('Erro ao carregar produto');
        }
    }

    /**
     * Buscar produtos
     */
    public function search(Request $request)
    {
        try {
            $query = $request->get('q');
            $perPage = min($request->get('per_page', 20), 50);

            if (empty($query) || strlen($query) < 2) {
                return $this->errorResponse('Termo de busca deve ter pelo menos 2 caracteres', 400);
            }

            $products = Product::where('name', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%")
                ->orWhereHas('productable.artists', function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%");
                })
                ->with([
                    'productable.recordLabel:id,name,slug',
                    'productable.artists:id,name,slug',
                    'productable.vinylSec:id,vinyl_master_id,price,stock',
                    'productable.categories:id,name,slug'
                ])
                ->select('id', 'name', 'slug', 'description', 'productable_id', 'productable_type', 'created_at')
                ->paginate($perPage);

            return $this->successResponse(
                ProductResource::collection($products)->response()->getData(),
                "Encontrados {$products->total()} produtos para '{$query}'"
            );

        } catch (\Exception $e) {
            \Log::error('Erro na busca de produtos:', [
                'query' => $request->get('q'),
                'error' => $e->getMessage()
            ]);

            return $this->serverErrorResponse('Erro na busca de produtos');
        }
    }

    /**
     * Produtos de vinil com filtros otimizados
     */
    public function vinylProducts(Request $request)
    {
        try {
            $query = Product::where('productable_type', 'App\\Models\\VinylMaster');

            // Aplicar filtros
            $this->applyVinylFilters($query, $request);

            // Ordenação
            $sortField = $request->input('sort_by', 'created_at');
            $sortDirection = $request->input('sort_direction', 'desc');

            if ($sortField === 'price') {
                $query->join('vinyl_masters', function ($join) {
                    $join->on('products.productable_id', '=', 'vinyl_masters.id')
                         ->where('products.productable_type', '=', 'App\\Models\\VinylMaster');
                })
                ->join('vinyl_secs', 'vinyl_masters.id', '=', 'vinyl_secs.vinyl_master_id')
                ->orderBy('vinyl_secs.price', $sortDirection)
                ->select('products.*');
            } else {
                $query->orderBy($sortField, $sortDirection);
            }

            // Eager loading otimizado
            $query->with([
                'productable.recordLabel:id,name,slug',
                'productable.artists:id,name,slug',
                'productable.vinylSec:id,vinyl_master_id,price,stock',
                'productable.categories:id,name,slug',
                'productable.media' => function ($q) {
                    $q->where('is_primary', true)->limit(1);
                }
            ]);

            $perPage = min($request->input('per_page', 20), 50);
            $products = $query->paginate($perPage);

            return $this->successResponse(
                ProductResource::collection($products)->response()->getData(),
                'Produtos de vinil carregados com sucesso'
            );

        } catch (\Exception $e) {
            \Log::error('Erro ao carregar produtos de vinil:', [
                'filters' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return $this->serverErrorResponse('Erro ao carregar produtos de vinil');
        }
    }

    /**
     * Últimos vinis adicionados
     */
    public function latestVinyls(int $limit = 20)
    {
        try {
            $limit = min($limit, 50); // Máximo 50
            $cacheKey = "products.latest_vinyls.{$limit}";

            $products = Cache::remember($cacheKey, 300, function () use ($limit) {
                return Product::where('productable_type', 'App\\Models\\VinylMaster')
                    ->with([
                        'productable.recordLabel:id,name,slug',
                        'productable.artists:id,name,slug',
                        'productable.vinylSec:id,vinyl_master_id,price,stock',
                        'productable.categories:id,name,slug',
                        'productable.media' => function ($q) {
                            $q->where('is_primary', true)->limit(1);
                        }
                    ])
                    ->select('id', 'name', 'slug', 'description', 'productable_id', 'productable_type', 'created_at')
                    ->latest()
                    ->limit($limit)
                    ->get();
            });

            return $this->successResponse(
                ProductResource::collection($products),
                'Últimos vinis carregados com sucesso'
            );

        } catch (\Exception $e) {
            \Log::error('Erro ao carregar últimos vinis:', [
                'limit' => $limit,
                'error' => $e->getMessage()
            ]);

            return $this->serverErrorResponse('Erro ao carregar últimos vinis');
        }
    }

    /**
     * Aplicar filtros específicos para produtos de vinil
     */
    private function applyVinylFilters($query, Request $request)
    {
        if ($request->has('year')) {
            $query->whereHas('productable', function ($q) use ($request) {
                $q->where('release_year', $request->input('year'));
            });
        }

        if ($request->has('artist_id')) {
            $query->whereHas('productable.artists', function ($q) use ($request) {
                $q->where('artists.id', $request->input('artist_id'));
            });
        }

        if ($request->has('label_id')) {
            $query->whereHas('productable', function ($q) use ($request) {
                $q->where('record_label_id', $request->input('label_id'));
            });
        }

        if ($request->has('category_id')) {
            $query->whereHas('productable.categories', function ($q) use ($request) {
                $q->where('cat_style_shop.id', $request->input('category_id'));
            });
        }

        if ($request->has('price_min')) {
            $query->whereHas('productable.vinylSec', function ($q) use ($request) {
                $q->where('price', '>=', $request->input('price_min'));
            });
        }

        if ($request->has('price_max')) {
            $query->whereHas('productable.vinylSec', function ($q) use ($request) {
                $q->where('price', '<=', $request->input('price_max'));
            });
        }

        if ($request->has('in_stock') && $request->boolean('in_stock')) {
            $query->whereHas('productable.vinylSec', function ($q) {
                $q->where('stock', '>', 0);
            });
        }
    }
}