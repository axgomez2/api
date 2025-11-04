# 🚀 Instruções de Deploy - Correção de Rotas Cart

## 🔧 Problema Corrigido
- Rotas de cart mudadas de `/api/cart` para `/api/client/cart`
- Rotas duplicadas removidas do middleware `auth:sanctum`

## 📦 Deploy Realizado
- ✅ Commit: f6ea0da
- ✅ Push para origin/main: Completo

## 🔥 PASSOS OBRIGATÓRIOS NO SERVIDOR

Após o pull no servidor de produção, execute os seguintes comandos:

```bash
# 1. Acessar diretório da API
cd /caminho/para/api

# 2. Fazer pull das mudanças
git pull origin main

# 3. CRÍTICO: Limpar cache de rotas
php artisan route:clear

# 4. CRÍTICO: Limpar cache geral (config, views, etc)
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# 5. Otimizar para produção (opcional mas recomendado)
php artisan route:cache
php artisan config:cache

# 6. Reiniciar workers se houver
# supervisorctl restart laravel-worker:*
# ou
# php artisan queue:restart
```

## ✅ Verificação

Após os comandos acima, teste:

```bash
# Listar rotas de cart
php artisan route:list --path=cart

# Deve mostrar:
# GET|HEAD  api/client/cart ...................... 
# POST      api/client/cart ...................... 
# PUT       api/client/cart/{productId} ..........
# DELETE    api/client/cart/{productId} ..........
# DELETE    api/client/cart ......................
```

## 🧪 Testes no Frontend

Após deploy completo, teste:

1. **Visualizar carrinho**: GET `/api/client/cart` → Status 200
2. **Adicionar produto**: POST `/api/client/cart` → Status 201
3. **Atualizar quantidade**: PUT `/api/client/cart/{id}` → Status 200
4. **Remover item**: DELETE `/api/client/cart/{id}` → Status 200
5. **Limpar carrinho**: DELETE `/api/client/cart` → Status 200

## 🚨 Erros Esperados SEM o Cache Clear

Se você **NÃO** executar `php artisan route:clear`:
- ❌ 404 Not Found nas rotas `/api/client/cart`
- ❌ Cache antigo ainda apontando para `/api/cart`
- ❌ Frontend não consegue adicionar/visualizar items no carrinho

## 📝 Notas Importantes

- **SEMPRE** limpe o cache de rotas após mudanças em `routes/api.php`
- Em produção, use `route:cache` para performance
- O cache de rotas é armazenado em `bootstrap/cache/routes-v7.php`
