<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsForceMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Adicionar cabeçalhos CORS manualmente a cada resposta
        $response->headers->set('Access-Control-Allow-Origin', 'https://rdvdiscos.com.br');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        return $response;
    }
}
