# 🎨 Guia de Estrutura Frontend Vue.js

## 📋 Estrutura Base Recomendada

```
frontend/
├── public/
│   ├── favicon.ico
│   └── index.html
├── src/
│   ├── api/
│   │   ├── client.js          # Configuração do Axios
│   │   ├── endpoints.js       # URLs dos endpoints
│   │   └── services/
│   │       ├── auth.js        # Serviços de autenticação
│   │       ├── products.js    # Serviços de produtos
│   │       ├── cart.js        # Serviços do carrinho
│   │       ├── wishlist.js    # Serviços da wishlist
│   │       └── payments.js    # Serviços de pagamento
│   ├── components/
│   │   ├── common/
│   │   │   ├── AppHeader.vue
│   │   │   ├── AppFooter.vue
│   │   │   ├── LoadingSpinner.vue
│   │   │   ├── ErrorMessage.vue
│   │   │   └── SuccessMessage.vue
│   │   ├── forms/
│   │   │   ├── BaseInput.vue
│   │   │   ├── BaseButton.vue
│   │   │   ├── BaseSelect.vue
│   │   │   └── BaseTextarea.vue
│   │   ├── shop/
│   │   │   ├── ProductCard.vue
│   │   │   ├── ProductGrid.vue
│   │   │   ├── ProductFilters.vue
│   │   │   ├── CartItem.vue
│   │   │   └── WishlistItem.vue
│   │   └── layout/
│   │       ├── Navbar.vue
│   │       ├── Sidebar.vue
│   │       └── Breadcrumb.vue
│   ├── composables/
│   │   ├── useApi.js          # Composable para chamadas API
│   │   ├── useAuth.js         # Composable de autenticação
│   │   ├── useCart.js         # Composable do carrinho
│   │   ├── useWishlist.js     # Composable da wishlist
│   │   ├── useProducts.js     # Composable de produtos
│   │   ├── useNotifications.js # Composable de notificações
│   │   └── useLocalStorage.js # Composable para localStorage
│   ├── layouts/
│   │   ├── DefaultLayout.vue
│   │   ├── AuthLayout.vue
│   │   └── CheckoutLayout.vue
│   ├── pages/
│   │   ├── Home.vue
│   │   ├── auth/
│   │   │   ├── Login.vue
│   │   │   ├── Register.vue
│   │   │   └── Profile.vue
│   │   ├── shop/
│   │   │   ├── Products.vue
│   │   │   ├── ProductDetail.vue
│   │   │   ├── Cart.vue
│   │   │   ├── Wishlist.vue
│   │   │   └── Checkout.vue
│   │   └── orders/
│   │       ├── Orders.vue
│   │       └── OrderDetail.vue
│   ├── router/
│   │   └── index.js
│   ├── stores/
│   │   ├── auth.js            # Pinia store para auth
│   │   ├── cart.js            # Pinia store para carrinho
│   │   ├── products.js        # Pinia store para produtos
│   │   └── notifications.js   # Pinia store para notificações
│   ├── styles/
│   │   ├── main.css
│   │   ├── components.css
│   │   └── utilities.css
│   ├── utils/
│   │   ├── constants.js
│   │   ├── helpers.js
│   │   ├── formatters.js
│   │   └── validators.js
│   ├── App.vue
│   └── main.js
├── package.json
├── vite.config.js
├── tailwind.config.js
└── README.md
```

## 🛠️ Tecnologias Recomendadas

### **Core**
- **Vue 3** (Composition API)
- **Vite** (Build tool)
- **Vue Router 4** (Roteamento)
- **Pinia** (State management)

### **UI/Styling**
- **Tailwind CSS** (Styling)
- **Headless UI** (Componentes acessíveis)
- **Heroicons** (Ícones)

### **HTTP/API**
- **Axios** (HTTP client)
- **Vue Query/TanStack Query** (Cache e sincronização)

### **Formulários**
- **VeeValidate** (Validação de formulários)
- **Yup** (Schema validation)

### **Utilitários**
- **date-fns** (Manipulação de datas)
- **lodash-es** (Utilitários)

## 📦 package.json Base

