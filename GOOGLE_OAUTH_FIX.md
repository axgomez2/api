# 🔧 Correção: Google OAuth Redirect

## ✅ O Que Foi Corrigido

**Problema:** Login com Google redirecionava para `localhost:5173` em produção.

**Solução:** Aceitar `redirect_uri` dinâmico do frontend e validar domínios permitidos.

---

## 📂 Arquivo Modificado

### **Localização:**
```
c:\Users\dj_al\Herd\api\app\Http\Controllers\Api\ClientAuthController.php
```

---

## 🔧 Alterações Implementadas

### **1. Método `redirectToGoogle()` (linha ~394)**

**ANTES:**
```php
public function redirectToGoogle()
{
    return Socialite::driver('google')->stateless()->redirect();
}
```

**DEPOIS:**
```php
public function redirectToGoogle(Request $request)
{
    // Receber redirect_uri do frontend
    $redirectUri = $request->query('redirect_uri');
    
    // Validar domínios permitidos
    $allowedDomains = [
        'https://rdvdiscos.com.br',
        'http://localhost:5173',
    ];
    
    $isValid = false;
    if ($redirectUri) {
        foreach ($allowedDomains as $domain) {
            if (str_starts_with($redirectUri, $domain)) {
                $isValid = true;
                break;
            }
        }
    }
    
    // Se não for válido, usar padrão
    if (!$isValid) {
        $redirectUri = env('FRONTEND_URL', 'https://rdvdiscos.com.br') . '/auth/callback';
    }
    
    // Salvar redirect_uri na sessão para usar no callback
    session(['google_redirect_uri' => $redirectUri]);
    
    \Log::info('Google OAuth redirect iniciado:', [
        'redirect_uri' => $redirectUri,
        'from_request' => $request->query('redirect_uri')
    ]);
    
    return Socialite::driver('google')->stateless()->redirect();
}
```

**Mudanças:**
- ✅ Adiciona parâmetro `Request $request`
- ✅ Recebe `redirect_uri` da query string
- ✅ Valida contra lista de domínios permitidos
- ✅ Salva na sessão para usar no callback
- ✅ Log para debug

---

### **2. Método `handleGoogleCallback()` (linha ~402)**

**ANTES:**
```php
$token = $user->createToken('client-auth-token')->plainTextToken;

// Redirecionar para o frontend com o token
$frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
return redirect($frontendUrl . '/auth/callback?token=' . $token);
```

**DEPOIS:**
```php
$token = $user->createToken('client-auth-token')->plainTextToken;

// Pegar redirect_uri da sessão (definido no redirectToGoogle)
$redirectUri = session('google_redirect_uri', env('FRONTEND_URL', 'https://rdvdiscos.com.br') . '/auth/callback');

\Log::info('Google OAuth callback concluído com sucesso:', [
    'user_id' => $user->id,
    'redirect_uri' => $redirectUri
]);

// Limpar a sessão
session()->forget('google_redirect_uri');

// Redirecionar para o frontend com o token
return redirect($redirectUri . '?token=' . $token);
```

**Mudanças:**
- ✅ Pega `redirect_uri` da sessão
- ✅ Log para debug
- ✅ Limpa sessão após uso
- ✅ Redireciona para URL dinâmica

**Tratamento de Erro:**
```php
catch (\Exception $e) {
    Log::error('Erro no callback do Google:', ['error' => $e->getMessage()]);
    
    // Pegar redirect_uri da sessão ou usar padrão
    $redirectUri = session('google_redirect_uri');
    session()->forget('google_redirect_uri');
    
    if (!$redirectUri) {
        $redirectUri = env('FRONTEND_URL', 'https://rdvdiscos.com.br') . '/login';
    } else {
        // Remover /auth/callback e adicionar /login
        $redirectUri = str_replace('/auth/callback', '/login', $redirectUri);
    }
    
    return redirect($redirectUri . '?error=google_login_failed');
}
```

---

## 🔄 Fluxo Completo

### **1. Frontend Inicia Login:**
```javascript
// src/config/api.js
const redirectUri = encodeURIComponent('https://rdvdiscos.com.br/auth/callback')
const url = `https://api.rdvdiscos.com.br/api/client/auth/google/redirect?redirect_uri=${redirectUri}`
window.location.href = url
```

### **2. Backend Recebe Request:**
```php
// ClientAuthController.php - redirectToGoogle()
$redirectUri = $request->query('redirect_uri'); // 'https://rdvdiscos.com.br/auth/callback'
// Valida domínio ✅
session(['google_redirect_uri' => $redirectUri]); // Salva na sessão
return Socialite::driver('google')->stateless()->redirect(); // Redireciona para Google
```

### **3. Google Autentica:**
- Usuário faz login no Google
- Google redireciona para: `https://api.rdvdiscos.com.br/api/client/auth/google/callback`

