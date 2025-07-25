#!/bin/bash

echo "🚀 Configurando Frontend Vue.js para RDV Discos"

# Verificar se está na raiz do projeto
if [ ! -f "composer.json" ]; then
    echo "❌ Execute este script na raiz do projeto Laravel"
    exit 1
fi

# Criar projeto Vue
echo "📦 Criando projeto Vue.js..."
npm create vue@latest frontend -- --typescript false --jsx false --router true --pinia true --vitest true --eslint true --prettier true

cd frontend

echo "📦 Instalando dependências base..."
npm install

echo "🎨 Instalando Tailwind CSS..."
npm install -D tailwindcss autoprefixer postcss
npx tailwindcss init -p

echo "🌊 Instalando Flowbite..."
npm install flowbite flowbite-vue

echo "🔧 Instalando utilitários..."
npm install @headlessui/vue @heroicons/vue
npm install axios @tanstack/vue-query
npm install vee-validate yup
npm install date-fns lodash-es
npm install vue-toastification vue-loading-overlay

echo "⚙️ Configurando Tailwind..."
cat > tailwind.config.js << 'EOF'
/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
    "./node_modules/flowbite/**/*.js"
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: '#93c5fd',
          400: '#60a5fa',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
          800: '#1e40af',
          900: '#1e3a8a',
        },
        secondary: {
          50: '#f8fafc',
          100: '#f1f5f9',
          200: '#e2e8f0',
          300: '#cbd5e1',
          400: '#94a3b8',
          500: '#64748b',
          600: '#475569',
          700: '#334155',
          800: '#1e293b',
          900: '#0f172a',
        }
      }
    },
  },
  plugins: [
    require('flowbite/plugin'),
    require('@tailwindcss/forms'),
  ],
}
EOF

echo "⚙️ Configurando Vite..."
cat > vite.config.js << 'EOF'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: 'dist',
    sourcemap: true,
  },
})
EOF

echo "🎨 Configurando CSS principal..."
cat > src/style.css << 'EOF'
@tailwind base;
@tailwind components;
@tailwind utilities;

/* Componentes customizados */
@layer components {
  .btn-primary {
    @apply bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-4 rounded-lg transition-colors;
  }
  
  .btn-secondary {
    @apply bg-secondary-200 hover:bg-secondary-300 text-secondary-800 font-medium py-2 px-4 rounded-lg transition-colors;
  }
  
  .card {
    @apply bg-white rounded-lg shadow-md overflow-hidden;
  }
  
  .input-field {
    @apply block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500;
  }
}

/* Utilitários customizados */
@layer utilities {
  .text-balance {
    text-wrap: balance;
  }
}
EOF

echo "📁 Criando estrutura de pastas..."
mkdir -p src/api/services
mkdir -p src/components/{ui,layout,shop,forms}
mkdir -p src/composables
mkdir -p src/layouts
mkdir -p src/pages/{auth,orders}
mkdir -p src/stores
mkdir -p src/utils

echo "🔧 Criando arquivo de configuração da API..."
cat > src/api/client.js << 'EOF'
import axios from 'axios'

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v2',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// Request interceptor para adicionar token
apiClient.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

// Response interceptor para tratar erros
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default apiClient
EOF

echo "🔗 Criando endpoints da API..."
cat > src/api/endpoints.js << 'EOF'
export const API_ENDPOINTS = {
  // Auth
  AUTH: {
    LOGIN: '/auth/login',
    REGISTER: '/auth/register',
    LOGOUT: '/user/logout',
    PROFILE: '/user/profile',
  },
  
  // Products
  PRODUCTS: {
    LIST: '/shop/products',
    DETAIL: (slug) => `/shop/products/${slug}`,
    SEARCH: '/shop/products/search',
    VINYL: '/shop/products/vinyl',
    LATEST: (limit = 20) => `/shop/products/vinyl/latest/${limit}`,
  },
  
  // Cart
  CART: {
    LIST: '/cart',
    ADD: '/cart/add',
    REMOVE: (productId) => `/cart/remove/${productId}`,
    CLEAR: '/cart/clear',
    CHECK: (productId) => `/cart/check/${productId}`,
  },
  
  // Categories
  CATEGORIES: {
    LIST: '/shop/categories',
    DETAIL: (slug) => `/shop/categories/${slug}`,
    PRODUCTS: (slug) => `/shop/categories/${slug}/products`,
  },
}
EOF

echo "🏪 Criando store de autenticação..."
cat > src/stores/auth.js << 'EOF'
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import apiClient from '@/api/client'
import { API_ENDPOINTS } from '@/api/endpoints'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('auth_token'))
  
  const isAuthenticated = computed(() => !!token.value && !!user.value)
  
  const login = async (credentials) => {
    try {
      const response = await apiClient.post(API_ENDPOINTS.AUTH.LOGIN, credentials)
      
      if (response.data.success) {
        token.value = response.data.data.token
        user.value = response.data.data.user
        localStorage.setItem('auth_token', token.value)
        return response.data
      }
    } catch (error) {
      throw error
    }
  }
  
  const logout = () => {
    user.value = null
    token.value = null
    localStorage.removeItem('auth_token')
  }
  
  return {
    user,
    token,
    isAuthenticated,
    login,
    logout,
  }
})
EOF

echo "📄 Criando arquivo .env..."
cat > .env << 'EOF'
VITE_API_URL=http://localhost:8000/api/v2
VITE_APP_NAME=RDV Discos
EOF

echo "📝 Atualizando package.json..."
npm pkg set scripts.dev="vite"
npm pkg set scripts.build="vite build"
npm pkg set scripts.preview="vite preview"

echo "✅ Setup concluído!"
echo ""
echo "🚀 Para iniciar o desenvolvimento:"
echo "   cd frontend"
echo "   npm run dev"
echo ""
echo "🌐 Frontend estará disponível em: http://localhost:5173"
echo "🔗 API Laravel deve estar em: http://localhost:8000"
echo ""
echo "📚 Próximos passos:"
echo "   1. Testar a API com: curl http://localhost:8000/api/v2/health"
echo "   2. Iniciar o frontend: npm run dev"
echo "   3. Começar a desenvolver os componentes"
echo ""
echo "🎉 Boa sorte com o desenvolvimento!"