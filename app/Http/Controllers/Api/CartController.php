<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\AddToCartRequest;
use App\Http\Resources\CartResource;
use App\Http\Resources\CartItemResource;
use App\Models\Cart;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exceptions\ProductNotAvailableException;
use App\Exceptions\ProductAlreadyInCartException;

class CartController extends Controller
{
    use ApiResponse;
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return $this->unauthorizedResponse('Usuário não autenticado');
            }

            $cart = Cart::getActiveForUser($user->id);

            // Carregar carrinho com itens e produtos relacionados incluindo peso e dimensões
            $cartWithItems = Cart::with([
                'items.product.productable.artists',
                'items.product.productable.vinylSec.weight',
                'items.product.productable.vinylSec.dimension'
            ])->find($cart->id);

            return $this->successResponse(
                new CartResource($cartWithItems),
                'Carrinho carregado com sucesso'
            );

        } catch (\Exception $e) {
            Log::error('Erro ao carregar carrinho:', [
                'user_id' => $user->id ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->serverErrorResponse('Erro interno do servidor ao carregar carrinho');
        }
    }

    public function store(AddToCartRequest $request)
    {
        $user = $request->user();
        $data = $request->getValidatedData();
        
        Log::info('🛒 Tentativa de adicionar ao carrinho:', [
            'user_id' => $user->id,
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity']
        ]);

        $product = Product::find($data['product_id']);

        try {
            DB::beginTransaction();

            $cart = Cart::getActiveForUser($user->id);
            Log::info('✅ Carrinho obtido para Cart@store:', ['cart_id' => $cart->id, 'user_id' => $user->id]);

            $cartItem = $cart->addItem($product);
            Log::info('✅ Item adicionado ao carrinho via Cart@store:', ['cart_item_id' => $cartItem->id, 'product_id' => $product->id]);

            $cartItem->load('product.productable.artists', 'product.productable.vinylSec.weight', 'product.productable.vinylSec.dimension');

            DB::commit();

            return $this->createdResponse(
                new CartItemResource($cartItem),
                'Produto adicionado ao carrinho com sucesso!'
            );

        } catch (ProductNotAvailableException $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 400);
        } catch (ProductAlreadyInCartException $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 400, ['already_in_cart' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao adicionar ao carrinho:', [
                'user_id' => $user->id,
                'product_id' => $data['product_id'],
                'error' => $e->getMessage()
            ]);
            return $this->serverErrorResponse('Ocorreu um erro inesperado ao adicionar o produto ao carrinho.');
        }
    }

    /**
     * Atualizar quantidade de um item no carrinho
     */
    public function update(Request $request, $productId)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return $this->unauthorizedResponse('Usuário não autenticado');
            }

            $validated = $request->validate([
                'quantity' => 'required|integer|min:1|max:99'
            ]);

            $cart = Cart::getActiveForUser($user->id);
            $cartItem = $cart->items()->where('product_id', $productId)->first();

            if (!$cartItem) {
                return $this->errorResponse('Item não encontrado no carrinho', 404);
            }

            // Atualizar quantidade
            $cartItem->update(['quantity' => $validated['quantity']]);
            $cartItem->load('product.productable.artists', 'product.productable.vinylSec');

            Log::info('✅ Quantidade atualizada', [
                'cart_item_id' => $cartItem->id,
                'product_id' => $productId,
                'old_quantity' => $cartItem->getOriginal('quantity'),
                'new_quantity' => $validated['quantity']
            ]);

            return $this->successResponse(
                new CartItemResource($cartItem),
                'Quantidade atualizada com sucesso'
            );

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar quantidade:', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            return $this->serverErrorResponse('Erro ao atualizar quantidade');
        }
    }

    /**
     * Remover item do carrinho
     * Se o carrinho ficar vazio, será excluído automaticamente
     */
    public function destroy(Request $request, $productId)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            $cart = Cart::getActiveForUser($user->id);
            $itemsCountBefore = $cart->items()->count();

            if (!$cart->removeItem($productId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item não encontrado no carrinho'
                ], 404);
            }

            // Verificar se carrinho ainda existe (pode ter sido excluído pelo Observer)
            $cartStillExists = Cart::find($cart->id) !== null;

            Log::info('✅ Item removido do carrinho', [
                'product_id' => $productId,
                'items_before' => $itemsCountBefore,
                'cart_deleted' => !$cartStillExists
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Produto removido do carrinho com sucesso',
                'cart_deleted' => !$cartStillExists // Indica se carrinho foi excluído
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao remover item:', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpar carrinho
     * O carrinho será excluído automaticamente após limpar
     */
    public function clear(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            $cart = Cart::getActiveForUser($user->id);
            $itemsCount = $cart->items()->count();
            
            if ($itemsCount === 0) {
                return response()->json([
                    'success' => true,
                    'data' => ['items_removed' => 0],
                    'message' => 'Carrinho já está vazio'
                ]);
            }

            $cart->clear(); // Observer irá excluir o carrinho

            Log::info('✅ Carrinho limpo', [
                'user_id' => $user->id,
                'items_removed' => $itemsCount,
                'cart_deleted' => true // Sempre excluído após clear
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'items_removed' => $itemsCount,
                    'cart_deleted' => true
                ],
                'message' => 'Carrinho limpo com sucesso'
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao limpar carrinho:', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function checkItem(Request $request, $productId)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'in_cart' => false
                ]);
            }

            $cart = Cart::getActiveForUser($user->id);
            $inCart = $cart->hasItem($productId);

            return response()->json([
                'success' => true,
                'in_cart' => $inCart
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'in_cart' => false
            ]);
        }
    }
}
