<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    /**
     * Retornar configurações públicas da aplicação
     * GET /api/config
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'mercadopago' => [
                'public_key' => config('services.mercadopago.public_key'),
            ],
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
            ]
        ]);
    }
}
