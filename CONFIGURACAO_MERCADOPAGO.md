# 🛒 Configuração do Mercado Pago

## 📋 Problemas Identificados nos Logs

### 🔴 **Erro 401 - Token Inválido**
```
Erro na API do Mercado Pago ao criar preferência: {"status_code":401}
```

### 🔴 **Erro de Propriedade Nula**
```
Attempt to read property "email" on null
```

## 🛠️ **Soluções Implementadas**

### **1. Correções no PaymentController**
- ✅ Adicionada verificação de usuário nulo
- ✅ Validação de email com fallback
- ✅ Melhor tratamento de erros

### **2. Configuração do .env**

Adicione as seguintes variáveis ao seu arquivo `.env`:

```env
# Mercado Pago - Configurações de Sandbox
MERCADOPAGO_ACCESS_TOKEN=TEST-your-access-token-here
MERCADOPAGO_PUBLIC_KEY=TEST-your-public-key-here
MERCADOPAGO_WEBHOOK_SECRET=your-webhook-secret-here
MERCADOPAGO_SANDBOX=true

# Frontend URL
FRONTEND_URL=http://localhost:5173
```

### **3. Como Obter as Credenciais do Mercado Pago**

1. **Acesse o painel do Mercado Pago:**
   - Vá para: https://www.mercadopago.com.br/developers/
   - Faça login na sua conta

2. **Crie uma aplicação:**
   - Clique em "Criar aplicação"
   - Escolha "Checkout Pro" ou "Checkout API"
   - Preencha os dados da aplicação

3. **Obtenha as credenciais de teste:**
   - Access Token: `TEST-xxxxx-xxxxxx-xxxxx`
   - Public Key: `TEST-xxxxx-xxxxxx-xxxxx`

4. **Configure o Webhook:**
   - URL: `https://seu-dominio.com/api/webhooks/mercadopago`
   - Eventos: `payment`, `merchant_order`

## 🔧 **Testando a Configuração**

### **Teste via Artisan Tinker:**

```php
php artisan tinker

// Testar se as configurações estão corretas
config('services.mercadopago.access_token')
config('services.mercadopago.public_key')

// Testar o serviço
$service = app(\App\Services\MercadoPagoService::class);

// Criar uma preferência de teste
$testData = [
    'items' => [
        [
            'id' => '1',
            'title' => 'Teste',
            'quantity' => 1,
            'currency_id' => 'BRL',
            'unit_price' => 100.0
        ]
    ],
    'payer' => [
        'email' => 'test@test.com'
    ]
];

$preference = $service->createPreference($testData);
echo "Preferência criada: " . $preference->id;
```

## 🚨 **Problemas Comuns e Soluções**

### **Erro 401 - Unauthorized**
- ✅ Verifique se o ACCESS_TOKEN está correto
- ✅ Confirme se está usando credenciais de TEST para sandbox
- ✅ Verifique se não há espaços extras nas variáveis

### **Erro de Email Nulo**
- ✅ Já corrigido no PaymentController
- ✅ Agora usa fallback: `$user->email ?? 'cliente@example.com'`

### **Webhook não funciona**
- ✅ Use ngrok para desenvolvimento local: `ngrok http 8000`
- ✅ Configure a URL no painel do Mercado Pago
- ✅ Verifique se o WEBHOOK_SECRET está correto

## 📝 **Logs Importantes**

### **Sucesso:**
```
MercadoPagoService inicializado {"access_token_preview":"TEST-69476..."}
Preferência criada com sucesso {"preference_id":"xxx"}
```

### **Erro de Token:**
```
Erro na API do Mercado Pago: {"status_code":401}
```

### **Erro de Dados:**
```
Erro geral ao criar preferência: {"message":"Attempt to read property..."}
```

## 🔄 **Próximos Passos**

1. **Configure as variáveis no .env**
2. **Teste com Tinker**
3. **Verifique os logs**: `tail -f storage/logs/laravel.log`
4. **Teste uma transação real**

## 📞 **Suporte**

Se os problemas persistirem:
- Verifique se as credenciais estão ativas no painel do MP
- Confirme se a aplicação tem permissões necessárias
- Teste com dados mínimos primeiro