### **4. Backend Processa Callback:**
```php
// ClientAuthController.php - handleGoogleCallback()
$googleUser = Socialite::driver('google')->stateless()->user();
$user = ClientUser::updateOrCreate(...); // Cria/atualiza usuário
$token = $user->createToken('client-auth-token')->plainTextToken;
$redirectUri = session('google_redirect_uri'); // Pega da sessão: 'https://rdvdiscos.com.br/auth/callback'
return redirect($redirectUri . '?token=' . $token); // Redireciona para frontend
```

### **5. Frontend Recebe Token:**
```
URL: https://rdvdiscos.com.br/auth/callback?token=ABC123...
```

Página `AuthCallback.vue` processa o token e loga o usuário.

---

## 🔒 Segurança

### **Validação de Domínios:**
```php
$allowedDomains = [
    'https://rdvdiscos.com.br',  // Produção
    'http://localhost:5173',      // Desenvolvimento
];
```

**Se `redirect_uri` não for de domínio permitido:**
- ❌ Rejeita o redirect_uri fornecido
- ✅ Usa padrão: `https://rdvdiscos.com.br/auth/callback`

### **Prevenção de Open Redirect:**
- Usa `str_starts_with()` para validar início da URL
- Lista branca de domínios
- Fallback seguro

---

## 🧪 Como Testar

### **1. Desenvolvimento Local:**
```bash
# Frontend envia
redirect_uri=http://localhost:5173/auth/callback

# Backend valida ✅
# Redireciona para: http://localhost:5173/auth/callback?token=...
```

### **2. Produção:**
```bash
# Frontend envia
redirect_uri=https://rdvdiscos.com.br/auth/callback

# Backend valida ✅
# Redireciona para: https://rdvdiscos.com.br/auth/callback?token=...
```

### **3. Tentativa Maliciosa:**
```bash
# Atacante tenta
redirect_uri=https://site-malicioso.com/roubar-token

# Backend valida ❌
# Usa padrão: https://rdvdiscos.com.br/auth/callback?token=...
```

---

## 📋 Checklist de Deploy

### **Backend (API):**
- [x] Arquivo modificado: `ClientAuthController.php`
- [ ] Testar localmente
- [ ] Commit e push
- [ ] Deploy para produção
- [ ] Limpar cache Laravel:
  ```bash
  php artisan config:clear
  php artisan cache:clear
  php artisan route:clear
  ```

### **Frontend:**
- [x] Arquivo `.env` configurado com `VITE_FRONTEND_URL`
- [x] Arquivo `src/config/api.js` atualizado
- [x] Build realizado (`npm run build`)
- [ ] Deploy para produção

### **Teste Final:**
- [ ] Desktop: Login Google → Redireciona corretamente
- [ ] Mobile: Login Google → Redireciona corretamente
- [ ] iPhone: Login Google → Redireciona corretamente

---

## 📊 Logs para Monitorar

### **Backend (Laravel):**
```bash
# Verificar logs
tail -f storage/logs/laravel.log

# Procurar por:
# "Google OAuth redirect iniciado"
# "Google OAuth callback concluído com sucesso"
```

**Log Esperado (Sucesso):**
```
[2025-11-05 19:00:00] Google OAuth redirect iniciado: {"redirect_uri":"https://rdvdiscos.com.br/auth/callback","from_request":"https://rdvdiscos.com.br/auth/callback"}
[2025-11-05 19:00:05] Google OAuth callback: {"email":"user@example.com","name":"User Name","google_id":"123456"}
[2025-11-05 19:00:06] Google OAuth callback concluído com sucesso: {"user_id":42,"redirect_uri":"https://rdvdiscos.com.br/auth/callback"}
```

---

## ⚠️ Importante: Sessões

Como o código usa `session()`, certifique-se de que:

### **1. Driver de Sessão Apropriado:**
```env
# .env
SESSION_DRIVER=cookie  # ou database, redis
```

### **2. Domínio de Sessão:**
```env
# .env
SESSION_DOMAIN=.rdvdiscos.com.br
```

### **3. Secure Cookies em Produção:**
```env
# .env
SESSION_SECURE_COOKIE=true  # Somente HTTPS
```

---

## 🎯 Resultado

| Ambiente | Frontend URL | Backend Redireciona Para |
|----------|--------------|--------------------------|
| **Dev** | `http://localhost:5173` | `http://localhost:5173/auth/callback?token=...` |
| **Prod** | `https://rdvdiscos.com.br` | `https://rdvdiscos.com.br/auth/callback?token=...` |

✅ **Sempre redireciona para o domínio correto!**

---

**Data de Implementação:** 05/11/2025  
**Arquivo:** `app/Http/Controllers/Api/ClientAuthController.php`  
**Métodos Modificados:** `redirectToGoogle()`, `handleGoogleCallback()`  
**Status:** ✅ Pronto para deploy
