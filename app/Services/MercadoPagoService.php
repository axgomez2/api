<?php

namespace App\Services;

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;

class MercadoPagoService
{
    protected $paymentClient;
    protected $preferenceClient;

    public function __construct()
    {
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
        $this->paymentClient = new PaymentClient();
        $this->preferenceClient = new PreferenceClient();
    }

    /**
     * Criar preferência de pagamento
     */
    public function createPreference(array $data)
    {
        try {
            \Log::info('🔄 MercadoPagoService::createPreference - Iniciando', [
                'data_keys' => array_keys($data),
                'items_count' => count($data['items'] ?? []),
                'access_token_configured' => !empty(config('services.mercadopago.access_token')),
                'access_token_length' => strlen(config('services.mercadopago.access_token') ?? ''),
                'access_token_prefix' => substr(config('services.mercadopago.access_token') ?? '', 0, 10)
            ]);

            // Validar dados essenciais
            $this->validatePreferenceData($data);

            \Log::info('📤 MercadoPagoService::createPreference - Enviando dados', [
                'full_data' => $data,
                'notification_url' => $data['notification_url'] ?? 'NONE',
                'items_detail' => $data['items'] ?? []
            ]);

            $preference = $this->preferenceClient->create($data);

            \Log::info('✅ MercadoPagoService::createPreference - Sucesso', [
                'preference_id' => $preference->id,
                'init_point' => $preference->init_point ?? 'SEM_INIT_POINT',
                'sandbox_init_point' => $preference->sandbox_init_point ?? 'SEM_SANDBOX'
            ]);

            return $preference;

        } catch (MPApiException $e) {
            // Capturar erro específico da API do Mercado Pago
            $apiResponse = null;
            $statusCode = null;
            $responseBody = null;

            try {
                $apiResponse = $e->getApiResponse();
                $statusCode = $apiResponse?->getStatusCode();
                $responseBody = $apiResponse?->getContent();
            } catch (\Exception $responseException) {
                // Ignore if we can't get response details
            }

            \Log::error('❌ MercadoPagoService::createPreference - Erro API MP', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'status_code' => $statusCode,
                'response_body' => $responseBody,
                'api_response_exists' => $apiResponse !== null,
                'data_sent' => $data,
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;

        } catch (\Exception $e) {
            \Log::error('❌ MercadoPagoService::createPreference - Erro Geral', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'data_sent' => $data,
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Validar dados da preferência
     */
    private function validatePreferenceData(array $data)
    {
        $requiredFields = ['items', 'payer'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw new \InvalidArgumentException('Campos obrigatórios ausentes: ' . implode(', ', $missingFields));
        }

        // Validar items
        if (empty($data['items']) || !is_array($data['items'])) {
            throw new \InvalidArgumentException('Items deve ser um array não vazio');
        }

        foreach ($data['items'] as $index => $item) {
            $requiredItemFields = ['id', 'title', 'quantity', 'unit_price', 'currency_id'];
            foreach ($requiredItemFields as $field) {
                if (!isset($item[$field])) {
                    throw new \InvalidArgumentException("Item {$index}: campo '{$field}' é obrigatório");
                }
            }

            // Validar tipos
            if (!is_numeric($item['quantity']) || $item['quantity'] <= 0) {
                throw new \InvalidArgumentException("Item {$index}: quantity deve ser um número positivo");
            }

            if (!is_numeric($item['unit_price']) || $item['unit_price'] <= 0) {
                throw new \InvalidArgumentException("Item {$index}: unit_price deve ser um número positivo");
            }
        }

        // Validar payer
        if (empty($data['payer']['email'])) {
            throw new \InvalidArgumentException('Email do pagador é obrigatório');
        }

        if (!filter_var($data['payer']['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email do pagador inválido');
        }

        \Log::info('✅ MercadoPagoService::validatePreferenceData - Dados válidos', [
            'items_count' => count($data['items']),
            'payer_email' => $data['payer']['email']
        ]);
    }

    /**
     * Criar pagamento
     */
    public function createPayment(array $data)
    {
        try {
            \Log::info('🔄 MercadoPagoService::createPayment - Iniciando', [
                'data_keys' => array_keys($data),
                'payment_method_id' => $data['payment_method_id'] ?? 'NOT_SET',
                'transaction_amount' => $data['transaction_amount'] ?? 'NOT_SET',
                'access_token_configured' => !empty(config('services.mercadopago.access_token')),
                'full_data' => $data
            ]);

            $payment = $this->paymentClient->create($data);

            \Log::info('✅ MercadoPagoService::createPayment - Sucesso', [
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'payment_method_id' => $payment->payment_method_id
            ]);

            return $payment;

        } catch (MPApiException $e) {
            // Capturar erro específico da API do Mercado Pago
            $apiResponse = null;
            $statusCode = null;
            $responseBody = null;

            try {
                $apiResponse = $e->getApiResponse();
                $statusCode = $apiResponse?->getStatusCode();
                $responseBody = $apiResponse?->getContent();
            } catch (\Exception $responseException) {
                // Ignore if we can't get response details
            }

            \Log::error('❌ MercadoPagoService::createPayment - Erro API MP', [
                'error' => $e->getMessage(),
                'status_code' => $statusCode,
                'response_body' => $responseBody,
                'data_sent' => $data,
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;

        } catch (\Exception $e) {
            \Log::error('❌ MercadoPagoService::createPayment - Erro Geral', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'data_sent' => $data,
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Buscar pagamento por ID
     */
    public function getPayment($paymentId)
    {
        return $this->paymentClient->get($paymentId);
    }
}
