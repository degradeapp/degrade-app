<script setup lang="ts">
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import { Eye, EyeOff, Loader2 } from 'lucide-vue-next'

const showPassword = ref(false)
const isLoading = ref(false)
const errorMessage = ref('')

const form = reactive({
  email: '',
  password: '',
})

const errors = reactive({
  email: '',
  password: '',
})

const submit = async () => {
  errorMessage.value = ''
  errors.email = ''
  errors.password = ''

  if (!form.email) {
    errors.email = 'Email obrigatório'
    return
  }

  if (!form.password) {
    errors.password = 'Senha obrigatória'
    return
  }

  isLoading.value = true

  router.post('/login', {
    email: form.email,
    password: form.password,
  }, {
    onError: (pageErrors: any) => {
      if (pageErrors.email) {
        errors.email = pageErrors.email
      }
      if (pageErrors.password) {
        errors.password = pageErrors.password
      }
      if (!errors.email && !errors.password) {
        errorMessage.value = 'Email ou senha incorretos'
      }
    },
    onFinish: () => {
      isLoading.value = false
    },
  })
}

const loginWithGoogle = () => {
  errorMessage.value = 'Login com Google disponível em breve'
}
</script>

<template>
  <div class="min-h-dvh w-full bg-[#0A0A0A] flex items-center justify-center px-5 py-6">
    <div class="w-full max-w-[400px]">

      <!-- Brand -->
      <div class="mb-8">
        <h1 class="text-[22px] font-bold tracking-tight text-white leading-none">
          Degradê
        </h1>
        <p class="text-[12px] text-[#6B6B6B] mt-1.5">
          Gestão para barbearias modernas
        </p>
      </div>

      <!-- Action heading -->
      <div class="mb-6">
        <h2 class="text-[20px] font-semibold text-white leading-tight">
          Bem-vindo de volta
        </h2>
        <p class="text-[13px] text-[#A1A1A1] mt-1">
          Acesse sua barbearia para continuar
        </p>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="space-y-3">

        <!-- Email -->
        <div class="relative">
          <input
            id="email"
            v-model="form.email"
            type="email"
            placeholder=" "
            autocomplete="email"
            class="peer block w-full h-12 px-4 pt-4 pb-1 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white outline-none transition-all duration-150 focus:border-[#FFD60A] focus:ring-2 focus:ring-[#FFD60A]/20 box-border"
          />
          <label
            for="email"
            class="absolute left-4 top-3.5 text-[14px] text-[#6B6B6B] transition-all duration-150 pointer-events-none peer-focus:top-1.5 peer-focus:text-[11px] peer-focus:text-[#FFD60A] peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:text-[#A1A1A1]"
          >
            Email
          </label>
          <div v-if="errors.email" class="flex items-center gap-1.5 mt-1.5">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" class="flex-shrink-0 text-[#EF4444]">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.75"/>
              <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
            </svg>
            <span class="text-[12px] text-[#EF4444]">{{ errors.email }}</span>
          </div>
        </div>

        <!-- Password -->
        <div class="relative">
          <input
            id="password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            placeholder=" "
            autocomplete="current-password"
            class="peer block w-full h-12 px-4 pt-4 pb-1 pr-12 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white outline-none transition-all duration-150 focus:border-[#FFD60A] focus:ring-2 focus:ring-[#FFD60A]/20 box-border"
          />
          <label
            for="password"
            class="absolute left-4 top-3.5 text-[14px] text-[#6B6B6B] transition-all duration-150 pointer-events-none peer-focus:top-1.5 peer-focus:text-[11px] peer-focus:text-[#FFD60A] peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:text-[#A1A1A1]"
          >
            Senha
          </label>
          <button
            type="button"
            @click="showPassword = !showPassword"
            class="absolute right-4 top-[22px] -translate-y-1/2 text-[#6B6B6B] hover:text-white transition-colors"
            :disabled="isLoading"
            aria-label="Mostrar/ocultar senha"
          >
            <Eye v-if="showPassword" :size="18" :stroke-width="1.75" />
            <EyeOff v-else :size="18" :stroke-width="1.75" />
          </button>
          <div v-if="errors.password" class="flex items-center gap-1.5 mt-1.5">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" class="flex-shrink-0 text-[#EF4444]">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.75"/>
              <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
            </svg>
            <span class="text-[12px] text-[#EF4444]">{{ errors.password }}</span>
          </div>
        </div>

        <!-- Forgot -->
        <div class="flex justify-end pt-0.5">
          <a href="/forgot-password" class="text-[12px] font-medium text-[#FFD60A] hover:text-[#FFE066] transition-colors">
            Esqueceu a senha?
          </a>
        </div>

        <!-- Error geral (credenciais inválidas) -->
        <div
          v-if="errorMessage"
          class="flex items-start gap-2 text-[12px] text-[#EF4444]"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" class="flex-shrink-0 mt-0.5">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.75"/>
            <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
          </svg>
          <span>{{ errorMessage }}</span>
        </div>

        <!-- Submit — Amarelo vivo sempre visível -->
        <button
          type="submit"
          :disabled="isLoading"
          class="w-full h-12 rounded-[10px] font-bold text-[15px] text-[#0A0A0A] flex items-center justify-center gap-2 transition-all duration-150 active:scale-[0.98] disabled:cursor-not-allowed mt-1
                 bg-[#FFD60A]
                 enabled:hover:bg-[#FFE066]
                 enabled:active:bg-[#F5C400]
                 enabled:shadow-[0_8px_24px_-8px_rgba(255,214,10,0.5),inset_0_1px_0_rgba(255,255,255,0.25)]
                 disabled:opacity-70"
        >
          <Loader2 v-if="isLoading" :size="18" class="animate-spin" />
          {{ isLoading ? 'Entrando...' : 'Entrar' }}
        </button>
      </form>

      <!-- Divider -->
      <div class="flex items-center gap-3 my-5">
        <div class="flex-1 h-px bg-[#1F1F1F]"></div>
        <span class="text-[10px] text-[#6B6B6B] uppercase tracking-[0.14em] font-medium">
          OU
        </span>
        <div class="flex-1 h-px bg-[#1F1F1F]"></div>
      </div>

      <!-- Google -->
      <button
        type="button"
        @click="loginWithGoogle"
        class="w-full h-12 bg-transparent border border-[#2A2A2A] rounded-[10px] text-[14px] font-medium text-[#A1A1A1] hover:text-white hover:border-[#3D3D3D] transition-colors flex items-center justify-center gap-2.5"
      >
        <svg width="16" height="16" viewBox="0 0 24 24">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        Continuar com Google
      </button>

      <!-- Footer -->
      <div class="mt-6 text-center">
        <span class="text-[12px] text-[#A1A1A1]">Ainda não tem conta? </span>
        <a href="/register" class="text-[12px] font-semibold text-[#FFD60A] hover:text-[#FFE066] transition-colors">
          Criar conta grátis
        </a>
      </div>

    </div>
  </div>
</template>
