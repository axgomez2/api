<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Registrar seu middleware com um alias simples
        $middleware->alias([
            'client.auth' => \App\Http\Middleware\EnsureClientAuthenticated::class,
            //'cors.debug' => \App\Http\Middleware\CorsDebugMiddleware::class,
        ]);

        // 🔥 Configuração do Sanctum para API
        $middleware->api(prepend: [
           // \App\Http\Middleware\CorsDebugMiddleware::class, // Debug middleware antes do CORS
            //\App\Http\Middleware\CorsForceMiddleware::class, // Força adição de headers CORS
            \Illuminate\Http\Middleware\HandleCors::class,
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // 🔥 Configurar stateful domains para Sanctum (se necessário)
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 🔥 Tratar exceções de autenticação para APIs
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Não autenticado',
                    'error' => 'Token inválido ou ausente'
                ], 401);
            }
        });
    })->create();
