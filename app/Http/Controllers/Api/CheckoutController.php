<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\AddressResource;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Controller para gerenciar o processo de checkout
 */
class CheckoutController extends Controller
{
    use ApiResponse;

    /**
     * Inicializa checkout - retorna todos os dados necessários de uma vez
     * Consolida: perfil, endereços, carrinho
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function init(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Carrega endereços do usuário
            $addresses = $user->addresses()->orderBy('is_default', 'desc')->get();

            // Carrega carrinho ativo com relacionamentos
            $cart = Cart::with([
                'items.product.productable.artists',
                'items.product.productable.vinylSec.weight',
                'items.product.productable.vinylSec.dimension'
            ])->getActiveForUser($user->id);

            // Endereço padrão
            $defaultAddress = $addresses->where('is_default', true)->first();

            return $this->successResponse([
                'user' => new UserResource($user),
                'addresses' => AddressResource::collection($addresses),
                'cart' => new CartResource($cart),
                'default_address' => $defaultAddress ? new AddressResource($defaultAddress) : null,
                'has_previous_orders' => $user->orders()->exists(),
                'cart_summary' => [
                    'items_count' => $cart->items->count(),
                    'subtotal' => $cart->items->sum(function ($item) {
                        return $item->quantity * ($item->promotional_price ?? $item->unit_price);
                    }),
                ]
            ], 'Dados do checkout carregados com sucesso');

        } catch (\Exception $e) {
            Log::error('Erro ao inicializar checkout:', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->serverErrorResponse('Erro ao carregar dados do checkout');
        }
    }

    /**
     * Valida se carrinho pode prosseguir para checkout
     * Verifica: estoque, endereço, disponibilidade
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function validate(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $cart = Cart::with('items.product.productable.vinylSec')
                        ->getActiveForUser($user->id);

            $errors = [];
            $outOfStock = [];
            $unavailableProducts = [];

            // 1. Validar se carrinho não está vazio
            if ($cart->items->isEmpty()) {
                return $this->errorResponse('Carrinho está vazio', 400);
            }

            // 2. Validar estoque e disponibilidade
            foreach ($cart->items as $item) {
                $product = $item->product;
                
                // Verificar se produto está disponível
                if ($product->available === false || $product->in_stock === false) {
                    $unavailableProducts[] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'reason' => 'Produto indisponível'
                    ];
                    continue;
                }

                // Verificar estoque
                $currentStock = $product->productable?->vinylSec?->stock ?? $product->stock ?? 0;
                
                if ($currentStock < $item->quantity) {
                    $outOfStock[] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'requested' => $item->quantity,
                        'available' => $currentStock
                    ];
                }
            }

            // 3. Validar se usuário tem endereço cadastrado
            if ($user->addresses()->count() === 0) {
                $errors['address_missing'] = true;
                $errors['address_message'] = 'Você precisa cadastrar um endereço de entrega';
            }

            // 4. Verificar se há erros
            if (!empty($outOfStock) || !empty($unavailableProducts) || !empty($errors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validação do carrinho falhou',
                    'validation_errors' => [
                        'out_of_stock' => $outOfStock,
                        'unavailable_products' => $unavailableProducts,
                        'general_errors' => $errors
                    ]
                ], 400);
            }

            // Tudo OK
            return $this->successResponse([
                'valid' => true,
                'items_count' => $cart->items->count(),
                'can_proceed' => true
            ], 'Carrinho validado com sucesso');

        } catch (\Exception $e) {
            Log::error('Erro ao validar checkout:', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return $this->serverErrorResponse('Erro ao validar carrinho');
        }
    }
}