```json
{
  "name": "rdv-discos-frontend",
  "version": "1.0.0",
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview",
    "lint": "eslint . --ext .vue,.js,.jsx,.cjs,.mjs --fix --ignore-path .gitignore"
  },
  "dependencies": {
    "vue": "^3.4.0",
    "vue-router": "^4.2.0",
    "pinia": "^2.1.0",
    "axios": "^1.6.0",
    "@tanstack/vue-query": "^5.0.0",
    "@headlessui/vue": "^1.7.0",
    "@heroicons/vue": "^2.0.0",
    "vee-validate": "^4.12.0",
    "yup": "^1.4.0",
    "date-fns": "^3.0.0",
    "lodash-es": "^4.17.0"
  },
  "devDependencies": {
    "@vitejs/plugin-vue": "^5.0.0",
    "vite": "^5.0.0",
    "tailwindcss": "^3.4.0",
    "autoprefixer": "^10.4.0",
    "postcss": "^8.4.0",
    "eslint": "^8.57.0",
    "eslint-plugin-vue": "^9.20.0",
    "@vue/eslint-config-prettier": "^9.0.0",
    "prettier": "^3.2.0"
  }
}
```

## 🔧 Configurações Base

### **vite.config.js**
```javascript
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
})
```

### **tailwind.config.js**
```javascript
/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#eff6ff',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
        },
        secondary: {
          50: '#f8fafc',
          500: '#64748b',
          600: '#475569',
          700: '#334155',
        }
      }
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
}
```

## 🔌 API Client Base

### **src/api/client.js**
```javascript
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'

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
    const authStore = useAuthStore()
    if (authStore.token) {
      config.headers.Authorization = `Bearer ${authStore.token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

// Response interceptor para tratar erros
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    const authStore = useAuthStore()
    
    if (error.response?.status === 401) {
      authStore.logout()
      window.location.href = '/login'
    }
    
    return Promise.reject(error)
  }
)

export default apiClient
```

### **src/api/endpoints.js**
```javascript
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
  
  // Wishlist
  WISHLIST: {
    LIST: '/wishlist',
    ADD: '/wishlist/add',
    REMOVE: (productId) => `/wishlist/remove/${productId}`,
    CHECK: (productId) => `/wishlist/check/${productId}`,
  },
  
  // Categories
  CATEGORIES: {
    LIST: '/shop/categories',
    DETAIL: (slug) => `/shop/categories/${slug}`,
    PRODUCTS: (slug) => `/shop/categories/${slug}/products`,
  },
  
  // Orders
  ORDERS: {
    LIST: '/orders',
    CREATE: '/orders',
    DETAIL: (id) => `/orders/${id}`,
  },
  
  // Payments
  PAYMENTS: {
    CREATE_PREFERENCE: '/payments/create-preference',
    PROCESS: '/payments/process',
  },
}
```

## 🎯 Composables Base

### **src/composables/useApi.js**
```javascript
import { ref, reactive } from 'vue'
import apiClient from '@/api/client'

export function useApi() {
  const loading = ref(false)
  const error = ref(null)
  
  const request = async (config) => {
    loading.value = true
    error.value = null
    
    try {
      const response = await apiClient(config)
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Erro na requisição'
      throw err
    } finally {
      loading.value = false
    }
  }
  
  const get = (url, config = {}) => request({ method: 'GET', url, ...config })
  const post = (url, data, config = {}) => request({ method: 'POST', url, data, ...config })
  const put = (url, data, config = {}) => request({ method: 'PUT', url, data, ...config })
  const del = (url, config = {}) => request({ method: 'DELETE', url, ...config })
  
  return {
    loading: readonly(loading),
    error: readonly(error),
    request,
    get,
    post,
    put,
    delete: del,
  }
}
```

### **src/composables/useAuth.js**
```javascript
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

