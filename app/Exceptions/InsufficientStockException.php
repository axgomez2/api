<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    protected $productName;
    protected $availableStock;
    protected $requestedQuantity;

    public function __construct(
        string $productName, 
        int $availableStock, 
        int $requestedQuantity = null
    ) {
        $this->productName = $productName;
        $this->availableStock = $availableStock;
        $this->requestedQuantity = $requestedQuantity;

        $message = $requestedQuantity 
            ? "Estoque insuficiente para '{$productName}'. Disponível: {$availableStock}, Solicitado: {$requestedQuantity}"
            : "Produto '{$productName}' sem estoque. Disponível: {$availableStock}";

        parent::__construct($message, 400);
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getAvailableStock(): int
    {
        return $this->availableStock;
    }

    public function getRequestedQuantity(): ?int
    {
        return $this->requestedQuantity;
    }

    /**
     * Renderizar exceção como resposta JSON
     */
    public function render($request)
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_type' => 'insufficient_stock',
            'product' => [
                'name' => $this->productName,
                'available_stock' => $this->availableStock,
                'requested_quantity' => $this->requestedQuantity
            ]
        ], 400);
    }
}
