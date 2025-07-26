<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\ClientAuthController;
use App\Http\Controllers\SeoController;

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

// 🔥 ROTA DE TESTE GOOGLE OAUTH (para desenvolvimento local)
Route::get('/test-google', [ClientAuthController::class, 'redirectToGoogle'])
    ->name('test.google');

Route::get('/test-google-callback', [ClientAuthController::class, 'handleGoogleCallback'])
    ->name('test.google.callback');

// 🔥 ROTAS SEO - Open Graph Meta Tags para WhatsApp/Facebook
// Estas rotas servem páginas HTML com meta tags para compartilhamento
Route::get('/vinyl/{slug}', [SeoController::class, 'productPage'])
    ->name('seo.vinyl');
    
Route::get('/product/{slug}', [SeoController::class, 'productPage'])
    ->name('seo.product');
    
// Rota genérica para compatibilidade
Route::get('/p/{slug}', [SeoController::class, 'productPage'])
    ->name('seo.product.short');

// 🧪 ROTA DE TESTE SEO
Route::get('/seo-test', function() {
    return response('<h1>SEO Controller funcionando!</h1><p>Teste: <a href="/vinyl/1">/vinyl/1</a></p>')
        ->header('Content-Type', 'text/html');
});

