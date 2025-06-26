<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\ClientAuthController;

// 🔥 ROTA DE CONEXÃO MELHOR ENVIO (sem prefixo /api)
Route::get('/shipping/connect', [ShippingController::class, 'redirectToSandbox'])
    ->name('shipping.connect');

// 🔥 ROTA DE CALLBACK MELHOR ENVIO (sem prefixo /api)
Route::get('/auth/melhorenvio/callback', [ShippingController::class, 'handleSandboxCallback'])
    ->name('shipping.callback');

// 🔥 ROTA DE VERIFICAÇÃO DE EMAIL (processa no backend e redireciona para o frontend)
Route::get('/email/verify/{id}/{hash}', [ClientAuthController::class, 'verifyEmail'])
    ->middleware(['signed'])
    ->name('verification.verify');

