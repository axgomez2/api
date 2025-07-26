<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Str;

class SeoController extends Controller
{
    /**
     * Gerar página de produto com Open Graph meta tags para SEO/WhatsApp
     */
    public function productPage($slug)
    {
        try {
            // Buscar produto pelo slug ou ID
            $product = $this->findProductBySlug($slug);
            
            if (!$product) {
                return $this->renderNotFound();
            }

            // Preparar dados para Open Graph
            $ogData = $this->prepareOpenGraphData($product);
            
            // Detectar se é bot do WhatsApp/Facebook
            if ($this->isSocialBot()) {
                // Servir HTML com meta tags para bots
                return $this->renderProductHtml($ogData);
            } else {
                // Redirecionar usuários normais para o frontend
                return redirect($ogData['url']);
            }
            
        } catch (\Exception $e) {
            \Log::error('Erro ao gerar página SEO do produto:', [
                'slug' => $slug,
                'error' => $e->getMessage()
            ]);
            
            return $this->renderNotFound();
        }
    }
    
    /**
     * Detectar se é bot de rede social
     */
    private function isSocialBot()
    {
        $userAgent = request()->header('User-Agent', '');
        
        $bots = [
            'WhatsApp',
            'facebookexternalhit',
            'Facebot',
            'Twitterbot',
            'LinkedInBot',
            'TelegramBot',
            'SkypeUriPreview',
            'SlackBot',
            'DiscordBot'
        ];
        
        foreach ($bots as $bot) {
            if (stripos($userAgent, $bot) !== false) {
                \Log::info('Bot detectado:', [
                    'bot' => $bot,
                    'user_agent' => $userAgent
                ]);
                return true;
            }
        }
        
        return false;
    }

    /**
     * Buscar produto pelo slug
     */
    private function findProductBySlug($slug)
    {
        // Tentar buscar por slug primeiro
        $product = Product::where('slug', $slug)->first();
        
        if (!$product) {
            // Se não encontrar por slug, tentar por ID (para compatibilidade)
            if (is_numeric($slug)) {
                $product = Product::find($slug);
            }
        }
        
        if ($product) {
            // Carregar relacionamentos necessários
            $product->load(['productable', 'productable.artists', 'productable.vinylSec']);
        }
        
        return $product;
    }

    /**
     * Preparar dados do Open Graph
     */
    private function prepareOpenGraphData($product)
    {
        $frontendUrl = config('app.frontend_url', 'https://rdvdiscos.com.br');
        $cdnUrl = config('app.cdn_url', 'https://cdn.rdvdiscos.com.br');
        
        // Título
        $title = $product->name;
        
        // Descrição
        $description = $this->generateDescription($product);
        
        // Imagem
        $image = $this->getProductImage($product, $cdnUrl);
        
        // URL da página
        $url = $this->generateProductUrl($product, $frontendUrl);
        
        // Preço
        $price = $this->getProductPrice($product);
        
        return [
            'title' => $title,
            'description' => $description,
            'image' => $image,
            'url' => $url,
            'price' => $price,
            'product' => $product,
            'site_name' => 'RDV Discos',
            'type' => 'product'
        ];
    }

    /**
     * Gerar descrição do produto
     */
    private function generateDescription($product)
    {
        $description = $product->name;
        
        // Adicionar artistas se disponível
        $artists = $this->getArtistsString($product);
        if ($artists) {
            $description .= " - " . $artists;
        }
        
        // Adicionar preço
        $price = $this->getProductPrice($product);
        if ($price) {
            $description .= " - R$ " . number_format($price, 2, ',', '.');
        }
        
        // Adicionar ano se disponível
        if ($product->productable && $product->productable->release_year) {
            $description .= " (" . $product->productable->release_year . ")";
        }
        
        $description .= " | RDV Discos - Sua loja de vinis e música";
        
        return $description;
    }
    
    /**
     * Obter string dos artistas
     */
    private function getArtistsString($product)
    {
        if (!$product->productable || !$product->productable->artists) {
            return null;
        }
        
        $artistNames = $product->productable->artists->pluck('name')->toArray();
        
        if (empty($artistNames)) {
            return null;
        }
        
        // Se há apenas um artista
        if (count($artistNames) === 1) {
            return $artistNames[0];
        }
        
        // Se há vários artistas, juntar com vírgula e "e"
        if (count($artistNames) === 2) {
            return implode(' e ', $artistNames);
        }
        
        // Mais de 2 artistas: "Artista1, Artista2 e Artista3"
        $lastArtist = array_pop($artistNames);
        return implode(', ', $artistNames) . ' e ' . $lastArtist;
    }

    /**
     * Obter imagem do produto
     */
    private function getProductImage($product, $cdnUrl)
    {
        if ($product->productable && $product->productable->cover_image) {
            $imagePath = $product->productable->cover_image;
            
            // Se já é uma URL completa, retornar como está
            if (Str::startsWith($imagePath, ['http://', 'https://'])) {
                return $imagePath;
            }
            
            // Construir URL da CDN
            return $cdnUrl . '/' . ltrim($imagePath, '/');
        }
        
        // Imagem padrão
        return $cdnUrl . '/images/placeholder-vinyl.jpg';
    }

    /**
     * Gerar URL do produto no frontend
     */
    private function generateProductUrl($product, $frontendUrl)
    {
        // Usar slug se disponível, senão usar ID
        $identifier = $product->slug ?: $product->id;
        
        // Determinar tipo de produto baseado no productable_type
        $type = 'product';
        if ($product->productable_type === 'App\\Models\\VinylMaster') {
            $type = 'vinyl';
        }
        
        return $frontendUrl . '/' . $type . '/' . $identifier;
    }

