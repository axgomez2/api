<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exceptions\ProductNotAvailableException;
use App\Exceptions\ProductAlreadyInCartException;

class CartController extends Controller
{
    public function index(Request $request)
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

            // Carregar carrinho com itens e produtos relacionados incluindo peso e dimensões
            $cartWithItems = Cart::with([
                'items.product.productable.artists',
                'items.product.productable.vinylSec.weight',
                'items.product.productable.vinylSec.dimension'
            ])->find($cart->id);

            return response()->json([
                'success' => true,
                'data' => $cartWithItems->items,
                'meta' => [
                    'cart_id' => $cart->id,
                    'total_items' => $cartWithItems->total_items,
                    'total_amount' => $cartWithItems->total_amount,
                    'status' => $cart->status
                ],
                'message' => 'Carrinho carregado com sucesso'
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao carregar carrinho:', [
                'user_id' => $user->id ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor ao carregar carrinho'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = $request->user(); // client.auth middleware garante que $user existe
        Log::info('🛒 Tentativa de adicionar ao carrinho:', [
            'user_id' => $user->id,
            'request_data' => $request->all(),
        ]);

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id'
        ]);

        if ($validator->fails()) {
            Log::warning('❌ Validação falhou para Cart@store:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos.',
                'errors' => $validator->errors()
            ], 422); // HTTP 422 Unprocessable Entity
        }

        $productId = $request->input('product_id');
        $product = Product::find($productId); // 'exists' na validação já garante que ele existe

        // A validação 'exists' já garante que o produto foi encontrado.
        // Se, por algum motivo muito estranho, não for, o código abaixo falhará.

        try {
            DB::beginTransaction();

            $cart = Cart::getActiveForUser($user->id);
            Log::info('✅ Carrinho obtido para Cart@store:', ['cart_id' => $cart->id, 'user_id' => $user->id]);

            $cartItem = $cart->addItem($product); // Lógica de negócio movida para o modelo
            Log::info('✅ Item adicionado ao carrinho via Cart@store:', ['cart_item_id' => $cartItem->id, 'product_id' => $product->id]);

            $cartItem->load('product.productable.artists', 'product.productable.vinylSec.weight', 'product.productable.vinylSec.dimension');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $cartItem,
                'message' => 'Produto adicionado ao carrinho com sucesso!'
            ], 201); // HTTP 201 Created

        } catch (ProductNotAvailableException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (ProductAlreadyInCartException $e) {
            DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'already_in_cart' => true
        ], 400);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ocorreu um erro inesperado ao adicionar o produto ao carrinho.'
            ], 500); // HTTP 500 Internal Server Error
        }
    }

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

            if (!$cart->removeItem($productId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item não encontrado no carrinho'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Produto removido do carrinho com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

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
            $cart->clear();

            return response()->json([
                'success' => true,
                'data' => ['items_removed' => $itemsCount],
                'message' => 'Carrinho limpo com sucesso'
            ]);

        } catch (\Exception $e) {
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
