<?php

// DIAGNÓSTICO CORS - ROTA ESPECIAL
Route::any('/cors-debug', function() {
    // Adicionar cabeçalhos CORS manualmente
    return response()->json([
        'message' => 'CORS debug successful',
        'request_method' => request()->method(),
        'request_headers' => collect(request()->headers->all())
            ->map(function ($item) {
                return is_array($item) ? implode(', ', $item) : $item;
            })
            ->toArray(),
        'timestamp' => now()->toDateTimeString(),
        'env_settings' => [
            'app_url' => config('app.url'),
            'frontend_url' => config('app.frontend_url'),
            'cors_allowed_origins' => env('CORS_ALLOWED_ORIGINS'),
            'cors_supports_credentials' => env('CORS_SUPPORTS_CREDENTIALS')
        ]
    ])->withHeaders([
        'Access-Control-Allow-Origin' => 'https://rdvdiscos.com.br',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Origin, X-Requested-With, Content-Type, Accept, Authorization',
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Max-Age' => '86400'
    ]);
});

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
use App\Http\Controllers\Api\ArtistController;
use App\Http\Controllers\Api\RecordLabelController;
use Illuminate\Http\Request;
// use App\Http\Controllers\Admin\StatusController;

// 🔥 ENDPOINT DE CONFIGURAÇÃO (SEM AUTENTICAÇÃO)
Route::get('/config', function () {
    return response()->json([
        'api_url' => config('app.url'),
        'frontend_url' => config('app.frontend_url'),
        'app_name' => config('app.name'),
        'cdn_url' => env('CDN_URL', 'https://cdn.rdvdiscos.com.br'),
        'media_url' => env('MEDIA_URL', 'https://media.rdvdiscos.com.br'),
        'image_base_url' => env('IMAGE_BASE_URL', config('app.url')),
        'mercadopago' => [
            'public_key' => config('services.mercadopago.public_key'),
            'sandbox' => config('services.mercadopago.sandbox', false),
        ],
        'google' => [
            'client_id' => config('services.google.client_id'),
        ],
        'app_env' => config('app.env'),
        'timestamp' => now()->toDateTimeString(),
        'server_info' => [
            'remote_addr' => request()->server('REMOTE_ADDR'),
            'http_origin' => request()->server('HTTP_ORIGIN'),
            'http_host' => request()->server('HTTP_HOST'),
        ]
    ]);
});








// 🔥 ROTAS CLIENT - SEM AUTENTICAÇÃO
Route::post('/client/register', [ClientAuthController::class, 'register'])->name('client.register');
Route::post('/client/login', [ClientAuthController::class, 'login'])->middleware(['throttle:5,1'])->name('client.login');

