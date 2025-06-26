<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ClientAuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\VinylMasterController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\WantlistController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Http\Request;

// 🔥 ROTA DE DEBUG (PRIMEIRA para não conflitar)
Route::get('/debug/routes', function () {
    $routes = collect(Route::getRoutes())->map(function ($route) {
        return [
            'method' => implode('|', $route->methods()),
            'uri' => $route->uri(),
            'name' => $route->getName(),
            'action' => $route->getActionName(),
        ];
    });

    return response()->json([
        'total_routes' => $routes->count(),
        'api_routes' => $routes->filter(function($route) {
            return str_starts_with($route['uri'], 'api/');
        })->values(),
        'client_routes' => $routes->filter(function($route) {
            return str_starts_with($route['uri'], 'api/client');
        })->values()
    ]);
});

// 🔥 ROTAS CLIENT - SEM AUTENTICAÇÃO
Route::post('/client/register', [ClientAuthController::class, 'register'])->name('client.register');
Route::post('/client/login', [ClientAuthController::class, 'login'])->middleware(['throttle:5,1'])->name('client.login');

Route::get('/client/redirectToGoogle', [ClientAuthController::class, 'redirectToGoogle'])->name('client.google.redirect');
Route::get('/client/handleGoogleCallback', [ClientAuthController::class, 'handleGoogleCallback'])->name('client.google.callback');

// Rotas de verificação de email
Route::get('/client/email/verify/{id}/{hash}', [ClientAuthController::class, 'verifyEmail'])
    ->middleware(['signed'])
    ->name('client.verification.verify');

// 🔥 ROTAS DE SHIPPING - SEM AUTENTICAÇÃO (OAuth flow)
Route::prefix('shipping')->group(function () {
    // passo 1: redirecionar pro sandbox consent
    Route::get('connect', [ShippingController::class, 'redirectToSandbox']);
    // Debug para verificar configurações
    Route::get('debug', [ShippingController::class, 'debug']);
});



// 🔥 ROTAS CLIENT PROTEGIDAS - USANDO MIDDLEWARE CUSTOMIZADO
Route::middleware('client.auth')->group(function () {
    // Rotas do cliente autenticado
    Route::get('/client/me', [ClientAuthController::class, 'me']);
    Route::put('/client/profile', [ClientAuthController::class, 'updateProfile']);
    Route::put('/client/password', [ClientAuthController::class, 'changePassword']);
    Route::post('/client/resend-verification', [ClientAuthController::class, 'resendVerificationEmail']);
    Route::post('/client/logout', [ClientAuthController::class, 'logout']);

    // Carrinho
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/', [CartController::class, 'store']);
        Route::delete('/{productId}', [CartController::class, 'destroy']);
        Route::delete('/', [CartController::class, 'clear']);
        Route::get('/{productId}/check', [CartController::class, 'checkItem']); // Nova rota para verificar item
    });

    // Wishlist
    Route::prefix('wishlist')->group(function () {
        Route::get('/', [WishlistController::class, 'index']);
        Route::post('/', [WishlistController::class, 'store']);
        Route::get('/{productId}/check', [WishlistController::class, 'check']); // 🔥 NOVA ROTA
        Route::delete('/{productId}', [WishlistController::class, 'destroy']);
        Route::delete('/', [WishlistController::class, 'clear']);
    });

    // Wantlist
    Route::prefix('wantlist')->group(function () {
        Route::get('/', [WantlistController::class, 'index']);
        Route::post('/', [WantlistController::class, 'store']);
        Route::delete('/{productId}', [WantlistController::class, 'destroy']);
        Route::delete('/', [WantlistController::class, 'clear']);
    });

    // Endereços
    Route::prefix('addresses')->group(function () {
        Route::get('/', [AddressController::class, 'index']);
        Route::post('/', [AddressController::class, 'store']);
        Route::put('/{id}', [AddressController::class, 'update']);
        Route::delete('/{id}', [AddressController::class, 'destroy']);
        Route::put('/{id}/default', [AddressController::class, 'setDefault']);
    });

    // Rotas de shipping que precisam de autenticação
    Route::prefix('shipping')->group(function () {
        Route::post('rates', [ShippingController::class, 'rates']);
        Route::post('labels', [ShippingController::class, 'createLabel']);
        Route::post('select-service', [ShippingController::class, 'selectService']);
        Route::get('quotes', [ShippingController::class, 'getQuotes']);
        Route::get('quotes/{id}', [ShippingController::class, 'getQuote']);
    });

    // Pedidos
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::put('/{id}/cancel', [OrderController::class, 'cancel']);
        Route::get('/{id}/tracking', [OrderController::class, 'tracking']);
        Route::put('/{id}/retry-payment', [OrderController::class, 'retryPayment']);
    });

    // 🔥 ROTAS DE PAGAMENTO - MERCADO PAGO
    Route::prefix('payment')->group(function () {
        Route::post('create-preference', [PaymentController::class, 'createPreference']);
        Route::post('process', [PaymentController::class, 'processPayment']);
        Route::get('status/{paymentId}', [PaymentController::class, 'getPaymentStatus']);
    });

});

