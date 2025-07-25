<?php

use App\Http\Controllers\Api\V2\ProductController;
use App\Http\Controllers\Api\ClientAuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ArtistController;
use App\Http\Controllers\Api\RecordLabelController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V2 Routes - Otimizada e Padronizada
|--------------------------------------------------------------------------
*/

// 🔧 Configuração da API
Route::get('/config', function () {
    return response()->json([
        'success' => true,
        'data' => [
            'api_version' => '2.0',
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
        ],
        'message' => 'Configuração da API carregada com sucesso',
        'meta' => [
            'timestamp' => now()->toDateTimeString(),
            'server_info' => [
                'remote_addr' => request()->server('REMOTE_ADDR'),
                'http_origin' => request()->server('HTTP_ORIGIN'),
                'http_host' => request()->server('HTTP_HOST'),
            ]
        ]
    ]);
});

// 🔧 Health Check
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'data' => [
            'status' => 'healthy',
            'version' => '2.0',
            'timestamp' => now()->toDateTimeString(),
        ],
        'message' => 'API funcionando corretamente'
    ]);
});

// 🔐 Autenticação (Rotas Públicas)
Route::prefix('auth')->group(function () {
    Route::post('/register', [ClientAuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [ClientAuthController::class, 'login'])
        ->middleware(['throttle:5,1'])
        ->name('auth.login');
    
    // OAuth Google
    Route::get('/google/redirect', [ClientAuthController::class, 'redirectToGoogle'])->name('auth.google.redirect');
    Route::get('/google/callback', [ClientAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
    
    // Verificação de email
    Route::get('/email/verify/{id}/{hash}', [ClientAuthController::class, 'verifyEmail'])
        ->middleware(['signed'])
        ->name('auth.verification.verify');
});

// 🛍️ Loja (Rotas Públicas)
Route::prefix('shop')->group(function () {
    // Produtos
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('products.index');
        Route::get('/search', [ProductController::class, 'search'])->name('products.search');
        Route::get('/vinyl', [ProductController::class, 'vinylProducts'])->name('products.vinyl');
        Route::get('/vinyl/latest/{limit?}', [ProductController::class, 'latestVinyls'])->name('products.vinyl.latest');
        Route::get('/{slug}', [ProductController::class, 'show'])->name('products.show');
    });
    
    // Categorias
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/with-products', [CategoryController::class, 'fetchWithProducts'])->name('categories.with-products');
        Route::get('/{slug}', [CategoryController::class, 'show'])->name('categories.show');
        Route::get('/{slug}/products', [CategoryController::class, 'productsByCategory'])->name('categories.products');
    });
    
    // Artistas
    Route::prefix('artists')->group(function () {
        Route::get('/', [ArtistController::class, 'index'])->name('artists.index');
        Route::get('/{slug}', [ArtistController::class, 'show'])->name('artists.show');
        Route::get('/{slug}/products', [ArtistController::class, 'productsByArtist'])->name('artists.products');
    });
    
    // Gravadoras
    Route::prefix('labels')->group(function () {
        Route::get('/', [RecordLabelController::class, 'index'])->name('labels.index');
        Route::get('/{slug}', [RecordLabelController::class, 'show'])->name('labels.show');
        Route::get('/{slug}/products', [RecordLabelController::class, 'productsByLabel'])->name('labels.products');
    });
});

