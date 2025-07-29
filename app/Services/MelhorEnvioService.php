<?php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MelhorEnvioService
{
    protected string $baseUri;
    public function __construct()
    {
        $this->baseUri = rtrim(config('services.melhor_envio.base_uri'), '/');
    }

    protected function getToken(): string
    {
        // Primeiro, tentar pegar do .env (mais simples)
        $envToken = config('services.melhor_envio.bearer_token');
        if ($envToken) {
            return $envToken;
        }

        // Segundo, pegar do cache (OAuth)
        $cacheToken = Cache::get('sandbox_me_token');
        if ($cacheToken) {
            return $cacheToken;
        }

        // Se não tiver nenhum, lançar erro
        throw new \RuntimeException('Token do Melhor Envio não encontrado. Configure MELHORENVIO_BEARER_TOKEN no .env ou faça a conexão OAuth.');
    }

    /** Calcula tarifas de frete */
    // app/Services/MelhorEnvioService.php

    public function calculateShipping(array $payload): array
    {
        $token = $this->getToken();

        // Debug logs
        Log::info('🚚 [MelhorEnvio] Calculando frete', [
            'base_uri' => $this->baseUri,
            'token_preview' => substr($token, 0, 20) . '...',
            'payload' => $payload
        ]);

        $fullUrl = $this->baseUri . '/api/v2/me/shipment/calculate';
        
        Log::info('🔗 [MelhorEnvio] URL completa', [
            'full_url' => $fullUrl
        ]);

        $res = Http::withToken($token)
            ->withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->post($fullUrl, $payload);

        Log::info('✅ [MelhorEnvio] Resposta recebida', [
            'status' => $res->status(),
            'success' => $res->ok(),
            'response_preview' => substr($res->body(), 0, 200) . '...'
        ]);

        if (! $res->ok()) {
            Log::error('❌ [MelhorEnvio] Erro na resposta', [
                'status' => $res->status(),
                'body' => $res->body()
            ]);
            throw new \RuntimeException("Erro Melhor Envio [{$res->status()}]: ".$res->body());
        }
        
        if (! is_array($res->json())) {
            Log::error('❌ [MelhorEnvio] Resposta não é array', [
                'response_type' => gettype($res->json()),
                'response' => $res->json()
            ]);
            throw new \RuntimeException("Resposta inválida do Melhor Envio: " . $res->body());
        }

        return $res->json();
    }


    /** Gera etiqueta de frete */
    public function createLabel(array $payload): array
    {
        $token = $this->getToken();

        $res = Http::baseUrl($this->baseUri)
            ->withToken($token)
            ->withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->post('/api/v2/me/shipment/services', $payload);

        if (! $res->ok() || ! is_array($res->json())) {
            throw new \RuntimeException("Erro Melhor Env. createLabel [{$res->status()}]: ".$res->body());
        }

        return $res->json();
    }

    // Você pode adicionar track(), cancel(), etc conforme doc oficial.
}