// 🔥 WEBHOOK MERCADO PAGO (SEM AUTENTICAÇÃO)
Route::post('/webhooks/mercadopago', [PaymentController::class, 'webhook']);

// Rotas para Products (principal para e-commerce)
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/search', [ProductController::class, 'search']);
    Route::get('/type/{typeId}', [ProductController::class, 'filterByType']);
    Route::get('/vinyl', [ProductController::class, 'vinylProducts']);
    Route::get('/vinyl/latest/{limit?}', [ProductController::class, 'latestVinyls']);
    Route::get('/vinyl/{slug}', [ProductController::class, 'vinylDetail']);
    Route::get('/{slug}', [ProductController::class, 'show']);
});

// Rotas para VinylMaster (dados específicos sobre discos)
Route::prefix('vinyl')->group(function () {
    Route::get('/', [VinylMasterController::class, 'index']);
    Route::get('/year/{year}', [VinylMasterController::class, 'filterByYear']);
    Route::get('/label/{labelId}', [VinylMasterController::class, 'filterByLabel']);
    Route::get('/search', [VinylMasterController::class, 'search']);
    Route::get('/{slug}', [VinylMasterController::class, 'show']);
});

// Rotas para Categorias (CatStyleShop)
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/{slug}', [CategoryController::class, 'show']);
    Route::get('/{slug}/products', [CategoryController::class, 'productsByCategory']);
});

// Rota de teste do Mercado Pago
Route::get('/test-mercadopago', function () {
    try {
        $mercadoPagoService = app(App\Services\MercadoPagoService::class);

        $testData = [
            "items" => [
                [
                    "id" => "test-item",
                    "title" => "Produto de Teste",
                    "quantity" => 1,
                    "currency_id" => "BRL",
                    "unit_price" => 100.00
                ]
            ],
            "payer" => [
                "email" => "teste@example.com"
            ],
            "back_urls" => [
                "success" => config('app.frontend_url') . "/checkout/success",
                "failure" => config('app.frontend_url') . "/checkout/failure",
                "pending" => config('app.frontend_url') . "/checkout/pending"
            ]
        ];

        $preference = $mercadoPagoService->createPreference($testData);

        return response()->json([
            'success' => true,
            'preference_id' => $preference->id,
            'init_point' => $preference->init_point
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 400);
    }
});

// Rota de teste para pagamento PIX
Route::get('/test-pix-payment', function () {
    try {
        $mercadoPagoService = app(App\Services\MercadoPagoService::class);

        $paymentData = [
            "payment_method_id" => "pix",
            "transaction_amount" => 100.0,
            "description" => "Teste PIX - Vinyl Shop",
            "payer" => [
                "email" => "teste@example.com",
                "first_name" => "Teste",
                "last_name" => "PIX"
            ],
            "external_reference" => "test_pix_" . time()
        ];

        $payment = $mercadoPagoService->createPayment($paymentData);

        return response()->json([
            'success' => true,
            'payment_id' => $payment->id,
            'status' => $payment->status,
            'qr_code' => $payment->point_of_interaction->transaction_data->qr_code ?? null,
            'qr_code_base64' => $payment->point_of_interaction->transaction_data->qr_code_base64 ?? null
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 400);
    }
});

// Rota de teste para pagamento com cartão
Route::get('/test-card-payment', function () {
    try {
        $mercadoPagoService = app(App\Services\MercadoPagoService::class);

        // Dados de teste para cartão Visa (conforme documentação do MP)
        $paymentData = [
            "payment_method_id" => "visa",
            "token" => "ff8080814c11e237014c1ff593b57b4d", // Token de teste do MP
            "transaction_amount" => 100.0,
            "installments" => 1,
            "description" => "Teste Cartão - Vinyl Shop",
            "payer" => [
                "email" => "teste@example.com",
                "identification" => [
                    "type" => "CPF",
                    "number" => "19119119100"
                ]
            ],
            "external_reference" => "test_card_" . time()
        ];

        $payment = $mercadoPagoService->createPayment($paymentData);

        return response()->json([
            'success' => true,
            'payment_id' => $payment->id,
            'status' => $payment->status,
            'payment_method' => $payment->payment_method_id
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 400);
    }
});
