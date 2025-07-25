# 🧪 Guia de Testes da API

## 🚀 Como Testar as Mudanças

### **1. Preparar o Ambiente**

```bash
# Limpar cache
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Verificar se as rotas estão carregadas
php artisan route:list --path=api/v2
```

### **2. Testar Endpoints Básicos**

#### **Health Check**
```bash
curl -X GET "http://localhost:8000/api/v2/health" \
  -H "Accept: application/json"
```

**Resposta Esperada:**
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "version": "2.0",
    "timestamp": "2025-01-18 15:30:00"
  },
  "message": "API funcionando corretamente"
}
```

#### **Configuração da API**
```bash
curl -X GET "http://localhost:8000/api/v2/config" \
  -H "Accept: application/json"
```

### **3. Testar Autenticação**

#### **Registro de Usuário**
```bash
curl -X POST "http://localhost:8000/api/v2/auth/register" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Teste User",
    "email": "teste@example.com",
    "password": "123456",
    "password_confirmation": "123456"
  }'
```

**Resposta Esperada:**
```json
{
  "success": true,
  "data": {
    "token": "1|abc123...",
    "user": {
      "id": "uuid-here",
      "name": "Teste User",
      "email": "teste@example.com"
    }
  },
  "message": "Conta criada com sucesso!"
}
```

#### **Login**
```bash
curl -X POST "http://localhost:8000/api/v2/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "teste@example.com",
    "password": "123456"
  }'
```

### **4. Testar Produtos (Rotas Públicas)**

#### **Listar Produtos**
```bash
curl -X GET "http://localhost:8000/api/v2/shop/products?per_page=5" \
  -H "Accept: application/json"
```

#### **Buscar Produtos**
```bash
curl -X GET "http://localhost:8000/api/v2/shop/products/search?q=vinil" \
  -H "Accept: application/json"
```

#### **Produtos de Vinil**
```bash
curl -X GET "http://localhost:8000/api/v2/shop/products/vinyl?per_page=10" \
  -H "Accept: application/json"
```

#### **Últimos Vinis**
```bash
curl -X GET "http://localhost:8000/api/v2/shop/products/vinyl/latest/5" \
  -H "Accept: application/json"
```

### **5. Testar Rotas Protegidas**

#### **Perfil do Usuário**
```bash
curl -X GET "http://localhost:8000/api/v2/user/profile" \
  -H "Authorization: Bearer SEU_TOKEN_AQUI" \
  -H "Accept: application/json"
```

#### **Carrinho - Listar**
```bash
curl -X GET "http://localhost:8000/api/v2/cart" \
  -H "Authorization: Bearer SEU_TOKEN_AQUI" \
  -H "Accept: application/json"
```

#### **Carrinho - Adicionar Item**
```bash
curl -X POST "http://localhost:8000/api/v2/cart/add" \
  -H "Authorization: Bearer SEU_TOKEN_AQUI" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "product_id": 1,
    "quantity": 1
  }'
```

### **6. Script de Teste Automatizado**

Crie um arquivo `test_api.sh`:

```bash
#!/bin/bash

API_BASE="http://localhost:8000/api/v2"
TOKEN=""

echo "🧪 Testando API V2..."

# 1. Health Check
echo "1. Health Check..."
curl -s "$API_BASE/health" | jq .

# 2. Config
echo "2. Configuração..."
curl -s "$API_BASE/config" | jq .success

# 3. Produtos
echo "3. Listando produtos..."
curl -s "$API_BASE/shop/products?per_page=2" | jq '.data.data | length'

# 4. Busca
echo "4. Buscando produtos..."
curl -s "$API_BASE/shop/products/search?q=test" | jq .success

# 5. Registro (se não existir)
echo "5. Testando registro..."
REGISTER_RESPONSE=$(curl -s -X POST "$API_BASE/auth/register" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test'$(date +%s)'@example.com",
    "password": "123456",
    "password_confirmation": "123456"
  }')

TOKEN=$(echo $REGISTER_RESPONSE | jq -r '.data.token // empty')

if [ ! -z "$TOKEN" ]; then
  echo "✅ Token obtido: ${TOKEN:0:20}..."
  
  # 6. Perfil
  echo "6. Testando perfil..."
  curl -s "$API_BASE/user/profile" \
    -H "Authorization: Bearer $TOKEN" | jq .success
  
  # 7. Carrinho
  echo "7. Testando carrinho..."
  curl -s "$API_BASE/cart" \
    -H "Authorization: Bearer $TOKEN" | jq .success
else
  echo "❌ Falha ao obter token"
fi

echo "🎉 Testes concluídos!"
```

### **7. Usando Postman/Insomnia**

Importe esta coleção JSON:

```json
{
  "info": {
    "name": "RDV Discos API V2",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "variable": [
    {
      "key": "base_url",
      "value": "http://localhost:8000/api/v2"
    },
    {
      "key": "token",
      "value": ""
    }
  ],
  "item": [
    {
      "name": "Health Check",
      "request": {
        "method": "GET",
        "header": [],
        "url": "{{base_url}}/health"
      }
    },
    {
      "name": "Register",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"name\": \"Test User\",\n  \"email\": \"test@example.com\",\n  \"password\": \"123456\",\n  \"password_confirmation\": \"123456\"\n}"
        },
        "url": "{{base_url}}/auth/register"
      }
    },
    {
      "name": "Login",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"email\": \"test@example.com\",\n  \"password\": \"123456\"\n}"
        },
        "url": "{{base_url}}/auth/login"
      }
    },
    {
      "name": "Products",
      "request": {
        "method": "GET",
        "header": [],
        "url": "{{base_url}}/shop/products"
      }
    },
    {
      "name": "Cart",
      "request": {
        "method": "GET",
        "header": [
          {
            "key": "Authorization",
            "value": "Bearer {{token}}"
          }
        ],
        "url": "{{base_url}}/cart"
      }
    }
  ]
}
```

## 🔍 Verificações Importantes

### **1. Logs**
```bash
tail -f storage/logs/laravel.log
```

### **2. Banco de Dados**
```bash
php artisan migrate:status
```

### **3. Cache**
```bash
php artisan config:cache
php artisan route:cache
```

### **4. Permissões**
```bash
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
```

## ✅ Checklist de Testes

- [ ] Health check responde corretamente
- [ ] Configuração da API carrega
- [ ] Registro de usuário funciona
- [ ] Login retorna token válido
- [ ] Produtos são listados
- [ ] Busca funciona
- [ ] Rotas protegidas exigem autenticação
- [ ] Carrinho funciona com token
- [ ] Respostas seguem padrão da API
- [ ] Logs não mostram erros críticos

## 🚨 Problemas Comuns

### **Erro 404 nas rotas V2**
```bash
php artisan route:clear
php artisan config:clear
```

### **Erro de CORS**
Verificar configuração em `config/cors.php`

### **Token inválido**
Verificar se o middleware `client.auth` está funcionando

### **Erro de validação**
Verificar se os Form Requests estão sendo usados corretamente