<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wantlist;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WantlistController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['message' => 'Usuário não autenticado'], 401);
            }

            \Log::info('Carregando wantlist para usuário:', ['user_id' => $user->id]);

            // Buscar itens da wantlist do usuário com os produtos relacionados
            $wishlistItems = Wantlist::where('user_id', $user->id)
                ->with(['product.productable.artists', 'product.productable.vinylSec'])
                ->get();

            // Transformar os dados para o formato esperado pelo frontend
            $products = $wishlistItems->map(function ($item) {
                $product = $item->product;
                // Adicionar informação do ID da wantlist para facilitar remoção
                $product->wantlist_id = $item->id;
                return $product;
            });

            \Log::info('Wantlist carregada:', [
                'user_id' => $user->id,
                'items_count' => $products->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $products,
                'message' => 'Wantlist carregada com sucesso'
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro ao carregar wantlist:', [
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

            \Log::info('Adicionando produto à wantlist:', [
                'user_id' => $user->id,
                'product_id' => $productId
            ]);

            // Verificar se o produto já está na wantlist
            $existingItem = Wantlist::where('user_id', $user->id)
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
            $wishlistItem = Wantlist::create([
                'user_id' => $user->id,
                'product_id' => $productId
            ]);

            \Log::info('Produto adicionado à wantlist:', [
                'wantlist_id' => $wishlistItem->id,
                'user_id' => $user->id,
                'product_id' => $productId,
                'product_name' => $product->name
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'wantlist_id' => $wishlistItem->id,
                    'product' => $product
                ],
                'message' => 'Produto adicionado à wantlist com sucesso'
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro ao adicionar à wantlist:', [
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

            \Log::info('Removendo produto da wantlist:', [
                'user_id' => $user->id,
                'product_id' => $productId
            ]);

            // Buscar o item na wantlist
            $wishlistItem = Wantlist::where('user_id', $user->id)
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

            \Log::info('Produto removido da wantlist:', [
                'wantlist_id' => $wishlistItem->id,
                'user_id' => $user->id,
                'product_id' => $productId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Produto removido da wantlist com sucesso'
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro ao remover da wantlist:', [
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

            \Log::info('Limpando wantlist do usuário:', ['user_id' => $user->id]);

            // Contar itens antes de remover
            $itemsCount = Wantlist::where('user_id', $user->id)->count();

            // Remover todos os itens da wantlist do usuário
            Wantlist::where('user_id', $user->id)->delete();

            \Log::info('Wantlist limpa:', [
                'user_id' => $user->id,
                'items_removed' => $itemsCount
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'items_removed' => $itemsCount
                ],
                'message' => 'Wantlist limpa com sucesso'
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro ao limpar wantlist:', [
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

            $exists = Wantlist::where('user_id', $user->id)
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

    // Método toggle para adicionar/remover da wantlist
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

            // Verificar se já existe na wantlist
            $wantlistItem = Wantlist::where('user_id', $user->id)
                ->where('product_id', $productId)
                ->first();

            if ($wantlistItem) {
                // Se existe, remover
                $wantlistItem->delete();
                
                \Log::info('Produto removido da wantlist via toggle:', [
                    'user_id' => $user->id,
                    'product_id' => $productId
                ]);

                return response()->json([
                    'success' => true,
                    'action' => 'removed',
                    'message' => 'Produto removido da wantlist'
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

                $wantlistItem = Wantlist::create([
                    'user_id' => $user->id,
                    'product_id' => $productId
                ]);

                \Log::info('Produto adicionado à wantlist via toggle:', [
                    'user_id' => $user->id,
                    'product_id' => $productId,
                    'wantlist_id' => $wantlistItem->id
                ]);

                return response()->json([
                    'success' => true,
                    'action' => 'added',
                    'data' => $product,
                    'message' => 'Produto adicionado à wantlist'
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Erro no toggle da wantlist:', [
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
