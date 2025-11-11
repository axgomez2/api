# 📋 API: Suporte para Lançamentos e Pré-vendas

## 🎯 Objetivo

Adicionar suporte completo na API Laravel para filtros e ordenação de produtos por:
- `is_new` (Lançamentos)
- `is_presale` (Pré-vendas)
- `release_date` (Data de lançamento)

---

## 📁 Arquivos Modificados

### 1. ✅ `app/Models/VinylSec.php`

**Adicionado ao `$fillable`:**
```php
'is_presale',
'presale_arrival_date',
'release_date',
```

### 2. ✅ `app/Http/Controllers/Api/ProductController.php`

**Método `vinylProducts()` - Novos filtros:**

```php
// Filtro por is_new (lançamentos)
if ($request->has('is_new') && $request->input('is_new') == 'true') {
    $query->whereHas('productable.vinylSec', function($q) {
        $q->where('is_new', 1);
    });
}

// Filtro por is_presale (pré-vendas)
if ($request->has('is_presale') && $request->input('is_presale') == 'true') {
    $query->whereHas('productable.vinylSec', function($q) {
        $q->where('is_presale', 1);
    });
}
```

**Ordenação por `release_date`:**

```php
$allowedSortFields = ['created_at', 'name', 'price', 'release_date'];

// Ordenação por release_date
if ($sortField === 'price' || $sortField === 'release_date') {
    $query->join('vinyl_masters', function ($join) {
        $join->on('products.productable_id', '=', 'vinyl_masters.id');
        $join->where('products.productable_type', '=', 'App\\Models\\VinylMaster');
    })
    ->join('vinyl_secs', 'vinyl_masters.id', '=', 'vinyl_secs.vinyl_master_id')
    ->orderBy('vinyl_secs.' . $sortField, $sortDirection)
    ->select('products.*');
}
```

**Suporte para `sort_order`:**
```php
// Permitir sort_order como alternativa para sort_direction
if ($request->has('sort_order')) {
    $sortDirection = $request->input('sort_order');
}
```

### 3. ✅ `database/migrations/2025_11_11_055300_add_presale_and_release_date_to_vinyl_secs.php`

**Nova migration para adicionar campos:**
```php
- is_presale (boolean, default: false)
- presale_arrival_date (date, nullable)
- release_date (date, nullable)
```

---

## 🔧 Endpoints da API

### **Principal:** `/api/products/vinyl`

**Suporta os seguintes parâmetros:**

#### Filtros Existentes:
- `year` - Filtrar por ano de lançamento
- `artist_id` - Filtrar por ID do artista
- `label_id` - Filtrar por ID da gravadora
- `category_id` - Filtrar por ID da categoria
- `search` - Busca por texto (título, artista, gravadora, tracks)
- `available_only=1` - Apenas produtos em estoque

#### ✅ NOVOS Filtros:
- `is_new=true` - Apenas lançamentos (produtos marcados como novos)
- `is_presale=true` - Apenas pré-vendas

#### Ordenação:
- `sort_by` - Campo para ordenar (`created_at`, `name`, `price`, `release_date`)
- `sort_direction` ou `sort_order` - Direção (`asc` ou `desc`)

#### Paginação:
- `per_page` - Itens por página (padrão: 20)

---

## 📊 Exemplos de Uso

### 1. Buscar Lançamentos (`is_new = true`)

```bash
GET /api/products/vinyl?is_new=true&per_page=1000&sort_by=created_at&sort_order=desc
```

**Retorna:** Todos os produtos com `is_new = 1`, ordenados por data de criação (mais recentes primeiro)

### 2. Buscar Pré-vendas (`is_presale = true`)

```bash
GET /api/products/vinyl?is_presale=true&per_page=1000&sort_by=release_date&sort_order=asc
```

**Retorna:** Todos os produtos com `is_presale = 1`, ordenados por data de lançamento (próximos primeiro)

### 3. Lançamentos de uma Categoria Específica

```bash
GET /api/products/vinyl?is_new=true&category_id=5&sort_by=created_at&sort_order=desc
```

### 4. Pré-vendas Disponíveis em Estoque

