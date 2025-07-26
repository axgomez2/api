<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\MelhorEnvioService;
use Illuminate\Support\Facades\Log;
use App\Models\ShippingQuote;
use App\Models\Cart;
use Carbon\Carbon;

class ShippingController extends Controller
{
    protected MelhorEnvioService $me;
    public function __construct(MelhorEnvioService $me) {
        $this->me = $me;
    }

    // 1) redireciona pro consent no Sandbox
    public function redirectToSandbox()
    {
        $clientId = config('services.melhor_envio.client_id');
        $redirectUri = route('shipping.callback');
        $baseUri = config('services.melhor_envio.base_uri');

        // 🔥 DEBUG: Log das configurações
        Log::info('Melhor Envio OAuth Debug', [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'base_uri' => $baseUri,
        ]);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'scope'         => 'shipment'
        ]);

        $authUrl = $baseUri . "/oauth/authorize?{$query}";
        Log::info('Redirect URL: ' . $authUrl);

        return redirect($authUrl);
    }

    // 2) recebe o code e troca por token
    public function handleSandboxCallback(Request $request)
    {
        $data = $request->validate(['code'=>'required|string']);
        $res = Http::baseUrl(config('services.melhor_envio.base_uri'))
            ->post('/oauth/token', [
                'grant_type'    => 'authorization_code',
                'client_id'     => config('services.melhor_envio.client_id'),
                'client_secret' => config('services.melhor_envio.client_secret'),
                'redirect_uri'  => route('shipping.callback'),
                'code'          => $data['code'],
            ]);

        if (!$res->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter token: ' . $res->body()
            ], 400);
        }

        $payload = $res->json();

        // 🔥 CORREÇÃO: Salvar apenas o access_token no cache
        $accessToken = $payload['access_token'] ?? null;

        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token de acesso não encontrado na resposta'
            ], 400);
        }

        // Salvar o token no cache por 1 hora (3600 segundos)
        Cache::put('sandbox_me_token', $accessToken, $payload['expires_in'] ?? 3600);

        // Retornar uma página HTML que fecha o popup
        return response()->view('melhor-envio.success', [
            'message' => 'Conexão com Melhor Envio realizada com sucesso!',
            'expires_in' => $payload['expires_in'] ?? 3600
        ]);
    }

    /** POST /api/shipping/rates */
    public function rates(Request $request)
    {
        $user = $request->user();

        $requestData = $request->validate([
            'from'    => 'required|array',
            'to'      => 'required|array',
            'volumes' => 'required|array|min:1',
        ]);

        try {
            $result = $this->me->calculateShipping($requestData);

            // Salvar cotação no banco de dados e obter o ID
            $quoteId = $this->saveShippingQuote($user, $requestData, $result);

            return response()->json([
                'success' => true,
                'data' => $result,
                'quote_id' => $quoteId
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao calcular frete:', [
                'user_id' => $user->id,
                'request' => $requestData,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao calcular frete: ' . $e->getMessage()
            ], 500);
        }
    }

    /** POST /api/shipping/labels */
    public function createLabel(Request $request)
    {
        $data = $request->validate([
            // validar conforme payload exigido pela API
            'from'       => 'required|array',
            'to'         => 'required|array',
            'volumes'    => 'required|array',
            'service'    => 'required|string',
            'recipient'  => 'required|array',
            // ...
        ]);

        $result = $this->me->createLabel($data);

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    /** GET /api/shipping/debug - Método para debug */
    public function debug()
    {
        $envToken = config('services.melhor_envio.bearer_token');
        $cacheToken = Cache::get('sandbox_me_token');
        $clientId = config('services.melhor_envio.client_id');
        $clientSecret = config('services.melhor_envio.client_secret');
        $baseUri = config('services.melhor_envio.base_uri');

        return response()->json([
            'success' => true,
            'data' => [
                'env_token_exists' => !empty($envToken),
                'env_token_length' => $envToken ? strlen($envToken) : 0,
                'cache_token_exists' => !empty($cacheToken),
                'cache_token_length' => $cacheToken ? strlen($cacheToken) : 0,
                'active_token' => $envToken ? 'ENV' : ($cacheToken ? 'CACHE' : 'NONE'),
                'config_check' => [
                    'client_id' => $clientId ? 'OK' : 'MISSING',
                    'client_secret' => $clientSecret ? 'OK' : 'MISSING',
                    'base_uri' => $baseUri ?: 'MISSING',
                    'bearer_token' => $envToken ? 'OK' : 'MISSING',
                ],
                'message' => $envToken ? 'Token configurado no .env - Pronto para usar!' : 'Configure MELHORENVIO_BEARER_TOKEN no .env'
            ]
        ]);
    }

    /**
     * Salvar cotação de frete no banco de dados
     */
    private function saveShippingQuote($user, $requestData, $result)
    {
        try {
            // Buscar carrinho ativo do usuário
            $cart = Cart::getActiveForUser($user->id);

            // Extrair CEP de destino
            $cepDestino = $requestData['to']['postal_code'] ?? null;

            if (!$cepDestino || !$cart) {
                Log::warning('Não foi possível salvar cotação - dados insuficientes', [
                    'user_id' => $user->id,
                    'cep_destino' => $cepDestino,
                    'cart_id' => $cart?->id
                ]);
                return null;
            }

            // Verificar se já existe uma cotação válida para este carrinho e CEP
            $existingQuote = ShippingQuote::forUser($user->id)
                ->forCart($cart->id)
                ->where('cep_destino', $cepDestino)
                ->valid()
                ->first();

            if ($existingQuote) {
                // Atualizar cotação existente
                $existingQuote->update([
                    'quote_data' => $result,
                    'expires_at' => Carbon::now()->addHours(24)
                ]);

                Log::info('Cotação de frete atualizada', [
                    'quote_id' => $existingQuote->id,
                    'user_id' => $user->id,
                    'cart_id' => $cart->id
                ]);

                return $existingQuote->id;
            } else {
                // Criar nova cotação
                $quote = ShippingQuote::create([
                    'user_id' => $user->id,
                    'cart_id' => $cart->id,
                    'cep_destino' => $cepDestino,
                    'quote_data' => $result,
                    'expires_at' => Carbon::now()->addHours(24)
                ]);

                Log::info('Nova cotação de frete salva', [
                    'quote_id' => $quote->id,
                    'user_id' => $user->id,
                    'cart_id' => $cart->id
                ]);

                return $quote->id;
            }
        } catch (\Exception $e) {
            Log::error('Erro ao salvar cotação de frete:', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Selecionar serviço de frete
     * POST /api/shipping/select-service
     */
    public function selectService(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'quote_id' => 'required|integer|exists:shipping_quotes,id',
            'service_id' => 'required|string',
            'service_data' => 'required|array'
        ]);

        try {
            $quote = ShippingQuote::forUser($user->id)->findOrFail($data['quote_id']);

            if (!$quote->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cotação expirada. Calcule o frete novamente.'
                ], 400);
            }

            $quote->update([
                'selected_service' => $data['service_data']
            ]);

            Log::info('Serviço de frete selecionado', [
                'quote_id' => $quote->id,
                'user_id' => $user->id,
                'service_id' => $data['service_id']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Serviço de frete selecionado com sucesso',
                'data' => $quote
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao selecionar serviço de frete:', [
                'user_id' => $user->id,
                'quote_id' => $data['quote_id'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao selecionar serviço de frete'
            ], 500);
        }
    }

    /**
     * Listar cotações do usuário
     * GET /api/shipping/quotes
     */
    public function getQuotes(Request $request)
    {
        $user = $request->user();

        $quotes = ShippingQuote::forUser($user->id)
            ->with('cart')
            ->valid()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $quotes
        ]);
    }

    /**
     * Obter cotação específica
     * GET /api/shipping/quotes/{id}
     */
    public function getQuote(Request $request, $id)
    {
        $user = $request->user();

        try {
            $quote = ShippingQuote::forUser($user->id)
                ->with('cart')
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $quote
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cotação não encontrada'
            ], 404);
        }
    }

    /**
     * Cálculo básico de frete (SEM AUTENTICAÇÃO)
     * POST /api/shipping/calculate
     */
    public function calculate(Request $request)
    {
        try {
            $data = $request->validate([
                'cep_destino' => 'required|string|size:8',
                'products' => 'required|array',
                'products.*.id' => 'required|integer',
                'products.*.quantity' => 'required|integer|min:1',
                'products.*.weight' => 'nullable|numeric',
                'products.*.dimensions' => 'nullable|array'
            ]);

            // Preparar dados para o Melhor Envio
            $requestData = $this->prepareShippingData($data);
            
            $result = $this->me->calculateShipping($requestData);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao calcular frete público:', [
                'request' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao calcular frete: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter tarifas disponíveis (SEM AUTENTICAÇÃO)
     * POST /api/shipping/rates
     */
    public function rates(Request $request)
    {
        try {
            $data = $request->validate([
                'cep_origem' => 'required|string|size:8',
                'cep_destino' => 'required|string|size:8',
                'volumes' => 'required|array',
                'volumes.*.height' => 'required|numeric|min:1',
                'volumes.*.width' => 'required|numeric|min:1', 
                'volumes.*.length' => 'required|numeric|min:1',
                'volumes.*.weight' => 'required|numeric|min:0.1'
            ]);

            $result = $this->me->calculateShipping($data);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao obter tarifas:', [
                'request' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter tarifas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter serviços disponíveis (SEM AUTENTICAÇÃO)
     * GET /api/shipping/services
     */
    public function getServices()
    {
        try {
            $services = [
                [
                    'id' => 1,
                    'name' => 'PAC',
                    'company' => 'Correios',
                    'description' => 'Entrega econômica'
                ],
                [
                    'id' => 2,
                    'name' => 'SEDEX',
                    'company' => 'Correios', 
                    'description' => 'Entrega expressa'
                ],
                [
                    'id' => 3,
                    'name' => 'Jadlog',
                    'company' => 'Jadlog',
                    'description' => 'Entrega rápida'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $services
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter serviços'
            ], 500);
        }
    }

    /**
     * Validar CEP (SEM AUTENTICAÇÃO)
     * POST /api/shipping/validate-cep
     */
    public function validateCep(Request $request)
    {
        try {
            $data = $request->validate([
                'cep' => 'required|string'
            ]);

            $cep = preg_replace('/\D/', '', $data['cep']);
            
            if (strlen($cep) !== 8) {
                return response()->json([
                    'success' => false,
                    'message' => 'CEP deve ter 8 dígitos'
                ], 400);
            }

            // Consultar ViaCEP para validar
            $response = Http::get("https://viacep.com.br/ws/{$cep}/json/");
            
            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao consultar CEP'
                ], 500);
            }

            $address = $response->json();
            
            if (isset($address['erro'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'CEP não encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'cep' => $cep,
                    'logradouro' => $address['logradouro'] ?? '',
                    'bairro' => $address['bairro'] ?? '',
                    'cidade' => $address['localidade'] ?? '',
                    'uf' => $address['uf'] ?? '',
                    'valid' => true
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao validar CEP:', [
                'request' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao validar CEP'
            ], 500);
        }
    }

    /**
     * Preparar dados para envio ao Melhor Envio
     */
    private function prepareShippingData(array $data)
    {
        $volumes = [];
        $totalWeight = 0;

        foreach ($data['products'] as $product) {
            $weight = $product['weight'] ?? 0.3; // Peso padrão para vinyl
            $dimensions = $product['dimensions'] ?? [
                'height' => 0.5,
                'width' => 31.5,
                'length' => 31.5
            ];

            for ($i = 0; $i < $product['quantity']; $i++) {
                $volumes[] = [
                    'height' => $dimensions['height'],
                    'width' => $dimensions['width'],
                    'length' => $dimensions['length'],
                    'weight' => $weight
                ];
                $totalWeight += $weight;
            }
        }

        return [
            'from' => [
                'postal_code' => config('shipping.origin_cep', '01310-100')
            ],
            'to' => [
                'postal_code' => $data['cep_destino']
            ],
            'volumes' => $volumes
        ];
    }
}