export function useAuth() {
  const authStore = useAuthStore()
  
  const isAuthenticated = computed(() => authStore.isAuthenticated)
  const user = computed(() => authStore.user)
  const token = computed(() => authStore.token)
  
  const login = async (credentials) => {
    return await authStore.login(credentials)
  }
  
  const register = async (userData) => {
    return await authStore.register(userData)
  }
  
  const logout = () => {
    authStore.logout()
  }
  
  const updateProfile = async (profileData) => {
    return await authStore.updateProfile(profileData)
  }
  
  return {
    isAuthenticated,
    user,
    token,
    login,
    register,
    logout,
    updateProfile,
  }
}
```

## 🏪 Stores Base (Pinia)

### **src/stores/auth.js**
```javascript
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
  
  const register = async (userData) => {
    try {
      const response = await apiClient.post(API_ENDPOINTS.AUTH.REGISTER, userData)
      
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
  
  const fetchProfile = async () => {
    try {
      const response = await apiClient.get(API_ENDPOINTS.AUTH.PROFILE)
      if (response.data.success) {
        user.value = response.data.data.user
      }
    } catch (error) {
      logout()
    }
  }
  
  return {
    user,
    token,
    isAuthenticated,
    login,
    register,
    logout,
    fetchProfile,
  }
})
```

## 🎨 Componente Base

### **src/components/shop/ProductCard.vue**
```vue
<template>
  <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
    <div class="aspect-square overflow-hidden">
      <img 
        :src="product.vinyl_data?.media?.[0]?.url || '/placeholder.jpg'"
        :alt="product.name"
        class="w-full h-full object-cover hover:scale-105 transition-transform"
      />
    </div>
    
    <div class="p-4">
      <h3 class="font-semibold text-gray-900 mb-2 line-clamp-2">
        {{ product.name }}
      </h3>
      
      <div class="text-sm text-gray-600 mb-2">
        <span v-if="product.artists?.length">
          {{ product.artists.map(a => a.name).join(', ') }}
        </span>
      </div>
      
      <div class="flex items-center justify-between">
        <span class="text-lg font-bold text-primary-600">
          {{ formatPrice(product.price) }}
        </span>
        
        <div class="flex gap-2">
          <button 
            @click="toggleWishlist"
            class="p-2 rounded-full hover:bg-gray-100"
            :class="{ 'text-red-500': inWishlist }"
          >
            <HeartIcon class="w-5 h-5" />
          </button>
          
          <button 
            @click="addToCart"
            :disabled="!product.in_stock"
            class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 disabled:opacity-50"
          >
            {{ product.in_stock ? 'Adicionar' : 'Esgotado' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { HeartIcon } from '@heroicons/vue/24/outline'
import { useCart } from '@/composables/useCart'
import { useWishlist } from '@/composables/useWishlist'
import { formatPrice } from '@/utils/formatters'

const props = defineProps({
  product: {
    type: Object,
    required: true
  }
})

const { addItem } = useCart()
const { addItem: addToWishlist, removeItem: removeFromWishlist, checkItem } = useWishlist()

const inWishlist = ref(false)

const addToCart = async () => {
  try {
    await addItem(props.product.id)
  } catch (error) {
    console.error('Erro ao adicionar ao carrinho:', error)
  }
}

const toggleWishlist = async () => {
  try {
    if (inWishlist.value) {
      await removeFromWishlist(props.product.id)
      inWishlist.value = false
    } else {
      await addToWishlist(props.product.id)
      inWishlist.value = true
    }
  } catch (error) {
    console.error('Erro ao alterar wishlist:', error)
  }
}

onMounted(async () => {
  try {
    inWishlist.value = await checkItem(props.product.id)
  } catch (error) {
    console.error('Erro ao verificar wishlist:', error)
  }
})
</script>
```

## 🚀 Próximos Passos

1. **Instalar dependências**: `npm install`
2. **Configurar variáveis de ambiente**: Criar `.env` com `VITE_API_URL`
3. **Implementar layouts**: Começar com `DefaultLayout.vue`
4. **Criar páginas principais**: Home, Products, ProductDetail
5. **Implementar autenticação**: Login/Register
6. **Adicionar carrinho e wishlist**: Funcionalidades básicas
7. **Integrar pagamentos**: MercadoPago
8. **Testes e otimizações**: Performance e UX

## 📚 Recursos Úteis

- [Vue 3 Documentation](https://vuejs.org/)
- [Pinia Documentation](https://pinia.vuejs.org/)
- [Tailwind CSS](https://tailwindcss.com/)
- [Headless UI](https://headlessui.com/)
- [VeeValidate](https://vee-validate.logaretm.com/v4/)

Esta estrutura fornece uma base sólida, escalável e bem organizada para o frontend Vue.js do seu e-commerce de discos de vinil!