<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        // Em API sempre retornar JSON, independente de Accept header
        if ($request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'Não autenticado. Por favor, faça login.',
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }
        
        // Se for uma solicitação que espera JSON (AJAX, etc.), também retorne JSON
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Não autenticado. Por favor, faça login.',
            ], 401);
        }

        // Para solicitações web normais, redireciona para a página de login
        return redirect()->guest(route('login'));
    }
}
