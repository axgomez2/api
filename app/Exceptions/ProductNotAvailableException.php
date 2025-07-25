<?php

namespace App\Exceptions;

use Exception;

class ProductNotAvailableException extends Exception
{
    public function __construct($message = 'Produto não está disponível em estoque', $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}