// 🔐 Rotas Protegidas (Requer Autenticação)
Route::middleware('client.auth')->group(function () {
    
    // 👤 Perfil do Usuário
    Route::prefix('user')->group(function () {
        Route::get('/profile', [ClientAuthController::class, 'me'])->name('user.profile');
        Route::put('/profile', [ClientAuthController::class, 'updateProfile'])->name('user.profile.update');
        Route::put('/password', [ClientAuthController::class, 'changePassword'])->name('user.password.change');
        Route::post('/resend-verification', [ClientAuthController::class, 'resendVerificationEmail'])->name('user.resend-verification');
        Route::post('/logout', [ClientAuthController::class, 'logout'])->name('user.logout');
    });
    
    // 🛒 Carrinho
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index'])->name('cart.index');
        Route::post('/add', [CartController::class, 'store'])->name('cart.add');
        Route::delete('/remove/{productId}', [CartController::class, 'destroy'])->name('cart.remove');
        Route::delete('/clear', [CartController::class, 'clear'])->name('cart.clear');
        Route::get('/check/{productId}', [CartController::class, 'checkItem'])->name('cart.check');
    });
    
    // ❤️ Lista de Desejos
    Route::prefix('wishlist')->group(function () {
        Route::get('/', [WishlistController::class, 'index'])->name('wishlist.index');
        Route::post('/add', [WishlistController::class, 'store'])->name('wishlist.add');
        Route::get('/check/{productId}', [WishlistController::class, 'check'])->name('wishlist.check');
        Route::delete('/remove/{productId}', [WishlistController::class, 'destroy'])->name('wishlist.remove');
        Route::delete('/clear', [WishlistController::class, 'clear'])->name('wishlist.clear');
    });
    
    // 📍 Endereços
    Route::prefix('addresses')->group(function () {
        Route::get('/', [AddressController::class, 'index'])->name('addresses.index');
        Route::post('/', [AddressController::class, 'store'])->name('addresses.store');
        Route::put('/{id}', [AddressController::class, 'update'])->name('addresses.update');
        Route::delete('/{id}', [AddressController::class, 'destroy'])->name('addresses.destroy');
        Route::put('/{id}/default', [AddressController::class, 'setDefault'])->name('addresses.set-default');
    });
    
    // 🚚 Frete
    Route::prefix('shipping')->group(function () {
        Route::post('/calculate', [ShippingController::class, 'rates'])->name('shipping.calculate');
        Route::post('/select-service', [ShippingController::class, 'selectService'])->name('shipping.select');
        Route::get('/quotes', [ShippingController::class, 'getQuotes'])->name('shipping.quotes');
        Route::get('/quotes/{id}', [ShippingController::class, 'getQuote'])->name('shipping.quote');
    });
    
    // 📦 Pedidos
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('orders.index');
        Route::post('/', [OrderController::class, 'store'])->name('orders.store');
        Route::get('/{id}', [OrderController::class, 'show'])->name('orders.show');
        Route::put('/{id}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::get('/{id}/tracking', [OrderController::class, 'tracking'])->name('orders.tracking');
        Route::put('/{id}/retry-payment', [OrderController::class, 'retryPayment'])->name('orders.retry-payment');
    });
    
    // 💳 Pagamentos
    Route::prefix('payments')->group(function () {
        Route::post('/create-preference', [PaymentController::class, 'createPreference'])->name('payments.create-preference');
        Route::post('/process', [PaymentController::class, 'processPayment'])->name('payments.process');
        Route::get('/status/{paymentId}', [PaymentController::class, 'getPaymentStatus'])->name('payments.status');
        Route::get('/{paymentId}/details', [PaymentController::class, 'getPaymentDetails'])->name('payments.details');
    });
});

// 🔗 Webhooks (Rotas Públicas)
Route::prefix('webhooks')->group(function () {
    Route::post('/mercadopago', [PaymentController::class, 'webhook'])->name('webhooks.mercadopago');
});

// 🔄 Redirecionamentos MercadoPago (Rotas Públicas)
Route::prefix('payment')->group(function () {
    Route::get('/success', function (Illuminate\Http\Request $request) {
        $redirectUrl = $request->query('redirect', config('app.frontend_url') . '/checkout/success');
        $mpParams = $request->only(['collection_id', 'collection_status', 'payment_id', 'status', 'external_reference', 'payment_type', 'merchant_order_id', 'preference_id']);
        
        if (!empty($mpParams)) {
            $separator = strpos($redirectUrl, '?') !== false ? '&' : '?';
            $redirectUrl .= $separator . http_build_query($mpParams);
        }
        
        return redirect($redirectUrl);
    })->name('payment.success');
    
    Route::get('/failure', function (Illuminate\Http\Request $request) {
        $redirectUrl = $request->query('redirect', config('app.frontend_url') . '/checkout/failure');
        $mpParams = $request->only(['collection_id', 'collection_status', 'payment_id', 'status', 'external_reference', 'payment_type', 'merchant_order_id', 'preference_id']);
        
        if (!empty($mpParams)) {
            $separator = strpos($redirectUrl, '?') !== false ? '&' : '?';
            $redirectUrl .= $separator . http_build_query($mpParams);
        }
        
        return redirect($redirectUrl);
    })->name('payment.failure');
    
    Route::get('/pending', function (Illuminate\Http\Request $request) {
        $redirectUrl = $request->query('redirect', config('app.frontend_url') . '/checkout/pending');
        $mpParams = $request->only(['collection_id', 'collection_status', 'payment_id', 'status', 'external_reference', 'payment_type', 'merchant_order_id', 'preference_id']);
        
        if (!empty($mpParams)) {
            $separator = strpos($redirectUrl, '?') !== false ? '&' : '?';
            $redirectUrl .= $separator . http_build_query($mpParams);
        }
        
        return redirect($redirectUrl);
    })->name('payment.pending');
});

// 🐛 Debug (apenas em desenvolvimento)
if (config('app.debug')) {
    Route::prefix('debug')->group(function () {
        Route::get('/routes', function () {
            $routes = collect(Route::getRoutes())->map(function ($route) {
                return [
                    'method' => implode('|', $route->methods()),
                    'uri' => $route->uri(),
                    'name' => $route->getName(),
                    'action' => ltrim($route->getActionName(), '\\'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'total_routes' => $routes->count(),
                    'api_v2_routes' => $routes->filter(function($route) {
                        return str_starts_with($route['uri'], 'api/v2/');
                    })->values()
                ],
                'message' => 'Rotas da API V2 carregadas'
            ]);
        })->name('debug.routes');
    });
}