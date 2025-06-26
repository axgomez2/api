# 🚀 Configuração Simples - Melhor Envio

## ✅ **Método Recomendado: Token no .env**

### **Passo 1: Obter Token do Sandbox**

1. Acesse: https://sandbox.melhorenvio.com.br
2. Faça login ou cadastre-se
3. Vá em **"Integrações"** → **"Tokens"**
4. Gere um novo token de API

### **Passo 2: Configurar no Backend**

No arquivo `.env` do Laravel (`vueshopfim/.env`), adicione:

```env
MELHORENVIO_CLIENT_ID=6369
MELHORENVIO_CLIENT_SECRET=seu_client_secret_aqui
MELHORENVIO_BASE_URI=https://sandbox.melhorenvio.com.br
MELHORENVIO_BEARER_TOKEN=seu_token_aqui
```

### **Passo 3: Testar**

1. Vá para: `http://localhost:5173/config`
2. Clique em **"Verificar Configuração"**
3. Deve aparecer: ✅ **"Melhor Envio Configurado!"**

### **Passo 4: Usar no Carrinho**

1. Vá para: `http://localhost:5173/cart`
2. Digite um CEP no campo de frete
3. Clique em **"Calcular Frete"**
4. As opções de frete aparecerão

---

## 🔧 **Estrutura Atual**

### **Frontend (Vue.js)**
- `useShipping.js` - Composable para cálculo de frete
- `ShippingCalculator.vue` - Componente de cálculo
- `CheckoutView.vue` - Página de finalização

### **Backend (Laravel)**
- `MelhorEnvioService.php` - Serviço de integração
- `ShippingController.php` - Controller da API
- `routes/api.php` - Rota `/api/shipping/rates`

### **Fluxo Simples**
```
Frontend → /api/shipping/rates → MelhorEnvioService → Melhor Envio API → Resposta
```

---

## 🎯 **URLs de Teste**

- **Configurações**: `http://localhost:5173/config`
- **Carrinho**: `http://localhost:5173/cart`
- **Checkout**: `http://localhost:5173/checkout`
- **Debug API**: `https://vueshopfim.test/api/shipping/debug`

---

## ✨ **Vantagens desta Abordagem**

1. **Simples**: Apenas configurar no `.env`
2. **Direto**: Sem OAuth complicado
3. **Funcional**: Cálculo de frete imediato
4. **Flexível**: Pode usar OAuth depois se quiser

---

## 🐛 **Se não funcionar**

1. Verifique se o token está correto no `.env`
2. Confirme se o backend está rodando
3. Teste a API diretamente: `GET /api/shipping/debug`
4. Veja os logs: `tail -f storage/logs/laravel.log`

---

## 🎉 **Resultado Final**

Com esta configuração, você terá:
- ✅ Cálculo de frete funcionando
- ✅ Interface completa no frontend
- ✅ Integração com Melhor Envio
- ✅ Sem complicações de OAuth

**É isso! Simples e funcional! 🚀** 
