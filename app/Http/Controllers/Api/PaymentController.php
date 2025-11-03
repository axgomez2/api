<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Order;
use App\Models\ShippingQuote;
use App\Models\ShippingLabel;
use App\Models\OrderStatus;
use Illuminate\Support\Facades\DB;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    protected $mercadoPagoService;

    public function __construct(MercadoPagoService $mercadoPagoService)
    {
        $this->mercadoPagoService = $mercadoPagoService;
    }

    /**
     * Criar preferência de pagamento
     */
    public function createPreference(Request $request)
    {
        try {
            Log::info('🔄 Criando preferência de pagamento', [
                'user_id' => $request->user()->id,
                'request_data' => $request->all()
            ]);

            Log::info('🔧 Configurações MercadoPago', [
                'access_token_exists' => !empty(config('services.mercadopago.access_token')),
                'access_token_length' => strlen(config('services.mercadopago.access_token') ?? ''),
                'service_class' => get_class($this->mercadoPagoService)
            ]);

            // 🔥 CORREÇÃO: Usar URLs públicas do backend em vez do frontend localhost
            $frontendUrl = config('app.frontend_url', 'http://localhost:5180');
            $backendUrl = config('app.url');

            $validator = Validator::make($request->all(), [
                'items' => 'required|array|min:1',
                'items.*.id' => 'required',
                'items.*.title' => 'required|string',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.currency_id' => 'required|string',
                'payer.email' => 'required|email',
                'payer.name' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados de pagamento inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $preferenceData = [
                'items' => $request->input('items'),
                'payer' => $request->input('payer'),
                'back_urls' => [
                    'success' => $backendUrl . '/success?redirect=' . urlencode($frontendUrl . '/checkout/success'),
                    'failure' => $backendUrl . '/failure?redirect=' . urlencode($frontendUrl . '/checkout/failure'),
                    'pending' => $backendUrl . '/pending?redirect=' . urlencode($frontendUrl . '/checkout/pending')
                ],
                'auto_return' => $request->input('auto_return', 'approved'),
                'statement_descriptor' => $request->input('statement_descriptor', 'VINYL_SHOP'),
                'external_reference' => 'order_' . time() . '_' . $request->user()->id,
                'notification_url' => config('app.url') . '/api/webhooks/mercadopago'
            ];

            Log::info('📤 Enviando para MercadoPago Service', [
                'preference_data' => $preferenceData,
                'items_count' => count($preferenceData['items']),
                'total_items_value' => array_sum(array_map(function($item) {
                    return $item['unit_price'] * $item['quantity'];
                }, $preferenceData['items'])),
                'back_urls_corrected' => true,
                'backend_url' => $backendUrl,
                'frontend_url' => $frontendUrl
            ]);

            $preference = $this->mercadoPagoService->createPreference($preferenceData);

            Log::info('✅ Resposta do MercadoPago Service', [
                'preference_id' => $preference->id ?? 'SEM_ID',
                'preference_type' => get_class($preference)
            ]);

            Log::info('✅ Preferência criada com sucesso', [
                'preference_id' => $preference->id,
                'user_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'preference_id' => $preference->id,
                'init_point' => $preference->init_point,
                'sandbox_init_point' => $preference->sandbox_init_point,
                'client_id' => $preference->client_id
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar preferência de pagamento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()->id ?? null,
                'request_data' => $request->all(),
                'preference_data' => $preferenceData ?? null,
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);

            // Retornar erro mais específico para debug
            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao criar preferência de pagamento',
                'debug_info' => config('app.debug') ? [
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ] : null
            ], 500);
        }
    }

    /**
     * Processar pagamento
     */
    public function processPayment(Request $request)
    {
        try {
            // 🔧 CORREÇÃO: Mapear bank_transfer → pix para o Mercado Pago
            $paymentMethodId = $request->input('payment_method_id');
            if ($paymentMethodId === 'bank_transfer') {
                $paymentMethodId = 'pix'; // MP usa "pix" não "bank_transfer"
            }

            Log::info('🔄 Processando pagamento', [
                'user_id' => $request->user()->id,
                'payment_method_original' => $request->input('payment_method_id'),
                'payment_method_mapped' => $paymentMethodId,
                'amount' => $request->input('transaction_amount')
            ]);

            $validator = Validator::make($request->all(), [
                'payment_method_id' => 'required|string',
                'transaction_amount' => 'required|numeric|min:0',
                'token' => 'sometimes|string',
                'installments' => 'sometimes|integer',
                'issuer_id' => 'sometimes|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados de pagamento inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // 1. Criar pedido primeiro
            $order = DB::transaction(function() use ($request, $paymentMethodId) {
                // Obter carrinho e calcular subtotal
                $cart = $request->user()->cart()->where('status', 'active')->with('items.product')->first();

                if (!$cart || $cart->items->isEmpty()) {
                    throw new \Exception("O carrinho do usuário está vazio.");
                }

                $subtotal = $cart->items->sum(function ($item) {
                    return $item->quantity * $item->price;
                });

                // Validar dados obrigatórios
                $shippingAddress = $request->input('shipping_address');
                $shippingCost = $request->input('shipping_cost', 0);
                $shippingQuoteId = $request->input('shipping_quote_id');
                $shippingService = $request->input('shipping_service');

                if (!$shippingAddress) {
                    throw new \Exception("Endereço de entrega não fornecido.");
                }

                if (!$shippingService) {
                    throw new \Exception("Serviço de frete não selecionado.");
                }

                Log::info('📦 Dados de frete recebidos:', [
                    'shipping_cost' => $shippingCost,
                    'shipping_quote_id' => $shippingQuoteId,
                    'shipping_service' => $shippingService,
                    'shipping_address' => $shippingAddress
                ]);

                // Criar pedido
                $order = Order::create([
                    'order_number' => 'ORD-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
                    'user_id' => $request->user()->id,
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'subtotal' => $subtotal,
                    'shipping_cost' => $shippingCost,
                    'total' => $request->input('transaction_amount'),
                    'shipping_address' => $shippingAddress, // Laravel converte automaticamente para JSON
                    'shipping_data' => $shippingService, // Armazena dados do serviço de frete
                    'payment_method' => $paymentMethodId,
                    'shipping_quote_id' => $shippingQuoteId,
                ]);

                // Criar itens do pedido
                foreach ($cart->items as $item) {
                    $product = $item->product; // Acessa o produto relacionado
                    
                    // Dados padrão
                    $artistName = 'N/A';
                    $albumTitle = 'N/A';
                    
                    // Se o produto tem productable (polimórfico)
                    if ($product->productable) {
                        $productable = $product->productable;
                        
                        // Se for VinylMaster, pegar dados do vinil
                        if ($productable instanceof \App\Models\VinylMaster) {
                            $albumTitle = $productable->title ?? 'N/A';
                            
                            // Pegar primeiro artista se existir
                            if ($productable->artists && $productable->artists->isNotEmpty()) {
                                $artistName = $productable->artists->first()->name;
                            }
                        }
                    }

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_snapshot' => json_encode($product->toArray()), // Snapshot completo
                        'product_name' => $product->name ?? 'Produto',
                        'quantity' => $item->quantity,
                        'unit_price' => $item->price,
                        'total_price' => $item->quantity * $item->price,
                        'artist_name' => $artistName,
                        'album_title' => $albumTitle,
                    ]);
                }

                // Criar order_status
                OrderStatus::create([
                    'order_id' => $order->id,
                    'status_to' => 'pending',
                    'notes' => 'Pedido criado, aguardando pagamento',
                    'change_type' => 'automatic',
                ]);

                // 🔥 Arquivar carrinhos antigos completed do usuário antes de marcar novo
                Cart::where('user_id', $request->user()->id)
                    ->where('status', 'completed')
                    ->update(['status' => 'archived']);
                
                // Marcar carrinho atual como completed
                $cart->update(['status' => 'completed']);

                return $order;
            });

            // 2. Preparar dados para o Mercado Pago
            $paymentData = [
                'payment_method_id' => $paymentMethodId,
                'transaction_amount' => $request->input('transaction_amount'),
                'description' => "Pedido #{$order->id} - Vinyl Shop",
                'external_reference' => (string) $order->id,
                'payer' => [
                    'email' => $request->user()->email,
                    'first_name' => $request->user()->name ?? 'Cliente',
                    'last_name' => 'Vinyl Shop'
                ],
                'notification_url' => config('app.url') . '/api/webhooks/mercadopago'
            ];

            // Adicionar dados específicos do método de pagamento
            if ($request->token) {
                $paymentData['token'] = $request->token;
            }

            if ($request->installments) {
                $paymentData['installments'] = $request->installments;
            }

            if ($request->issuer_id) {
                $paymentData['issuer_id'] = $request->issuer_id;
            }

            $payment = $this->mercadoPagoService->createPayment($paymentData);

            // Salvar transação e atualizar pedido
            DB::transaction(function() use ($payment, $order) {
                // Salvar transação
                PaymentTransaction::create([
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                    'payment_type' => $payment->payment_type_id,
                    'payment_method' => $payment->payment_method_id,
                    'transaction_amount' => $payment->transaction_amount,
                    'payer_data' => json_encode($payment->payer),
                    'mercadopago_response' => json_encode($payment),

                    // Campos adicionais para robustez
                    'external_reference' => $payment->external_reference,
                    'status_detail' => $payment->status_detail,
                    'net_received_amount' => $payment->transaction_details->net_received_amount ?? 0,
                    'total_paid_amount' => $payment->transaction_details->total_paid_amount ?? 0,
                    'currency_id' => $payment->currency_id,
                    'date_approved' => $payment->date_approved,
                    'date_created' => $payment->date_created,
                    'date_last_updated' => $payment->date_last_updated,
                    'pix_qr_code' => $payment->point_of_interaction->transaction_data->qr_code ?? null,
                    'pix_qr_code_base64' => $payment->point_of_interaction->transaction_data->qr_code_base64 ?? null,
                ]);

                // Atualizar pedido
                $order->update([
                    'payment_status' => $payment->status,
                    'payment_id' => $payment->id
                ]);

                // Atualizar status do pedido
                OrderStatus::create([
                    'order_id' => $order->id,
                    'status_from' => $order->payment_status,
                    'status_to' => $payment->status,
                    'notes' => $this->getStatusMessage($payment->status),
                    'change_type' => 'webhook',
                    'webhook_source' => 'mercadopago',
                ]);

                // Se for PIX, gerar etiqueta de envio
                if ($payment->payment_method_id === 'pix' && $payment->status === 'pending') {
                    $order->load('shippingQuote'); // Recarrega a relação
                    $this->generateShippingLabel($order);
                }
            });

            Log::info('✅ Pagamento processado', [
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'user_id' => $request->user()->id,
                'order_id' => $order->id
            ]);

            return response()->json([
                'success' => true,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'status_detail' => $payment->status_detail,
                'payment_method_id' => $payment->payment_method_id,
                'transaction_amount' => $payment->transaction_amount,
                'date_created' => $payment->date_created,
                'qr_code' => $payment->point_of_interaction->transaction_data->qr_code ?? null,
                'qr_code_base64' => $payment->point_of_interaction->transaction_data->qr_code_base64 ?? null,
                'ticket_url' => $payment->transaction_details->external_resource_url ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao processar pagamento', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()->id ?? null,
                'request_data' => $request->except(['token', 'cvv'])
            ]);

            // Em desenvolvimento, mostrar erro detalhado
            $errorMessage = config('app.debug') 
                ? $e->getMessage() . ' (Linha: ' . $e->getLine() . ')' 
                : 'Erro ao processar pagamento. Tente novamente.';

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'error_details' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500);
        }
    }

    /**
     * Obter status do pagamento
     */
    public function getPaymentStatus(Request $request, $paymentId)
    {
        try {
            Log::info('🔄 Consultando status do pagamento', [
                'payment_id' => $paymentId,
                'user_id' => $request->user()->id
            ]);

            $payment = $this->mercadoPagoService->getPayment($paymentId);

            Log::info('✅ Status do pagamento consultado', [
                'payment_id' => $paymentId,
                'status' => $payment->status,
                'user_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'status_detail' => $payment->status_detail,
                'payment_method_id' => $payment->payment_method_id,
                'transaction_amount' => $payment->transaction_amount,
                'date_created' => $payment->date_created,
                'date_approved' => $payment->date_approved,
                'qr_code' => $payment->point_of_interaction->transaction_data->qr_code ?? null,
                'qr_code_base64' => $payment->point_of_interaction->transaction_data->qr_code_base64 ?? null,
                'ticket_url' => $payment->transaction_details->external_resource_url ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao consultar status do pagamento', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
                'user_id' => $request->user()->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao consultar status do pagamento'
            ], 500);
        }
    }

    /**
     * Webhook do Mercado Pago
     */
    public function webhook(Request $request)
    {
        try {
            Log::info('🔔 Webhook recebido do Mercado Pago', [
                'type' => $request->input('type'),
                'data' => $request->input('data'),
                'headers' => $request->headers->all()
            ]);

            $type = $request->input('type');
            $data = $request->input('data');

            if ($type === 'payment') {
                $paymentId = $data['id'] ?? null;

                if ($paymentId) {
                    $payment = $this->mercadoPagoService->getPayment($paymentId);

                    Log::info('📋 Atualização de pagamento via webhook', [
                        'payment_id' => $paymentId,
                        'status' => $payment->status,
                        'external_reference' => $payment->external_reference
                    ]);

                    // Aqui você pode implementar a lógica para atualizar o status do pedido
                    // Por exemplo: atualizar status na tabela de pedidos
                }
            }

            return response()->json(['success' => true], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erro no webhook do Mercado Pago', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Gerar etiqueta de envio
     */
    private function generateShippingLabel(Order $order)
    {
        try {
            // Buscar shipping_quote
            $shippingQuote = $order->shippingQuote;
            if (!$shippingQuote) {
                Log::warning('❌ Shipping quote não encontrada para o pedido', [
                    'order_id' => $order->id
                ]);
                return;
            }

            // Criar etiqueta no Melhor Envio
            $shippingLabel = ShippingLabel::create([
                'order_id' => $order->id,
                'shipping_quote_id' => $shippingQuote->id,
                'status' => 'pending',
                'tracking_code' => null,
                'label_url' => null
            ]);

            // Atualizar status
            OrderStatus::create([
                'order_id' => $order->id,
                'status' => 'label_pending',
                'description' => 'Etiqueta de envio em geração'
            ]);

            Log::info('✅ Etiqueta de envio criada', [
                'order_id' => $order->id,
                'shipping_label_id' => $shippingLabel->id
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao gerar etiqueta de envio', [
                'error' => $e->getMessage(),
                'order_id' => $order->id
            ]);
        }
    }

    /**
     * Obter detalhes do pagamento (incluindo QR Code)
     */
    public function getPaymentDetails($paymentId)
    {
        try {
            $payment = $this->mercadoPagoService->getPayment($paymentId);

            // Se for PIX, incluir dados do QR Code
            if ($payment->payment_method_id === 'pix') {
                return response()->json([
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                    'transaction_amount' => $payment->transaction_amount,
                    'qr_code' => $payment->point_of_interaction->transaction_data->qr_code ?? null,
                    'qr_code_base64' => $payment->point_of_interaction->transaction_data->qr_code_base64 ?? null,
                    'message' => $this->getStatusMessage($payment->status)
                ]);
            }

            // Para outros métodos
            return response()->json([
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'transaction_amount' => $payment->transaction_amount,
                'message' => $this->getStatusMessage($payment->status)
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao obter detalhes do pagamento', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId
            ]);

            return response()->json([
                'message' => 'Erro ao obter detalhes do pagamento'
            ], 500);
        }
    }

    private function getStatusMessage($status)
    {
        $messages = [
            'pending' => 'Pagamento pendente, aguardando confirmação.',
            'approved' => 'Pagamento aprovado com sucesso.',
            'authorized' => 'Pagamento autorizado.',
            'in_process' => 'Pagamento em processamento.',
            'in_mediation' => 'Pagamento em mediação.',
            'rejected' => 'Pagamento rejeitado.',
            'cancelled' => 'Pagamento cancelado.',
            'refunded' => 'Pagamento reembolsado.',
            'charged_back' => 'Pagamento estornado (chargeback).',
        ];

        return $messages[$status] ?? 'Status desconhecido';
    }
}