// Google OAuth routes
Route::get('/client/auth/google/redirect', [ClientAuthController::class, 'redirectToGoogle'])->name('client.google.redirect');
Route::get('/client/auth/google/callback', [ClientAuthController::class, 'handleGoogleCallback'])->name('client.google.callback');

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
    Route::get('/me', [ClientAuthController::class, 'me']); // Rota simplificada para compatibilidade
    Route::put('/client/profile', [ClientAuthController::class, 'updateProfile']);
    Route::put('/client/password', [ClientAuthController::class, 'changePassword']);
    Route::post('/client/resend-verification', [ClientAuthController::class, 'resendVerificationEmail']);
    Route::post('/client/logout', [ClientAuthController::class, 'logout']);

    // Carrinho
    Route::prefix('client/cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/', [CartController::class, 'store']);
        Route::delete('/{productId}', [CartController::class, 'destroy']);
        Route::delete('/', [CartController::class, 'clear']);
        Route::get('/{productId}/check', [CartController::class, 'checkItem']); // Nova rota para verificar item
    });

    // Wishlist
    Route::prefix('client/wishlist')->group(function () {
        Route::get('/', [WishlistController::class, 'index']);
        Route::post('/', [WishlistController::class, 'store']);
        Route::post('/toggle', [WishlistController::class, 'toggle']); // 🔥 NOVA ROTA TOGGLE
        Route::get('/{productId}/check', [WishlistController::class, 'check']); // 🔥 NOVA ROTA
        Route::delete('/{productId}', [WishlistController::class, 'destroy']);
        Route::delete('/', [WishlistController::class, 'clear']);
    });

    // Wantlist
    Route::prefix('client/wantlist')->group(function () {
        Route::get('/', [WantlistController::class, 'index']);
        Route::post('/', [WantlistController::class, 'store']);
        Route::post('/toggle', [WantlistController::class, 'toggle']); // 🔥 NOVA ROTA TOGGLE
        Route::delete('/{productId}', [WantlistController::class, 'destroy']);
        Route::delete('/', [WantlistController::class, 'clear']);
    });

    // Endereços
    Route::prefix('client/addresses')->group(function () {
        Route::get('/', [AddressController::class, 'index']);
        Route::post('/', [AddressController::class, 'store']);
        Route::put('/{id}', [AddressController::class, 'update']);
        Route::delete('/{id}', [AddressController::class, 'destroy']);
        Route::put('/{id}/default', [AddressController::class, 'setDefault']);
    });

    // Rotas de shipping que precisam de autenticação
    Route::prefix('client/shipping')->group(function () {
        Route::post('rates', [ShippingController::class, 'rates']);
        Route::post('labels', [ShippingController::class, 'createLabel']);
        Route::post('select-service', [ShippingController::class, 'selectService']);
        Route::get('quotes', [ShippingController::class, 'getQuotes']);
        Route::get('quotes/{id}', [ShippingController::class, 'getQuote']);
    });

    // Pedidos
    Route::prefix('client/orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::put('/{id}/cancel', [OrderController::class, 'cancel']);
        Route::get('/{id}/tracking', [OrderController::class, 'tracking']);
        Route::put('/{id}/retry-payment', [OrderController::class, 'retryPayment']);
    });

    // 🔥 ROTAS DE PAGAMENTO - MERCADO PAGO
    Route::prefix('client/payment')->group(function () {
        Route::post('create-preference', [PaymentController::class, 'createPreference']);
        Route::post('process', [PaymentController::class, 'processPayment']);
        Route::get('status/{paymentId}', [PaymentController::class, 'getPaymentStatus']);
        Route::get('/{paymentId}/details', [PaymentController::class, 'getPaymentDetails']);
    });

});

// 🔥 WEBHOOK MERCADO PAGO (SEM AUTENTICAÇÃO)
Route::post('/webhooks/mercadopago', [PaymentController::class, 'webhook']);

// 🔥 ROTAS DE REDIRECIONAMENTO MERCADO PAGO (SEM AUTENTICAÇÃO)
Route::get('/success', function (Request $request) {
    $redirectUrl = $request->query('redirect', config('app.frontend_url') . '/checkout/success');

    // Adicionar parâmetros do MP à URL de redirecionamento
    $mpParams = $request->only(['collection_id', 'collection_status', 'payment_id', 'status', 'external_reference', 'payment_type', 'merchant_order_id', 'preference_id']);

    if (!empty($mpParams)) {
        $separator = strpos($redirectUrl, '?') !== false ? '&' : '?';
        $redirectUrl .= $separator . http_build_query($mpParams);
    }

    return redirect($redirectUrl);
});

Route::get('/failure', function (Request $request) {
    $redirectUrl = $request->query('redirect', config('app.frontend_url') . '/checkout/failure');

    // Adicionar parâmetros do MP à URL de redirecionamento
    $mpParams = $request->only(['collection_id', 'collection_status', 'payment_id', 'status', 'external_reference', 'payment_type', 'merchant_order_id', 'preference_id']);

    if (!empty($mpParams)) {
        $separator = strpos($redirectUrl, '?') !== false ? '&' : '?';
        $redirectUrl .= $separator . http_build_query($mpParams);
    }

    return redirect($redirectUrl);
});

Route::get('/pending', function (Request $request) {
    $redirectUrl = $request->query('redirect', config('app.frontend_url') . '/checkout/pending');

    // Adicionar parâmetros do MP à URL de redirecionamento
    $mpParams = $request->only(['collection_id', 'collection_status', 'payment_id', 'status', 'external_reference', 'payment_type', 'merchant_order_id', 'preference_id']);

    if (!empty($mpParams)) {
        $separator = strpos($redirectUrl, '?') !== false ? '&' : '?';
        $redirectUrl .= $separator . http_build_query($mpParams);
    }

    return redirect($redirectUrl);
});

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

