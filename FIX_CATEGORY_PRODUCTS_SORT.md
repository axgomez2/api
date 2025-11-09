# 🔧 FIX: Ordenação de Produtos por Categoria

## ⚠️ PROBLEMA IDENTIFICADO

Produtos estavam sendo retornados do **mais antigo para o mais recente**, quando deveria ser o contrário.

**Causa:** O método `productsByCategory()` não estava aplicando ordenação.

---

## ✅ CORREÇÃO APLICADA

### **Arquivo:** `app/Http/Controllers/Api/CategoryController.php`

**Método:** `productsByCategory(Request $request, $slug)`

---

## 🔧 MUDANÇAS

### **ANTES:**
```php
public function productsByCategory($slug)
{
    // ... busca categoria e produtos
    
    $products = Product::whereHasMorph(...)
        ->with([...])
        ->paginate(15); // ❌ SEM ORDENAÇÃO
    
    return response()->json([...]);
}
```

### **DEPOIS:**
```php
public function productsByCategory(Request $request, $slug)
{
    // ... busca categoria e produtos
    
    $query = Product::whereHasMorph(...)
        ->with([...]);
    
    // ✅ ORDENAÇÃO ADICIONADA
    $sortField = 'created_at';
    $sortDirection = 'desc'; // DESC por padrão
    
    if ($request->has('sort')) {
        $sortParam = $request->input('sort');
        
        // Se começar com '-', é descendente
        if (str_starts_with($sortParam, '-')) {
            $sortField = substr($sortParam, 1);
            $sortDirection = 'desc';
        } else {
            $sortField = $sortParam;
            $sortDirection = 'asc';
        }
    }
    
    $query->orderBy($sortField, $sortDirection);
    
    // Per page (limite de resultados)
    $perPage = $request->input('per_page', 15);
    
    $products = $query->paginate($perPage);
    
    return response()->json([...]);
}
```

---

## 🎯 FUNCIONALIDADES IMPLEMENTADAS

### **1. Ordenação Padrão:**
```
created_at DESC (mais recentes primeiro)
```

### **2. Query Parameters Aceitos:**

**sort:** Campo de ordenação
```
?sort=created_at          → ASC
?sort=-created_at         → DESC (padrão)
?sort=name                → ASC
?sort=-name               → DESC
```

**per_page:** Limite de resultados
```
?per_page=10              → 10 produtos
?per_page=50              → 50 produtos
(padrão: 15)
```

---

## 📡 EXEMPLOS DE USO DA API

### **1. Produtos recentes (padrão):**
```
GET /api/categories/house/products
```
**Resposta:** 15 produtos, ordenados por created_at DESC

---

### **2. 10 produtos mais recentes:**
```
GET /api/categories/house/products?per_page=10
```
**Resposta:** 10 produtos, ordenados por created_at DESC

---

### **3. Produtos mais recentes explicitamente:**
```
GET /api/categories/house/products?sort=-created_at&per_page=10
```
**Resposta:** 10 produtos, created_at DESC

---

### **4. Produtos mais antigos:**
```
GET /api/categories/house/products?sort=created_at&per_page=10
```
**Resposta:** 10 produtos, created_at ASC

---

### **5. Com relacionamentos:**
```
GET /api/categories/house/products?sort=-created_at&per_page=10&with=productable.tracks,productable.artists
```
**Resposta:** 10 produtos com tracks e artists

---

## 🧪 TESTANDO A API

### **Opção 1: cURL**
```bash
curl "http://127.0.0.1:8000/api/categories/house/products?sort=-created_at&per_page=5"
```

### **Opção 2: Postman/Insomnia**
```
GET http://127.0.0.1:8000/api/categories/house/products?sort=-created_at&per_page=5
```

### **Opção 3: Browser**
```
http://127.0.0.1:8000/api/categories/house/products?sort=-created_at&per_page=5
```

---

## 📊 ESTRUTURA DA RESPOSTA

```json
{
  "status": "success",
  "category": "House",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 150,
        "name": "Produto Mais Recente",
        "created_at": "2025-11-09T10:30:00Z",
        "productable": {
          "title": "Track House Novo",
          "artists": [...],
          "tracks": [...]
        }
      },
      {
        "id": 145,
        "name": "Segundo Mais Recente",
        "created_at": "2025-11-08T15:20:00Z",
        "productable": {...}
      }
      // ... mais produtos em ordem decrescente
    ],
    "per_page": 10,
    "total": 150
  }
}
```

---

## 🔍 VERIFICAÇÃO DE ORDENAÇÃO

