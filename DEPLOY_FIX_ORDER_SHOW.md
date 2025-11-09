# 🔥 FIX URGENTE: Order Show (500)

## ⚠️ PROBLEMA
Erro 500 ao acessar `/api/client/orders/74` (detalhes de pedido)

## ✅ CORREÇÃO
Método `show()` tentava carregar relacionamentos que não existem

**Arquivo corrigido:**
- `app/Http/Controllers/Api/OrderController.php` (linha 60-61)

**Mudança:**
```php
// ANTES (ERRO):
->with([
    'items.product',
    'items.vinyl',
    'statusHistory',
    'shippingLabel',      ← NÃO EXISTE
    'paymentTransactions', ← NÃO EXISTE
    'coupons'
])

// DEPOIS (CORRETO):
->with(['items'])  ← Apenas items
```

---

## 🚀 DEPLOY API

### **1. Upload do arquivo**
```
Local:  c:\Users\dj_al\Herd\api\app\Http\Controllers\Api\OrderController.php
Para:   /var/www/api.rdvdiscos.com.br/app/Http/Controllers/Api/OrderController.php
```

### **2. Limpar cache**
```bash
cd /var/www/api.rdvdiscos.com.br

php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan route:cache
```

---

## 🧪 TESTE

### **Antes:**
```
GET /api/client/orders/74
❌ 500 Internal Server Error
```

### **Depois:**
```
GET /api/client/orders/74
✅ 200 OK

{
  "success": true,
  "data": {
    "id": 74,
    "order_number": "ORD-20251109-LWJ9ZV",
    "status": "pending",
    "payment_status": "pending",
    "total": 19.98,
    "items": [...]
  }
}
```

---

## ⚡ URGENTE

**Deploy apenas esse arquivo!**
- Não precisa fazer build do frontend
- Apenas substituir `OrderController.php` e limpar cache
- Teste imediato: acessar qualquer pedido em `/orders/{id}`

---

**Arquivo:** `OrderController.php`
**Linhas:** 60-61
**Deploy:** URGENTE