// 🔥 ROTAS DE SHIPPING PÚBLICAS (SEM AUTENTICAÇÃO)
// Estas rotas podem ser acessadas sem login para cálculo de frete
Route::prefix('shipping')->group(function () {
    Route::post('/calculate', [ShippingController::class, 'calculate']); // Cálculo básico
    Route::post('/rates', [ShippingController::class, 'rates']); // Tarifas disponíveis
    Route::get('/services', [ShippingController::class, 'getServices']); // Serviços disponíveis
    Route::post('/validate-cep', [ShippingController::class, 'validateCep']); // Validar CEP
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
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories-with-products', [CategoryController::class, 'fetchWithProducts']);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);
Route::get('/categories/{slug}/products', [CategoryController::class, 'productsByCategory']);

// Rotas para Artistas
Route::prefix('artists')->group(function () {
    Route::get('/', [ArtistController::class, 'index']);
    Route::get('/{slug}', [ArtistController::class, 'show']);
    Route::get('/{slug}/products', [ArtistController::class, 'productsByArtist']);
});

// Rotas para Gravadoras (Record Labels)
Route::prefix('labels')->group(function () {
    Route::get('/', [RecordLabelController::class, 'index']);
    Route::get('/{slug}', [RecordLabelController::class, 'show']);
    Route::get('/{slug}/products', [RecordLabelController::class, 'productsByLabel']);
});

// Routes de debug (apenas em ambiente de desenvolvimento)
if (config('app.debug')) {
    Route::get('debug/app', function () {
        return response()->json([
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'timestamp' => now()->toDateTimeString()
        ]);
    });

    Route::get('debug/mercadopago', function () {
        return response()->json([
            'access_token_exists' => !empty(config('services.mercadopago.access_token')),
            'access_token_length' => strlen(config('services.mercadopago.access_token') ?? ''),
            'access_token_prefix' => substr(config('services.mercadopago.access_token') ?? '', 0, 15),
            'public_key_exists' => !empty(config('services.mercadopago.public_key')),
            'public_key_length' => strlen(config('services.mercadopago.public_key') ?? ''),
            'public_key_prefix' => substr(config('services.mercadopago.public_key') ?? '', 0, 15),
            'webhook_secret_exists' => !empty(config('services.mercadopago.webhook_secret')),
            'sandbox_mode' => config('services.mercadopago.sandbox', false),
            'app_url' => config('app.url'),
            'webhook_url' => config('app.url') . '/api/webhooks/mercadopago',
            'timestamp' => now()->toDateTimeString()
        ]);
    });

    Route::post('debug/mp-test-preference', function (Request $request) {
        try {
            $mercadoPagoService = app(\App\Services\MercadoPagoService::class);

            $testData = [
                'items' => [
                    [
                        'id' => 'test-item',
                        'title' => 'Item de Teste',
                        'quantity' => 1,
                        'unit_price' => 10.00,
                        'currency_id' => 'BRL'
                    ]
                ],
                'payer' => [
                    'email' => 'test@test.com',
                    'name' => 'Test User'
                ],
                'back_urls' => [
                    'success' => config('app.url') . '/success',
                    'failure' => config('app.url') . '/failure',
                    'pending' => config('app.url') . '/pending'
                ],
                'auto_return' => 'approved',
                'external_reference' => 'test_' . time(),
                'notification_url' => config('app.url') . '/api/webhooks/mercadopago'
            ];

            $preference = $mercadoPagoService->createPreference($testData);

            return response()->json([
                'success' => true,
                'message' => 'Preferência de teste criada com sucesso',
                'preference_id' => $preference->id,
                'init_point' => $preference->init_point,
                'sandbox_init_point' => $preference->sandbox_init_point
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    });
}

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

// Route::middleware(['auth:sanctum', 'verified'])->group(function () {
//     // Rotas para análise de mercado
//     Route::get('/market-analysis-chart-data', [MarketAnalysisController::class, 'getChartData']);
//     Route::resource('market-analysis', MarketAnalysisController::class)->except(['create', 'edit']);
// });

// Route::get('/status', [StatusController::class, 'index']);
