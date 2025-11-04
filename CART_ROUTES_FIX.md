# 🛒 CORREÇÃO CRÍTICA: Rotas de Cart 404

## 🚨 Problema Identificado

Frontend estava recebendo **404 Not Found** ao tentar acessar carrinho:
```
GET https://api.rdvdiscos.com.br/api/client/cart 404 (Not Found)
POST https://api.rdvdiscos.com.br/api/client/cart 404 (Not Found)
```

## 🔍 Causa Raiz

As rotas de cart estavam registradas SEM o prefixo `client/`:

```php
// ❌ ERRADO (routes/api.php linha 117)
Route::prefix('cart')->group(function () {
    // Gerava rotas: /api/cart
```

Mas o frontend esperava rotas com prefixo `client/`:

```javascript
// Frontend (config/api.js)
CART: {
  LIST: '/client/cart',    // ✅ Correto
  ADD: '/client/cart',     // ✅ Correto
```

## ✅ Solução Implementada

### 1. Rotas Corrigidas (api.php linha 117)

```php
// ✅ CORRETO
Route::prefix('client/cart')->group(function () {
    Route::get('/', [CartController::class, 'index']);
    Route::post('/', [CartController::class, 'store']);
    Route::put('/{productId}', [CartController::class, 'update']);
    Route::delete('/{productId}', [CartController::class, 'destroy']);
    Route::delete('/', [CartController::class, 'clear']);
});
```

**Rotas Geradas**:
- ✅ `GET /api/client/cart`
- ✅ `POST /api/client/cart`
- ✅ `PUT /api/client/cart/{productId}`
- ✅ `DELETE /api/client/cart/{productId}`
- ✅ `DELETE /api/client/cart`

### 2. Rotas Duplicadas Removidas

Comentadas as rotas antigas do middleware `auth:sanctum` (linhas 492-499):

```php
// ❌ DUPLICATAS REMOVIDAS
// Route::prefix('cart')->group(function () {
//     Route::get('/', [CartController::class, 'index']);
//     Route::post('/add', [CartController::class, 'add']);
//     ...
// });
```

## 🎯 Benefícios

1. **Consistência**: Todas rotas client usam prefixo `/client/`
2. **Sem duplicatas**: Apenas um conjunto de rotas de cart
3. **Middleware correto**: Usa `client.auth` em vez de `auth:sanctum`
4. **Frontend compatível**: Rotas exatamente como esperado

## 📊 Comparação

| Aspecto | Antes | Depois |
|---------|-------|--------|
| URL Cart | `/api/cart` | `/api/client/cart` ✅ |
| Middleware | Misturado | `client.auth` ✅ |
| Duplicatas | Sim ❌ | Não ✅ |
| Frontend | 404 ❌ | 200 ✅ |

## 🔧 Arquivos Modificados

- `routes/api.php`:
  - Linha 117: Adicionado prefixo `client/`
  - Linhas 492-499: Comentadas rotas duplicadas

## 🚀 Deploy

1. **Commit**: `f6ea0da` - "Fix: Adicionar prefixo client/ nas rotas de cart e remover duplicatas"
2. **Push**: Completo para `origin/main`
3. **Produção**: Aguardando cache clear

## ⚠️ IMPORTANTE

**No servidor de produção, é OBRIGATÓRIO executar**:

```bash
php artisan route:clear
php artisan cache:clear
php artisan route:cache
```

Sem isso, o cache antigo permanece e o erro 404 continua!

## 🧪 Como Testar

### Backend (Terminal)
```bash
php artisan route:list --path=cart
```

Deve mostrar rotas com `/api/client/cart`

### Frontend (Browser Console)
```javascript
// Testar no console do navegador após deploy
const response = await fetch('https://api.rdvdiscos.com.br/api/client/cart', {
  headers: {
    'Authorization': 'Bearer SEU_TOKEN_AQUI'
  }
})
console.log(response.status) // Deve ser 200
```

## ✅ Status

- Backend: **CORRIGIDO** ✅
- Deploy Git: **COMPLETO** ✅
- Cache Produção: **PENDENTE** ⏳
- Testes: **AGUARDANDO DEPLOY** ⏳
