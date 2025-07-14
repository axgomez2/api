<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CorsDebugMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Log CORS related information
        Log::info('CORS Debug Request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'origin' => $request->header('Origin'),
            'referer' => $request->header('Referer'),
            'user-agent' => $request->header('User-Agent'),
            'host' => $request->header('Host'),
        ]);
        
        // Process the request
        $response = $next($request);
        
        // Log the response headers related to CORS
        Log::info('CORS Debug Response', [
            'access-control-allow-origin' => $response->headers->get('Access-Control-Allow-Origin'),
            'access-control-allow-methods' => $response->headers->get('Access-Control-Allow-Methods'),
            'access-control-allow-headers' => $response->headers->get('Access-Control-Allow-Headers'),
            'access-control-allow-credentials' => $response->headers->get('Access-Control-Allow-Credentials'),
            'content-type' => $response->headers->get('Content-Type'),
            'status' => $response->getStatusCode(),
        ]);
        
        return $response;
    }
}
