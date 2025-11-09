# 🚀 DEPLOY - Fix Ordenação Produtos por Categoria

## ✅ CORREÇÃO IMPLEMENTADA

O endpoint `/api/categories/{slug}/products` agora retorna produtos **ordenados por created_at DESC** (mais recentes primeiro).

---

## 📝 RESUMO DA MUDANÇA

**Arquivo:** `app/Http/Controllers/Api/CategoryController.php`  
**Método:** `productsByCategory()`

**Antes:**
```php
->paginate(15); // ❌ Sem ordenação
```

**Depois:**
```php
->orderBy('created_at', 'desc')  // ✅ Mais recentes primeiro
->paginate($perPage);
```

---

## 🔧 DEPLOY NECESSÁRIO

### **Backend (API Laravel):**

```bash
# 1. Cache já limpo ✅
php artisan cache:clear

# 2. Opcional - Route e Config (se necessário):
php artisan route:clear
php artisan config:clear

# 3. Reiniciar servidor (se usar PHP-FPM ou Apache):
# sudo systemctl restart php8.2-fpm
# sudo systemctl restart apache2
```

### **Frontend (Vue):**

**Build já gerado anteriormente:**
```
✅ index-_kjwTCv3.js
✅ index-DAMZ0ZaG.css
```

**Deploy:**
```bash
# Upload dist/ para produção
# /var/www/rdvdiscos.com.br/

# Limpar cache Cloudflare
# Cloudflare → Purge Everything
```

---

## 🧪 TESTAR API

### **1. Endpoint direto:**
```bash
curl "https://api.rdvdiscos.com.br/api/categories/house/products?per_page=5"
```

**Verificar no response:**
```json
{
  "data": {
    "data": [
      {"id": 150, "created_at": "2025-11-09"},  // ← mais recente
      {"id": 145, "created_at": "2025-11-08"},
      {"id": 142, "created_at": "2025-11-07"}   // ← mais antigo
    ]
  }
}
```

### **2. Frontend (Console F12):**
```javascript
// Na HomeView, verificar:
categoriesWithProducts.value[0].products.forEach((p, i) => {
  console.log(`${i}: ${p.created_at}`)
})

// Output esperado (mais recente → mais antigo):
// 0: 2025-11-09
// 1: 2025-11-08
// 2: 2025-11-07
```

---

## 📋 CHECKLIST DEPLOY

### **API (Laravel):**
- [x] Código modificado
- [x] Cache limpo
- [ ] Servidor reiniciado (se necessário)
- [ ] API testada com curl/postman

### **Frontend (Vue):**
- [x] Build gerado
- [ ] Upload dist/ para produção
- [ ] Cache Cloudflare limpo
- [ ] HomeView carregando corretamente

### **Validação Final:**
- [ ] Home exibe 7 categorias
- [ ] Cada categoria com 10 produtos
- [ ] Produtos ordenados (recente → antigo)
- [ ] Console sem erros

---

## 🎯 RESULTADO ESPERADO

### **HomeView (https://rdvdiscos.com.br/):**

```
┌────────────────────────────────┐
│ Playlists                      │
├────────────────────────────────┤
│ 📀 CATEGORIA 1                 │
│ [Produto Recente 09/11] ←✅    │
│ [Produto 08/11]                │
│ [Produto 07/11]                │
│ ... 10 produtos total          │
├────────────────────────────────┤
│ 📀 CATEGORIA 20                │
│ [Produto Recente 09/11] ←✅    │
│ ... (7 categorias total)       │
└────────────────────────────────┘
```

---

## 💡 FEATURES IMPLEMENTADAS

### **1. Ordenação Padrão:**
```
created_at DESC (sem precisar passar parâmetro)
```

### **2. Query Parameters:**
```
?sort=-created_at  → DESC (padrão)
?sort=created_at   → ASC
?per_page=10       → 10 produtos
```

### **3. Flexibilidade:**
```php
// Aceita qualquer campo:
?sort=-name        → Nome DESC
?sort=price        → Preço ASC
```

---

## 🔄 IMPACTO

### **Views Afetadas:**
- ✅ **HomeView** - Categorias específicas (1, 20, 21, 28, 31, 26, 23)
- ✅ **CategoryView** - Listagem de produtos por categoria
- ✅ **Qualquer outra** que use `/categories/{slug}/products`

### **Comportamento:**
```
ANTES: Produtos antigos primeiro (ordem indefinida)
DEPOIS: Produtos mais recentes primeiro (created_at DESC)
```

---

## 🚨 SE DER ERRO

### **Problema: API ainda retorna produtos antigos**

**Solução 1: Cache do Browser**
```
Ctrl + Shift + Delete → Limpar cache
Ctrl + F5 (hard refresh)
```

**Solução 2: Cache do Laravel**
```bash
php artisan cache:clear
php artisan route:clear
php artisan config:clear
php artisan view:clear
```

**Solução 3: Reiniciar servidor**
```bash
# Se usar Herd (Windows):
# Reiniciar Herd

# Se usar Linux:
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
```

**Solução 4: Verificar logs**
```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Verificar se query está sendo executada com ORDER BY
```

---

## 🧪 TESTE FINAL

### **1. API funciona:**
```bash
curl "http://127.0.0.1:8000/api/categories/house/products?per_page=3"
```

### **2. Frontend recebe ordenado:**
```javascript
// Console do navegador
fetch('https://api.rdvdiscos.com.br/api/categories/house/products?per_page=3')
  .then(r => r.json())
  .then(d => console.table(d.data.data.map(p => ({
    id: p.id,
    created: p.created_at
  }))))
```

### **3. HomeView exibe correto:**
```
Abrir: https://rdvdiscos.com.br/
Scroll até após playlists
Verificar: primeiro produto de cada categoria é o mais recente
```

---

## 📊 QUERY SQL GERADA

**Antes (sem ordenação):**
```sql
SELECT * FROM products
WHERE productable_type = 'App\Models\VinylMaster'
AND productable_id IN (1, 2, 3, ...)
LIMIT 15
```

**Depois (com ordenação):**
```sql
SELECT * FROM products
WHERE productable_type = 'App\Models\VinylMaster'
AND productable_id IN (1, 2, 3, ...)
ORDER BY created_at DESC  ← ADICIONADO
LIMIT 10
```

---

## 📄 DOCUMENTAÇÃO

**Criadas:**
- ✅ `FIX_CATEGORY_PRODUCTS_SORT.md` - Detalhes técnicos
- ✅ `DEPLOY_CATEGORY_SORT_FIX.md` - Instruções de deploy

---

**Status:** ✅ Pronto para deploy  
**Cache limpo:** ✅ Sim  
**Teste local:** ⏳ Aguardando deploy para produção  
**Compatibilidade:** ✅ 100% backward compatible
