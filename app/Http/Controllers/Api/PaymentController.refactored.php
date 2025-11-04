<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Order;
use App\Models\Cart;
use App\Models\PaymentTransaction;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    protected $mercadoPagoService;

    public function __construct(MercadoPagoService $mercadoPagoService)
    {
        $this->mercadoPagoService = $mercadoPagoService;
    }

    /**
     * Processar pagamento de um pedido JÁ CRIADO
     * O pedido deve ser criado ANTES via OrderController@store
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'payment_method_id' => 'required|string',
            'token' => 'sometimes|string',
            'installments' => 'sometimes|integer|min:1',
            'issuer_id' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados de pagamento inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            // ✅ 1. BUSCAR PEDIDO (com relacionamentos)
            $order = Order::with(['items', 'user', 'shippingQuote'])
                          ->findOrFail($request->order_id);

            // Validar se pedido pertence ao usuário autenticado
            if ($order->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não autorizado a processar este pedido'
                ], 403);
            }

            // Validar se pedido ainda está pendente
            if ($order->payment_status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este pedido já foi processado',
                    'current_status' => $order->payment_status
                ], 400);
            }

            Log::info('🔄 Processando pagamento', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'user_id' => $user->id,
                'payment_method' => $request->payment_method_id,
                'amount' => $order->total
            ]);

            // ✅ 2. MAPEAR MÉTODO DE PAGAMENTO
            $paymentMethodId = $request->payment_method_id;
            if ($paymentMethodId === 'bank_transfer') {
                $paymentMethodId = 'pix'; // Mercado Pago usa "pix"
            }

            // ✅ 3. PREPARAR DADOS PARA MERCADO PAGO
            $paymentData = [
                'payment_method_id' => $paymentMethodId,
                'transaction_amount' => (float) $order->total,
                'description' => "Pedido {$order->order_number}",
                'external_reference' => (string) $order->id,
                'payer' => [
                    'email' => $order->user->email,
                    'first_name' => $order->user->name ?? 'Cliente',
                ],
                'notification_url' => config('app.url') . '/api/webhooks/mercadopago'
            ];

            // Adicionar token se for cartão de crédito
            if ($request->has('token')) {
                $paymentData['token'] = $request->token;
            }

            // Adicionar parcelas se especificado
            if ($request->has('installments')) {
                $paymentData['installments'] = $request->installments;
            }

            // Adicionar emissor se especificado
            if ($request->has('issuer_id')) {
                $paymentData['issuer_id'] = $request->issuer_id;
            }

            Log::info('📤 Enviando para Mercado Pago', [
                'payment_data' => array_except($paymentData, ['token']),
                'order_id' => $order->id
            ]);

            // ✅ 4. PROCESSAR PAGAMENTO NO MERCADO PAGO
            $payment = $this->mercadoPagoService->createPayment($paymentData);

            Log::info('✅ Resposta do Mercado Pago', [
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'status_detail' => $payment->status_detail,
                'order_id' => $order->id
            ]);

            // ✅ 5. SALVAR TRANSAÇÃO E ATUALIZAR PEDIDO
            DB::transaction(function() use ($payment, $order) {
                // Salvar transação de pagamento
                PaymentTransaction::create([
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                    'payment_type' => $payment->payment_type_id,
                    'payment_method' => $payment->payment_method_id,
                    'transaction_amount' => $payment->transaction_amount,
                    'payer_data' => json_encode($payment->payer),
                    'mercadopago_response' => json_encode($payment),
                    'external_reference' => $payment->external_reference,
                    'status_detail' => $payment->status_detail,
                    'currency_id' => $payment->currency_id,
                    'date_approved' => $payment->date_approved ?? null,
                    'date_created' => $payment->date_created,
                    'date_last_updated' => $payment->date_last_updated,
                    
                    // PIX específico
                    'pix_qr_code' => $payment->point_of_interaction->transaction_data->qr_code ?? null,
                    'pix_qr_code_base64' => $payment->point_of_interaction->transaction_data->qr_code_base64 ?? null,
                ]);

                // Atualizar status de pagamento do pedido
                $oldPaymentStatus = $order->payment_status;
                $order->update([
                    'payment_id' => $payment->id,
                    'payment_status' => $payment->status,
                ]);

                // Criar histórico de status
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'old_status' => $oldPaymentStatus,
                    'new_status' => $payment->status,
                    'comment' => $this->getStatusMessage($payment->status),
                    'change_type' => 'automatic',
                    'webhook_source' => 'mercadopago_direct',
                ]);

                // ✅ SE PAGAMENTO APROVADO → MARCAR CARRINHO COMO CONVERTIDO
                if ($payment->status === 'approved' && $order->cart_id) {
                    $this->markCartAsConverted($order);
                }
            });

            Log::info('✅ Pagamento processado com sucesso', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'cart_converted' => $payment->status === 'approved'
            ]);

            // ✅ 6. RETORNAR RESPOSTA
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
                
                // Dados específicos do método
                'qr_code' => $payment->point_of_interaction->transaction_data->qr_code ?? null,
                'qr_code_base64' => $payment->point_of_interaction->transaction_data->qr_code_base64 ?? null,
                'ticket_url' => $payment->transaction_details->external_resource_url ?? null,
                
                // Mensagem amigável
                'message' => $this->getStatusMessage($payment->status)
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao processar pagamento', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()->id ?? null,
                'order_id' => $request->order_id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar pagamento. Tente novamente.',
                'debug_info' => config('app.debug') ? [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                ] : null
            ], 500);
        }
    }

    /**
     * Webhook do Mercado Pago
     * Processa atualizações de status de pagamento
     */
    public function webhook(Request $request)
    {
        try {
            Log::info('🔔 Webhook recebido do Mercado Pago', [
                'type' => $request->input('type'),
                'data' => $request->input('data'),
                'headers' => [
                    'x-signature' => $request->header('x-signature'),
                    'x-request-id' => $request->header('x-request-id'),
                ]
            ]);

            $type = $request->input('type');
            $data = $request->input('data');

            // ✅ PROCESSAR APENAS EVENTOS DE PAGAMENTO
            if ($type === 'payment') {
                $paymentId = $data['id'] ?? null;

                if (!$paymentId) {
                    Log::warning('⚠️ Webhook sem payment_id');
                    return response()->json(['success' => false], 400);
                }

                // Buscar informações do pagamento no Mercado Pago
                $payment = $this->mercadoPagoService->getPayment($paymentId);

                Log::info('📋 Detalhes do pagamento via webhook', [
                    'payment_id' => $paymentId,
                    'status' => $payment->status,
                    'status_detail' => $payment->status_detail,
                    'external_reference' => $payment->external_reference
                ]);

                // Buscar pedido pela external_reference
                $order = Order::with(['items.product', 'cart'])->find($payment->external_reference);

                if (!$order) {
                    Log::warning('⚠️ Pedido não encontrado para webhook', [
                        'payment_id' => $paymentId,
                        'external_reference' => $payment->external_reference,
                    ]);
                    return response()->json(['success' => false, 'error' => 'Order not found'], 404);
                }

                // ✅ ATUALIZAR STATUS DO PEDIDO
                DB::transaction(function() use ($order, $payment) {
                    $oldStatus = $order->payment_status;

                    // Atualizar pedido
                    $order->update([
                        'payment_status' => $payment->status,
                        'payment_id' => $payment->id,
                    ]);

                    // Atualizar transação existente ou criar nova
                    PaymentTransaction::updateOrCreate(
                        ['payment_id' => $payment->id],
                        [
                            'order_id' => $order->id,
                            'status' => $payment->status,
                            'status_detail' => $payment->status_detail,
                            'date_approved' => $payment->date_approved ?? null,
                            'date_last_updated' => $payment->date_last_updated,
                            'mercadopago_response' => json_encode($payment),
                        ]
                    );

                    // Criar histórico
                    OrderStatusHistory::create([
                        'order_id' => $order->id,
                        'old_status' => $oldStatus,
                        'new_status' => $payment->status,
                        'comment' => "Webhook: {$this->getStatusMessage($payment->status)}",
                        'change_type' => 'webhook',
                        'webhook_source' => 'mercadopago',
                    ]);

                    // ✅ SE APROVADO → MARCAR CARRINHO E NOTIFICAR
                    if ($payment->status === 'approved') {
                        if ($order->cart_id) {
                            $this->markCartAsConverted($order);
                        }
                        
                        // TODO: Enviar email de confirmação
                        // TODO: Notificar sistema de envio
                        
                        Log::info('🎉 Pagamento aprovado via webhook', [
                            'order_id' => $order->id,
                            'payment_id' => $payment->id
                        ]);
                    }

                    // ✅ SE REJEITADO/CANCELADO → RESTAURAR ESTOQUE
                    if (in_array($payment->status, ['rejected', 'cancelled', 'refunded'])) {
                        Log::info('↩️ Restaurando estoque (pagamento ' . $payment->status . ')', [
                            'order_id' => $order->id
                        ]);

                        foreach ($order->items as $item) {
                            if ($item->product->productable?->vinylSec) {
                                $item->product->productable->vinylSec->increment('stock', $item->quantity);
                            } else if ($item->product->stock !== null) {
                                $item->product->increment('stock', $item->quantity);
                            }
                        }
                    }
                });

                Log::info('✅ Webhook processado com sucesso', [
                    'payment_id' => $paymentId,
                    'order_id' => $order->id,
                    'new_status' => $payment->status
                ]);
            }

            return response()->json(['success' => true], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao processar webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['success' => false], 500);
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
                'message' => $this->getStatusMessage($payment->status)
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao consultar status do pagamento', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao consultar status do pagamento'
            ], 500);
        }
    }

    /**
     * Marcar carrinho como convertido
     */
    private function markCartAsConverted(Order $order): void
    {
        try {
            // Arquivar carrinhos antigos convertidos do usuário
            Cart::where('user_id', $order->user_id)
                ->where('status', 'converted')
                ->where('id', '!=', $order->cart_id)
                ->update(['status' => 'archived']);

            // Marcar carrinho atual como convertido
            Cart::where('id', $order->cart_id)->update([
                'status' => 'converted',
                'converted_at' => now(),
                'order_id' => $order->id,
            ]);

            Log::info('✅ Carrinho marcado como convertido', [
                'cart_id' => $order->cart_id,
                'order_id' => $order->id
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erro ao marcar carrinho como convertido', [
                'cart_id' => $order->cart_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Obter mensagem amigável do status
     */
    private function getStatusMessage($status): string
    {
        $messages = [
            'pending' => 'Pagamento pendente, aguardando confirmação.',
            'approved' => 'Pagamento aprovado com sucesso! 🎉',
            'authorized' => 'Pagamento autorizado.',
            'in_process' => 'Pagamento em processamento...',
            'in_mediation' => 'Pagamento em mediação.',
            'rejected' => 'Pagamento rejeitado. Tente outro método.',
            'cancelled' => 'Pagamento cancelado.',
            'refunded' => 'Pagamento reembolsado.',
            'charged_back' => 'Pagamento estornado (chargeback).',
        ];

        return $messages[$status] ?? 'Status desconhecido';
    }
}
