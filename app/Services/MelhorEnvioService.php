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
        
        // Fallback se a configuração estiver vazia
        if (empty($this->baseUri)) {
            $this->baseUri = 'https://melhorenvio.com.br';
            Log::warning('⚠️ [MelhorEnvio] Base URI vazia, usando fallback', [
                'fallback_uri' => $this->baseUri
            ]);
        }
        
        // Log removido para reduzir ruído
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

        $fullUrl = $this->baseUri . '/api/v2/me/shipment/calculate';
        
        Log::info('🚚 [MelhorEnvio] Calculando frete', [
            'url' => $fullUrl,
            'from' => $payload['from']['postal_code'] ?? 'N/A',
            'to' => $payload['to']['postal_code'] ?? 'N/A'
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

        $allOptions = $res->json();
        
        // Filtrar apenas as transportadoras desejadas
        $filteredOptions = $this->filterShippingOptions($allOptions);
        
        Log::info('🔍 [MelhorEnvio] Opções filtradas', [
            'total_options' => count($allOptions),
            'filtered_options' => count($filteredOptions),
            'companies' => array_column($filteredOptions, 'name')
        ]);

        return $filteredOptions;
    }

    /**
     * Filtrar opções de frete para exibir apenas transportadoras selecionadas
     * 
     * Transportadoras permitidas:
     * - PAC (Correios) - ID 1
     * - SEDEX (Correios) - ID 2
     * - Jadlog .COM - ID 4
     * - Azul Cargo Express - ID 17
     * - JET - ID 10
     */
    private function filterShippingOptions(array $options): array
    {
        // IDs das transportadoras e serviços permitidos
        $allowedServices = [
            1 => ['PAC', 'Correios'],              // PAC
            2 => ['SEDEX', 'Correios'],            // SEDEX
            4 => ['Jadlog', '.COM'],               // Jadlog .COM
            10 => ['JET', 'JET'],                  // JET
            17 => ['Azul', 'Cargo', 'Express'],    // Azul Cargo Express
        ];

        $filtered = array_filter($options, function($option) use ($allowedServices) {
            $serviceId = $option['id'] ?? null;
            $serviceName = strtoupper($option['name'] ?? '');
            $companyName = strtoupper($option['company']['name'] ?? '');
            
            // Verificar por ID do serviço
            if (array_key_exists($serviceId, $allowedServices)) {
                return true;
            }
            
            // Verificar por nome do serviço
            foreach ($allowedServices as $keywords) {
                $matchesService = false;
                foreach ($keywords as $keyword) {
                    if (stripos($serviceName, $keyword) !== false || 
                        stripos($companyName, $keyword) !== false) {
                        $matchesService = true;
                        break;
                    }
                }
                if ($matchesService) {
                    return true;
                }
            }
            
            return false;
        });

        // Reindexar array
        return array_values($filtered);
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
