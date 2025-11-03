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

        // Preparar volumes com dados completos do carrinho
        $requestData = $this->prepareShippingDataWithCart($user, $requestData);

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

            // Se for erro de token, retornar dados mock para desenvolvimento
            if (str_contains($e->getMessage(), 'Token do Melhor Envio não encontrado')) {
                Log::info('🔧 Retornando dados mock de frete para desenvolvimento');
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        [
                            'id' => 1,
                            'name' => 'PAC',
                            'company' => [
                                'id' => 1,
                                'name' => 'Correios',
                                'picture' => 'https://www.melhorenvio.com.br/images/shipping-companies/correios.png'
                            ],
                            'price' => '18.90',
                            'custom_price' => '18.90',
                            'discount' => '0.00',
                            'currency' => 'R$',
                            'delivery_time' => 8,
                            'delivery_range' => [
                                'min' => 6,
                                'max' => 10
                            ]
                        ],
                        [
                            'id' => 2,
                            'name' => 'SEDEX',
                            'company' => [
                                'id' => 1,
                                'name' => 'Correios',
                                'picture' => 'https://www.melhorenvio.com.br/images/shipping-companies/correios.png'
                            ],
                            'price' => '28.50',
                            'custom_price' => '28.50',
                            'discount' => '0.00',
                            'currency' => 'R$',
                            'delivery_time' => 3,
                            'delivery_range' => [
                                'min' => 2,
                                'max' => 4
                            ]
                        ],
                        [
                            'id' => 4,
                            'name' => 'Jadlog .COM',
                            'company' => [
                                'id' => 4,
                                'name' => 'Jadlog',
                                'picture' => 'https://www.melhorenvio.com.br/images/shipping-companies/jadlog.png'
                            ],
                            'price' => '22.80',
                            'custom_price' => '22.80',
                            'discount' => '0.00',
                            'currency' => 'R$',
                            'delivery_time' => 5,
                            'delivery_range' => [
                                'min' => 4,
                                'max' => 6
                            ]
                        ],
                        [
                            'id' => 10,
                            'name' => 'JET',
                            'company' => [
                                'id' => 10,
                                'name' => 'JET',
                                'picture' => 'https://www.melhorenvio.com.br/images/shipping-companies/jet.png'
                            ],
                            'price' => '26.90',
                            'custom_price' => '26.90',
                            'discount' => '0.00',
                            'currency' => 'R$',
                            'delivery_time' => 4,
                            'delivery_range' => [
                                'min' => 3,
                                'max' => 5
                            ]
                        ],
                        [
                            'id' => 17,
                            'name' => 'Azul Cargo Express',
                            'company' => [
                                'id' => 17,
                                'name' => 'Azul Cargo',
                                'picture' => 'https://www.melhorenvio.com.br/images/shipping-companies/azul-cargo.png'
                            ],
                            'price' => '32.50',
                            'custom_price' => '32.50',
                            'discount' => '0.00',
                            'currency' => 'R$',
                            'delivery_time' => 2,
                            'delivery_range' => [
                                'min' => 1,
                                'max' => 3
                            ]
                        ]
                    ],
                    'message' => 'Dados mock de frete (configure MELHORENVIO_BEARER_TOKEN para dados reais)',
                    'quote_id' => null
                ]);
            }

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
     * Preparar dados de frete com informações do carrinho
     */
    private function prepareShippingDataWithCart($user, $requestData)
    {
        try {
            // Buscar carrinho ativo do usuário
            $cart = Cart::getActiveForUser($user->id);
            
            if (!$cart || $cart->items->isEmpty()) {
                Log::warning('Carrinho vazio ou não encontrado', ['user_id' => $user->id]);
                return $requestData; // Retorna dados originais
            }

            $totalValue = 0;
            $totalWeight = 0;
            $totalHeight = 0;
            $maxWidth = 31; // Largura padrão de vinil LP (cm)
            $maxLength = 31; // Comprimento padrão de vinil LP (cm)

            // Processar cada item do carrinho
            foreach ($cart->items as $cartItem) {
                $product = $cartItem->product;
                $quantity = $cartItem->quantity;

                // Calcular valor total para seguro
                $vinylSec = $product->productable?->vinylSec ?? $product->productable?->vinyl_sec;
                
                if (!$vinylSec) {
                    Log::warning('Produto sem vinylSec, usando valores padrão', [
                        'product_id' => $product->id,
                        'product_name' => $product->name
                    ]);
                }
                
                $price = $vinylSec->promotional_price ?? $vinylSec->price ?? $product->price ?? 0;
                $totalValue += $price * $quantity;

                // Peso do produto
                $weight = 0.3; // Peso padrão de 300g por vinil
                if ($vinylSec && isset($vinylSec->weight)) {
                    // Weight pode ser um objeto ou um valor numérico
                    if (is_object($vinylSec->weight) && isset($vinylSec->weight->value)) {
                        // Se for objeto Weight com propriedade value
                        $weight = $vinylSec->weight->value;
                        // Converter para kg se estiver em gramas
                        if (isset($vinylSec->weight->unit) && $vinylSec->weight->unit === 'g') {
                            $weight = $weight / 1000;
                        }
                    } elseif (is_numeric($vinylSec->weight)) {
                        // Se for valor numérico direto
                        $weight = floatval($vinylSec->weight);
                    }
                }
                $totalWeight += $weight * $quantity;

                // Dimensões
                // Altura se acumula (vários discos empilhados)
                $height = 0.5; // 5mm por disco (padrão)
                if ($vinylSec) {
                    // Verificar se dimension é um objeto ou valores diretos
                    if (isset($vinylSec->dimension) && is_object($vinylSec->dimension)) {
                        // Se dimension é um objeto
                        $height = isset($vinylSec->dimension->height) ? floatval($vinylSec->dimension->height) / 100 : 0.5;
                        $width = isset($vinylSec->dimension->width) ? floatval($vinylSec->dimension->width) : 31;
                        $length = isset($vinylSec->dimension->length) ? floatval($vinylSec->dimension->length) : 31;
                        
                        $maxWidth = max($maxWidth, $width);
                        $maxLength = max($maxLength, $length);
                    } else {
                        // Valores diretos nas propriedades
                        if (isset($vinylSec->height)) {
                            $height = floatval($vinylSec->height) / 100; // Converter mm para cm
                        }
                        if (isset($vinylSec->width)) {
                            $maxWidth = max($maxWidth, floatval($vinylSec->width));
                        }
                        if (isset($vinylSec->length)) {
                            $maxLength = max($maxLength, floatval($vinylSec->length));
                        }
                    }
                }
                $totalHeight += $height * $quantity;
            }

            // Garantir valores mínimos
            $totalWeight = max(0.1, $totalWeight); // Mínimo 100g
            $totalHeight = max(1, ceil($totalHeight)); // Mínimo 1cm
            $totalValue = max(10, $totalValue); // Mínimo R$ 10 para seguro

            // Atualizar volumes com dados reais
            $requestData['volumes'][0] = [
                'height' => (int) $totalHeight,
                'width' => (int) $maxWidth,
                'length' => (int) $maxLength,
                'weight' => round($totalWeight, 2),
                'insurance_value' => round($totalValue, 2) // VALOR PARA SEGURO
            ];

            Log::info('📦 Volumes preparados com dados do carrinho', [
                'user_id' => $user->id,
                'cart_id' => $cart->id,
                'items_count' => $cart->items->count(),
                'total_value' => $totalValue,
                'total_weight' => $totalWeight,
                'dimensions' => "{$maxWidth}x{$maxLength}x{$totalHeight}cm",
                'volumes' => $requestData['volumes']
            ]);

            return $requestData;

        } catch (\Exception $e) {
            Log::error('Erro ao preparar dados de frete:', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $requestData; // Retorna dados originais em caso de erro
        }
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


}
