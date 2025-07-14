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

        // Obter origem da requisição
        $origin = $request->header('Origin');
        
        // Obter origens permitidas do .env
        $allowedOrigins = explode(',', env('CORS_ALLOWED_ORIGINS', 'https://rdvdiscos.com.br,http://localhost:5173'));
        
        // Verificar se a origem da requisição está na lista de origens permitidas
        if ($origin && in_array($origin, $allowedOrigins)) {
            // Adicionar cabeçalhos CORS com a origem específica
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        } else {
            // Caso contrário, usar o frontend_url como padrão
            $response->headers->set('Access-Control-Allow-Origin', env('FRONTEND_URL', 'https://rdvdiscos.com.br'));
        }
        
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization');
        $response->headers->set('Access-Control-Allow-Credentials', env('CORS_SUPPORTS_CREDENTIALS', 'true'));

        return $response;
    }
}