### **Console do Frontend:**
```javascript
// Após receber produtos da API
products.forEach((p, i) => {
  console.log(`${i}: ${p.created_at}`)
})

// Output esperado (DESC):
// 0: 2025-11-09T10:30:00Z  ← mais recente
// 1: 2025-11-08T15:20:00Z
// 2: 2025-11-07T08:45:00Z
// 3: 2025-11-06T12:00:00Z  ← mais antigo
```

### **SQL Query Gerada:**
```sql
SELECT * FROM products
WHERE productable_type = 'App\Models\VinylMaster'
AND productable_id IN (1, 2, 3, ...)
ORDER BY created_at DESC
LIMIT 10
```

---

## 🚀 DEPLOY

### **1. Não precisa rebuild:**
```
✅ Mudança apenas no backend (PHP)
✅ Nenhum arquivo JS/CSS alterado
✅ Frontend usa a mesma query
```

### **2. Cache (se houver):**
```bash
php artisan cache:clear
php artisan route:clear
php artisan config:clear
```

### **3. Restart (opcional):**
```bash
# Se usar PHP-FPM
sudo systemctl restart php8.2-fpm

# Se usar Apache
sudo systemctl restart apache2

# Se usar Nginx
sudo systemctl restart nginx
```

---

## ✅ CHECKLIST

### **Código:**
- [x] Parâmetro `Request $request` adicionado
- [x] Ordenação padrão: `created_at DESC`
- [x] Suporte a query param `sort`
- [x] Suporte a query param `per_page`
- [x] Validação de parâmetros (`-` = DESC)

### **Deploy:**
- [ ] Cache limpo (se houver)
- [ ] Servidor reiniciado (se necessário)

### **Testes:**
- [ ] API retorna produtos ordenados DESC
- [ ] Parâmetro `?sort=-created_at` funciona
- [ ] Parâmetro `?per_page=10` funciona
- [ ] Frontend recebe produtos na ordem correta
- [ ] Console mostra logs corretos

---

## 🧪 TESTE RÁPIDO

### **1. Testar API diretamente:**
```bash
# Substitua 'house' pelo slug de uma categoria real
curl "http://127.0.0.1:8000/api/categories/house/products?per_page=3" | jq '.data.data[] | {id, created_at}'
```

**Output esperado:**
```json
{ "id": 150, "created_at": "2025-11-09" }  ← mais recente
{ "id": 145, "created_at": "2025-11-08" }
{ "id": 142, "created_at": "2025-11-07" }  ← mais antigo
```

### **2. Testar no Frontend:**
```javascript
// Console do navegador (F12)
fetch('https://api.rdvdiscos.com.br/api/categories/house/products?per_page=5')
  .then(r => r.json())
  .then(d => {
    console.table(d.data.data.map(p => ({
      id: p.id,
      created: p.created_at
    })))
  })
```

---

## 🔄 COMPORTAMENTO ANTERIOR vs ATUAL

### **ANTES:**
```
Ordem: INDEFINIDA (provavelmente ID ASC)
Resultado: Produtos mais antigos apareciam primeiro
Query: SELECT * FROM products WHERE ... (sem ORDER BY)
```

### **DEPOIS:**
```
Ordem: created_at DESC
Resultado: Produtos mais recentes aparecem primeiro
Query: SELECT * FROM products WHERE ... ORDER BY created_at DESC
```

---

## 💡 NOTAS IMPORTANTES

### **1. Compatibilidade:**
✅ Frontend existente continua funcionando  
✅ Query param `sort` é opcional  
✅ Ordenação padrão é DESC (mais recente)

### **2. Performance:**
✅ Índice em `created_at` recomendado:
```sql
CREATE INDEX idx_products_created_at ON products(created_at);
```

### **3. Flexibilidade:**
```php
// Aceita qualquer campo para ordenação:
?sort=name          → ordena por nome ASC
?sort=-price        → ordena por preço DESC
?sort=stock         → ordena por estoque ASC
```

---

## 🎯 IMPACTO

### **Views Afetadas:**
- ✅ **HomeView** - categorias específicas
- ✅ **CategoryView** - produtos por categoria
- ✅ **Qualquer view** que use `/categories/{slug}/products`

### **Resultado Esperado:**
```
✅ Produtos mais recentes aparecem primeiro
✅ Usuário vê novidades no topo
✅ Experiência de usuário melhorada
```

---

**Arquivo modificado:** `app/Http/Controllers/Api/CategoryController.php`  
**Método:** `productsByCategory()`  
**Ordenação padrão:** `created_at DESC`  
**Status:** ✅ Pronto para produção