    /**
     * Obter preço do produto
     */
    private function getProductPrice($product)
    {
        // Buscar preço no vinylSec primeiro (como no frontend)
        $vinylSec = $product->productable->vinylSec ?? null;
        
        // Verificar preço promocional primeiro
        if ($vinylSec && $vinylSec->promotional_price && $vinylSec->promotional_price > 0) {
            return $vinylSec->promotional_price;
        }
        
        // Preço regular do vinylSec
        if ($vinylSec && $vinylSec->price) {
            return $vinylSec->price;
        }
        
        // Fallback para preço do produto (se existir)
        if ($product->promotional_price && $product->promotional_price > 0) {
            return $product->promotional_price;
        }
        
        return $product->price ?: 0;
    }

    /**
     * Renderizar HTML com Open Graph meta tags
     */
    private function renderProductHtml($ogData)
    {
        $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Meta Tags Básicas -->
    <title>' . htmlspecialchars($ogData['title']) . ' | RDV Discos</title>
    <meta name="description" content="' . htmlspecialchars($ogData['description']) . '">
    
    <!-- Open Graph Meta Tags (Facebook, WhatsApp, etc.) -->
    <meta property="og:title" content="' . htmlspecialchars($ogData['title']) . '">
    <meta property="og:description" content="' . htmlspecialchars($ogData['description']) . '">
    <meta property="og:image" content="' . htmlspecialchars($ogData['image']) . '">
    <meta property="og:url" content="' . htmlspecialchars($ogData['url']) . '">
    <meta property="og:type" content="' . htmlspecialchars($ogData['type']) . '">
    <meta property="og:site_name" content="' . htmlspecialchars($ogData['site_name']) . '">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="' . htmlspecialchars($ogData['title']) . '">
    <meta name="twitter:description" content="' . htmlspecialchars($ogData['description']) . '">
    <meta name="twitter:image" content="' . htmlspecialchars($ogData['image']) . '">
    
    <!-- Meta Tags de Produto -->
    <meta property="product:price:amount" content="' . $ogData['price'] . '">
    <meta property="product:price:currency" content="BRL">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="https://rdvdiscos.com.br/favicon.ico">
    
    <!-- Redirecionamento manual para o frontend -->
    <script>
        // Função para redirecionar manualmente
        function goToProduct() {
            window.location.href = "' . $ogData['url'] . '";
        }
    </script>
    
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #1a1a1a;
            color: #fff;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .seo-info {
            background: #2a2a2a;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        .product-preview {
            background: #333;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        .product-image {
            max-width: 300px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .actions {
            margin: 30px 0;
        }
        .btn-primary, .btn-secondary {
            display: inline-block;
            padding: 12px 24px;
            margin: 10px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }
        .btn-primary:hover {
            background: #45a049;
        }
        .btn-secondary {
            background: #2196F3;
            color: white;
        }
        .btn-secondary:hover {
            background: #1976D2;
        }
        .meta-info {
            background: #2a2a2a;
            padding: 20px;
            border-radius: 10px;
            text-align: left;
        }
        .meta-info ul {
            list-style: none;
            padding: 0;
        }
        .meta-info li {
            margin: 10px 0;
            padding: 10px;
            background: #333;
            border-radius: 5px;
        }
        .meta-info a {
            color: #4CAF50;
            text-decoration: none;
        }
        .meta-info a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="seo-info">
            <h2>🔗 Página SEO - Open Graph</h2>
            <p>Esta página contém meta tags Open Graph para compartilhamento no WhatsApp/Facebook.</p>
        </div>
        
        <div class="product-preview">
            <h1>' . htmlspecialchars($ogData['title']) . '</h1>
            <img src="' . htmlspecialchars($ogData['image']) . '" alt="' . htmlspecialchars($ogData['title']) . '" class="product-image">
            <p>' . htmlspecialchars($ogData['description']) . '</p>
            
            <div class="actions">
                <button onclick="goToProduct()" class="btn-primary">
                    🎵 Ver Produto na Loja
                </button>
                <a href="' . $ogData['url'] . '" class="btn-secondary">
                    🔗 Link Direto
                </a>
            </div>
        </div>
        
        <div class="meta-info">
            <h3>📝 Meta Tags Open Graph:</h3>
            <ul>
                <li><strong>Título:</strong> ' . htmlspecialchars($ogData['title']) . '</li>
                <li><strong>Descrição:</strong> ' . htmlspecialchars($ogData['description']) . '</li>
                <li><strong>Imagem:</strong> <a href="' . htmlspecialchars($ogData['image']) . '" target="_blank">Ver imagem</a></li>
                <li><strong>URL:</strong> <a href="' . $ogData['url'] . '" target="_blank">' . htmlspecialchars($ogData['url']) . '</a></li>
            </ul>
        </div>
    </div>
</body>
</html>';

        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600'); // Cache por 1 hora
    }

    /**
     * Renderizar página 404
     */
    private function renderNotFound()
    {
        $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produto não encontrado | RDV Discos</title>
    <meta name="description" content="Produto não encontrado na RDV Discos">
    
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #1a1a1a;
            color: #fff;
            text-align: center;
            padding: 50px 20px;
        }
    </style>
</head>
<body>
    <h1>🎵 Produto não encontrado</h1>
    <p>O produto que você está procurando não foi encontrado.</p>
    <p><a href="https://rdvdiscos.com.br" style="color: #4CAF50;">Voltar para a loja</a></p>
</body>
</html>';

        return response($html, 404)
            ->header('Content-Type', 'text/html; charset=utf-8');
    }
}
