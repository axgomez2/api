<?php

namespace App\Services;

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;

class MercadoPagoService
{
    protected $paymentClient;

    public function __construct()
    {
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
        $this->paymentClient = new PaymentClient();
    }

    /**
     * Criar pagamento
     */
    public function createPayment(array $data)
    {
        return $this->paymentClient->create($data);
    }

    /**
     * Buscar pagamento por ID
     */
    public function getPayment($paymentId)
    {
        return $this->paymentClient->get($paymentId);
    }
}
