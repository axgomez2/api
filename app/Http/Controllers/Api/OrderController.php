<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\Cart;
use App\Models\ShippingQuote;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Lista os pedidos do usuário autenticado
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $orders = Order::forUser($user->id)
                ->with(['items', 'shippingLabel', 'paymentTransactions'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $orders,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar pedidos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mostra detalhes de um pedido específico
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            $order = Order::forUser($user->id)
                ->with([
                    'items.product',
                    'items.vinyl',
                    'statusHistory' => function($query) {
                        $query->orderBy('created_at', 'desc');
                    },
                    'shippingLabel',
                    'paymentTransactions' => function($query) {
                        $query->orderBy('created_at', 'desc');
                    },
                    'coupons'
                ])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $order,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado',
            ], 404);
        }
    }

    /**
     * Cria um novo pedido a partir do carrinho
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'shipping_quote_id' => 'required|exists:shipping_quotes,id',
            'shipping_address' => 'required|array',
            'billing_address' => 'required|array',
            'payment_method' => 'required|string',
            'coupon_code' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $user = $request->user();

            // Busca o carrinho do usuário
            $cart = Cart::where('user_id', $user->id)->with('items.product', 'items.vinyl')->first();

            if (!$cart || $cart->items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Carrinho vazio',
                ], 400);
            }

            // Busca a cotação de frete
            $shippingQuote = ShippingQuote::findOrFail($request->shipping_quote_id);

            // Calcula totais
            $subtotal = $cart->items->sum(function ($item) {
                return $item->quantity * ($item->promotional_price ?? $item->unit_price);
            });

            $shippingCost = $shippingQuote->price;
            $discount = 0; // TODO: Implementar lógica de cupons
            $total = $subtotal + $shippingCost - $discount;

            // Cria o pedido
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $request->payment_method,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'discount' => $discount,
                'total' => $total,
                'shipping_address' => $request->shipping_address,
                'billing_address' => $request->billing_address,
                'shipping_quote_id' => $shippingQuote->id,
                'shipping_data' => [
                    'service_name' => $shippingQuote->service_name,
                    'company_name' => $shippingQuote->company_name,
                    'delivery_time' => $shippingQuote->delivery_time,
                    'dimensions' => $shippingQuote->dimensions,
                    'weight' => $shippingQuote->weight,
                ],
            ]);

            // Cria os itens do pedido
            foreach ($cart->items as $cartItem) {
                $product = $cartItem->product;
                $vinyl = $cartItem->vinyl;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'vinyl_id' => $vinyl?->id,
                    'product_snapshot' => [
                        'product' => $product->toArray(),
                        'vinyl' => $vinyl?->toArray(),
                    ],
                    'product_name' => $product->name ?? $vinyl->title,
                    'product_sku' => $product->sku ?? $vinyl->barcode,
                    'product_image' => $product->image ?? $vinyl->image,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $cartItem->unit_price,
                    'promotional_price' => $cartItem->promotional_price,
                    'total_price' => $cartItem->quantity * ($cartItem->promotional_price ?? $cartItem->unit_price),
                    'artist_name' => $vinyl?->artist_name,
                    'album_title' => $vinyl?->title,
                    'vinyl_condition' => $vinyl?->midia_status,
                    'cover_condition' => $vinyl?->cover_status,
                ]);
            }

            // Cria histórico inicial
            OrderStatusHistory::createHistory(
                $order->id,
                null,
                'pending',
                'Pedido criado',
                ['created_from' => 'web'],
                $user->id,
                'automatic'
            );

            // Limpa o carrinho
            $cart->items()->delete();
            $cart->delete();

            DB::commit();

            // Carrega o pedido com relacionamentos
            $order->load(['items', 'statusHistory', 'shippingLabel', 'paymentTransactions']);

            return response()->json([
                'success' => true,
                'message' => 'Pedido criado com sucesso',
                'data' => $order,
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar pedido: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancela um pedido (se possível)
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            $order = Order::forUser($user->id)->findOrFail($id);

            if (!$order->canCancel()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este pedido não pode ser cancelado',
                ], 400);
            }

            DB::beginTransaction();

            // Atualiza status do pedido
            $oldStatus = $order->status;
            $order->update([
                'status' => 'canceled',
                'payment_status' => 'cancelled',
            ]);

            // Cria histórico
            OrderStatusHistory::createHistory(
                $order->id,
                $oldStatus,
                'canceled',
                'Pedido cancelado pelo cliente',
                ['canceled_by' => 'customer'],
                $user->id,
                'manual'
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pedido cancelado com sucesso',
                'data' => $order->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Erro ao cancelar pedido: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Busca informações de rastreamento
     */
    public function tracking(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            $order = Order::forUser($user->id)
                ->with('shippingLabel')
                ->findOrFail($id);

            if (!$order->canTrack()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rastreamento não disponível para este pedido',
                ], 400);
            }

            $trackingData = [
                'tracking_code' => $order->tracking_code,
                'tracking_url' => $order->getTrackingUrl(),
                'estimated_delivery' => $order->getEstimatedDelivery(),
                'status' => $order->getStatusLabel(),
                'shipping_company' => $order->shipping_data['company_name'] ?? null,
                'service_name' => $order->shipping_data['service_name'] ?? null,
            ];

            // Se tem etiqueta, inclui eventos de rastreamento
            if ($order->shippingLabel && $order->shippingLabel->tracking_events) {
                $trackingData['events'] = collect($order->shippingLabel->tracking_events)
                    ->map(function ($event) {
                        return [
                            'date' => $event['date'] ?? null,
                            'time' => $event['time'] ?? null,
                            'location' => $event['location'] ?? null,
                            'description' => $event['description'] ?? null,
                        ];
                    })
                    ->reverse()
                    ->values();
            }

            return response()->json([
                'success' => true,
                'data' => $trackingData,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar rastreamento: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reprocessa o pagamento de um pedido
     */
    public function retryPayment(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|string',
            'payment_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();

            $order = Order::forUser($user->id)->findOrFail($id);

            if (!in_array($order->payment_status, ['rejected', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível reprocessar o pagamento deste pedido',
                ], 400);
            }

            DB::beginTransaction();

            // Atualiza método de pagamento
            $order->update([
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'payment_data' => $request->payment_data,
            ]);

            // Cria histórico
            OrderStatusHistory::createHistory(
                $order->id,
                $order->payment_status,
                'pending',
                'Pagamento reprocessado',
                [
                    'payment_method' => $request->payment_method,
                    'retry_attempt' => true,
                ],
                $user->id,
                'manual'
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pagamento reprocessado com sucesso',
                'data' => $order->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Erro ao reprocessar pagamento: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cria um pedido pendente para WhatsApp
     */
    public function createWhatsAppOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cart_items' => 'required|array|min:1',
            'cart_items.*.id' => 'required|integer',
            'cart_items.*.quantity' => 'required|integer|min:1',
            'shipping_address' => 'required|array',
            'shipping_address.street' => 'required|string',
            'shipping_address.number' => 'required|string',
            'shipping_address.city' => 'required|string',
            'shipping_address.state' => 'required|string',
            'shipping_address.zip_code' => 'required|string',
            'shipping_method' => 'required|array',
            'shipping_method.service_name' => 'required|string',
            'shipping_method.price' => 'required|numeric',
            'shipping_method.delivery_time' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            $data = $validator->validated();

            DB::beginTransaction();

            // Calcular totais
            $subtotal = 0;
            $orderItems = [];

            foreach ($data['cart_items'] as $cartItem) {
                $product = \App\Models\Product::with('productable')->find($cartItem['id']);
                if (!$product) {
                    throw new \Exception("Produto ID {$cartItem['id']} não encontrado");
                }

                // Acessar dados do relacionamento polimórfico
                $productable = $product->productable;
                $productName = $product->name;
                $productPrice = $product->price ?? $productable->price ?? 0;
                $artistName = $productable->artist ?? 'Artista não informado';
                $productSku = $productable->sku ?? $product->slug;
                $productImage = $productable->image_url ?? null;

                // Log do produto para debug
                \Log::info("Product Debug:", [
                    'product_id' => $product->id,
                    'product_name' => $productName,
                    'product_price' => $productPrice,
                    'artist_name' => $artistName,
                    'productable_type' => $product->productable_type,
                    'productable_data' => $productable ? $productable->toArray() : null
                ]);

                // Validar dados essenciais do produto
                if (!$productPrice || $productPrice <= 0) {
                    throw new \Exception("Produto ID {$cartItem['id']} não tem preço válido. Preço atual: " . ($productPrice ?? 'null'));
                }
                
                if (!$productName) {
                    throw new \Exception("Produto ID {$cartItem['id']} não tem nome. Nome atual: " . ($productName ?? 'null'));
                }

                $itemTotal = $productPrice * $cartItem['quantity'];
                $subtotal += $itemTotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $cartItem['quantity'],
                    'unit_price' => $productPrice,
                    'total_price' => $itemTotal,
                    'product_snapshot' => [
                        'id' => $product->id,
                        'name' => $productName,
                        'artist' => $artistName,
                        'image_url' => $productImage,
                        'sku' => $productSku,
                        'price' => $productPrice,
                        'description' => $product->description ?? '',
                        'product_type' => $product->productable_type ?? '',
                        'created_at' => $product->created_at,
                    ],
                    'product_name' => $productName,
                    'product_sku' => $productSku,
                    'product_image' => $productImage,
                    'artist_name' => $artistName,
                    'album_title' => $productName,
                ];
            }

            $shippingCost = $data['shipping_method']['price'];
            $totalAmount = $subtotal + $shippingCost;

            // Gerar número do pedido
            $orderNumber = 'WA-' . strtoupper(uniqid());

            // Criar pedido
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => $orderNumber,
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => 'whatsapp',
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total' => $totalAmount,
                'shipping_address' => $data['shipping_address'],
                'shipping_data' => [
                    'service_name' => $data['shipping_method']['service_name'],
                    'delivery_time' => $data['shipping_method']['delivery_time'],
                    'company_name' => 'Correios',
                ],
                'notes' => 'Pedido criado via WhatsApp - Aguardando confirmação de pagamento',
            ]);

            // Criar itens do pedido
            foreach ($orderItems as $itemData) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['total_price'],
                    'product_snapshot' => $itemData['product_snapshot'],
                    'product_name' => $itemData['product_name'],
                    'product_sku' => $itemData['product_sku'],
                    'product_image' => $itemData['product_image'],
                    'artist_name' => $itemData['artist_name'],
                    'album_title' => $itemData['album_title'],
                ]);
            }

            // Criar histórico inicial
            OrderStatusHistory::createHistory(
                $order->id,
                null,
                'pending',
                'Pedido criado via WhatsApp',
                ['created_via' => 'whatsapp'],
                $user->id,
                'whatsapp'
            );

            DB::commit();

            // Carregar pedido com relacionamentos
            $order->load(['items.product', 'statusHistory']);

            return response()->json([
                'success' => true,
                'message' => 'Pedido criado com sucesso',
                'data' => [
                    'order' => $order,
                    'whatsapp_data' => [
                        'phone' => '+5511947159293',
                        'message' => $this->generateWhatsAppMessage($order, $user),
                    ]
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar pedido: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Gera mensagem formatada para WhatsApp
     */
    private function generateWhatsAppMessage(Order $order, $user): string
    {
        $message = "🎵 *NOVO PEDIDO RDV DISCOS* 🎵\n\n";
        $message .= "📋 *Pedido:* #{$order->order_number}\n";
        $message .= "👤 *Cliente:* {$user->name}\n";
        $message .= "📧 *Email:* {$user->email}\n";
        $message .= "📱 *Telefone:* " . ($user->phone ?? 'Não informado') . "\n\n";

        $message .= "📦 *PRODUTOS:*\n";
        foreach ($order->items as $item) {
            $message .= "• {$item->product_name}\n";
            $message .= "  Artista: {$item->artist_name}\n";
            $message .= "  Qtd: {$item->quantity} x R$ " . number_format($item->unit_price, 2, ',', '.') . "\n";
            $message .= "  Total: R$ " . number_format($item->total_price, 2, ',', '.') . "\n\n";
        }

        $message .= "💰 *VALORES:*\n";
        $message .= "Subtotal: R$ " . number_format($order->subtotal, 2, ',', '.') . "\n";
        $message .= "Frete ({$order->shipping_data['service_name']}): R$ " . number_format($order->shipping_cost, 2, ',', '.') . "\n";
        $message .= "🔥 *TOTAL: R$ " . number_format($order->total, 2, ',', '.') . "*\n\n";

        $message .= "📍 *ENDEREÇO DE ENTREGA:*\n";
        $address = $order->shipping_address;
        $message .= "{$address['street']}, {$address['number']}\n";
        if (isset($address['complement']) && $address['complement']) {
            $message .= "{$address['complement']}\n";
        }
        $message .= "{$address['city']} - {$address['state']}\n";
        $message .= "CEP: {$address['zip_code']}\n\n";

        $message .= "🚚 *ENTREGA:* {$order->shipping_data['delivery_time']}\n\n";
        $message .= "💬 *Olá! Este pedido foi gerado automaticamente.*\n";
        $message .= "Por favor, confirme os dados e informe as opções de pagamento disponíveis.\n\n";
        $message .= "Obrigado por escolher a RDV Discos! 🎶";

        return $message;
    }
}
