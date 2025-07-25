<?php

namespace App\Exceptions;

use Exception;

class ProductAlreadyInCartException extends Exception
{
    public function __construct($message = 'Produto já está no carrinho', $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}