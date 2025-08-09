<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['message' => 'Usuário não autenticado'], 401);
            }

            \Log::info('Carregando wishlist para usuário:', ['user_id' => $user->id]);

            // Buscar itens da wishlist do usuário com os produtos relacionados
            $wishlistItems = Wishlist::where('user_id', $user->id)
                ->with(['product.productable.artists', 'product.productable.vinylSec'])
                ->get();

            // Transformar os dados para o formato esperado pelo frontend
            $products = $wishlistItems->map(function ($item) {
                $product = $item->product;
                // Adicionar informação do ID da wishlist para facilitar remoção
                $product->wishlist_id = $item->id;
                return $product;
            });

            \Log::info('Wishlist carregada:', [
                'user_id' => $user->id,
                'items_count' => $products->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $products,
                'message' => 'Wishlist carregada com sucesso'
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro ao carregar wishlist:', [
                'user_id' => $user->id ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|integer|exists:products,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            
            if (!$user) {
                return response()->json(['message' => 'Usuário não autenticado'], 401);
            }

            $productId = $request->product_id;

            \Log::info('Adicionando produto à wishlist:', [
                'user_id' => $user->id,
                'product_id' => $productId
            ]);

            // Verificar se o produto já está na wishlist
            $existingItem = Wishlist::where('user_id', $user->id)
                ->where('product_id', $productId)
                ->first();

            if ($existingItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produto já está na sua lista de desejos'
                ], 409); // Conflict
            }

            // Verificar se o produto existe
            $product = Product::find($productId);
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produto não encontrado'
                ], 404);
            }

            // Criar item na wishlist
            $wishlistItem = Wishlist::create([
                'user_id' => $user->id,
                'product_id' => $productId
            ]);

            \Log::info('Produto adicionado à wishlist:', [
                'wishlist_id' => $wishlistItem->id,
                'user_id' => $user->id,
                'product_id' => $productId,
                'product_name' => $product->name
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'wishlist_id' => $wishlistItem->id,
                    'product' => $product
                ],
                'message' => 'Produto adicionado à wishlist com sucesso'
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro ao adicionar à wishlist:', [
                'user_id' => $user->id ?? null,
                'product_id' => $request->product_id ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $productId)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['message' => 'Usuário não autenticado'], 401);
            }

            \Log::info('Removendo produto da wishlist:', [
                'user_id' => $user->id,
                'product_id' => $productId
            ]);

            // Buscar o item na wishlist
            $wishlistItem = Wishlist::where('user_id', $user->id)
                ->where('product_id', $productId)
                ->first();

            if (!$wishlistItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produto não encontrado na sua lista de desejos'
                ], 404);
            }

            // Remover o item
            $wishlistItem->delete();

            \Log::info('Produto removido da wishlist:', [
                'wishlist_id' => $wishlistItem->id,
                'user_id' => $user->id,
                'product_id' => $productId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Produto removido da wishlist com sucesso'
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro ao remover da wishlist:', [
                'user_id' => $user->id ?? null,
                'product_id' => $productId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function clear(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['message' => 'Usuário não autenticado'], 401);
            }

            \Log::info('Limpando wishlist do usuário:', ['user_id' => $user->id]);

            // Contar itens antes de remover
            $itemsCount = Wishlist::where('user_id', $user->id)->count();

            // Remover todos os itens da wishlist do usuário
            Wishlist::where('user_id', $user->id)->delete();

            \Log::info('Wishlist limpa:', [
                'user_id' => $user->id,
                'items_removed' => $itemsCount
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'items_removed' => $itemsCount
                ],
                'message' => 'Wishlist limpa com sucesso'
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro ao limpar wishlist:', [
                'user_id' => $user->id ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Método adicional para verificar se um produto está na wishlist
    public function check(Request $request, $productId)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['message' => 'Usuário não autenticado'], 401);
            }

            $exists = Wishlist::where('user_id', $user->id)
                ->where('product_id', $productId)
                ->exists();

            return response()->json([
                'success' => true,
                'data' => [
                    'in_wishlist' => $exists
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Método toggle para adicionar/remover da wishlist
    public function toggle(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['message' => 'Usuário não autenticado'], 401);
            }

            $validator = Validator::make($request->all(), [
                'product_id' => 'required|integer|exists:products,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $productId = $request->product_id;

            // Verificar se já existe na wishlist
            $wishlistItem = Wishlist::where('user_id', $user->id)
                ->where('product_id', $productId)
                ->first();

            if ($wishlistItem) {
                // Se existe, remover
                $wishlistItem->delete();
                
                \Log::info('Produto removido da wishlist via toggle:', [
                    'user_id' => $user->id,
                    'product_id' => $productId
                ]);

                return response()->json([
                    'success' => true,
                    'action' => 'removed',
                    'message' => 'Produto removido da wishlist'
                ]);
            } else {
                // Se não existe, adicionar
                $product = Product::find($productId);
                
                if (!$product) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Produto não encontrado'
                    ], 404);
                }

                $wishlistItem = Wishlist::create([
                    'user_id' => $user->id,
                    'product_id' => $productId
                ]);

                \Log::info('Produto adicionado à wishlist via toggle:', [
                    'user_id' => $user->id,
                    'product_id' => $productId,
                    'wishlist_id' => $wishlistItem->id
                ]);

                return response()->json([
                    'success' => true,
                    'action' => 'added',
                    'data' => $product,
                    'message' => 'Produto adicionado à wishlist'
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Erro no toggle da wishlist:', [
                'user_id' => $user->id ?? null,
                'product_id' => $request->product_id ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