```bash
GET /api/products/vinyl?is_presale=true&available_only=1&sort_by=release_date&sort_order=asc
```

### 5. Buscar Lançamentos por Texto

```bash
GET /api/products/vinyl?is_new=true&search=pink%20floyd
```

---

## 🗄️ Estrutura da Tabela `vinyl_secs`

### Campos Relacionados:

| Campo | Tipo | Nullable | Default | Descrição |
|-------|------|----------|---------|-----------|
| `is_new` | boolean | No | false | Indica se é um lançamento |
| `is_presale` | boolean | No | false | Indica se está em pré-venda |
| `presale_arrival_date` | date | Yes | NULL | Data prevista de chegada do produto em pré-venda |
| `release_date` | date | Yes | NULL | Data oficial de lançamento do produto |

---

## 🚀 Deploy

### 1. Rodar Migration

```bash
cd /path/to/api
php artisan migrate
```

Isso adicionará os campos `is_presale`, `presale_arrival_date` e `release_date` na tabela `vinyl_secs` (se não existirem).

### 2. Limpar Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### 3. Verificar Alterações

```bash
# Testar endpoint de lançamentos
curl "https://api.rdvdiscos.com.br/api/products/vinyl?is_new=true&per_page=5"

# Testar endpoint de pré-vendas
curl "https://api.rdvdiscos.com.br/api/products/vinyl?is_presale=true&per_page=5"
```

---

## 📋 Checklist de Testes

- [ ] Migration executada com sucesso
- [ ] Campos aparecem na tabela `vinyl_secs`
- [ ] `/api/products/vinyl?is_new=true` retorna apenas produtos com `is_new = 1`
- [ ] `/api/products/vinyl?is_presale=true` retorna apenas produtos com `is_presale = 1`
- [ ] Ordenação por `release_date` funciona
- [ ] Parâmetro `sort_order` é aceito como alternativa para `sort_direction`
- [ ] Combinação de filtros funciona (`is_new + category_id`, etc.)

---

## ⚙️ Configuração de Produtos

### Marcar Produto como Lançamento:

```sql
UPDATE vinyl_secs 
SET is_new = 1 
WHERE vinyl_master_id = [ID_DO_PRODUTO];
```

### Marcar Produto como Pré-venda:

```sql
UPDATE vinyl_secs 
SET is_presale = 1, 
    presale_arrival_date = '2025-12-01',
    release_date = '2025-12-01'
WHERE vinyl_master_id = [ID_DO_PRODUTO];
```

---

## 🔄 Endpoints Alternativos

Além do endpoint principal, existem métodos específicos:

### Lançamentos (Limite Fixo)
```
GET /api/products/vinyl/new-arrivals/{limit?}
```

### Pré-vendas (Limite Fixo)
```
GET /api/products/vinyl/presale/{limit?}
```

**Nota:** Esses endpoints têm limite fixo e menos filtros. Use o endpoint principal `/api/products/vinyl` para maior flexibilidade.

---

## 🎯 Resposta da API

**Exemplo de produto com os novos campos:**

```json
{
  "id": 123,
  "name": "Pink Floyd - The Wall",
  "slug": "pink-floyd-the-wall",
  "productable": {
    "id": 456,
    "title": "The Wall",
    "vinylSec": {
      "id": 789,
      "price": 199.90,
      "promotional_price": 179.90,
      "is_new": 1,
      "is_presale": 0,
      "presale_arrival_date": null,
      "release_date": "2025-11-15",
      "stock": 10,
      "in_stock": true
    }
  }
}
```

---

## 📝 Notas Importantes

1. **Fallback de Ordenação:**
   - Se `release_date` for NULL, use `created_at` como fallback
   - O frontend já implementa isso automaticamente

2. **Valores Booleanos:**
   - `is_new` e `is_presale` são armazenados como `0` ou `1`
   - Aceita `true`/`false` como query parameter

3. **Compatibilidade:**
   - Migration usa `Schema::hasColumn()` para evitar erros se campos já existirem
   - Seguro executar múltiplas vezes

---

**Data de Criação:** 11/11/2025  
**Autor:** Cascade AI  
**Versão:** 1.0.0
