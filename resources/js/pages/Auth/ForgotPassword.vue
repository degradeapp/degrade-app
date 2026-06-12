<template>
  <div class="min-h-dvh w-full bg-[#0A0A0A] flex flex-col items-center justify-center px-5">
    <div class="w-full max-w-[400px]">
      <div class="mb-8 text-center">
        <h1 class="text-[22px] font-bold tracking-tight text-white leading-none">Esqueceu a senha?</h1>
        <p class="text-[13px] text-[#A1A1A1] mt-2">
          Informe seu email e enviaremos um link para você redefinir.
        </p>
      </div>

      <div v-if="!sent" class="space-y-3">
        <div class="relative">
          <input
            id="email"
            v-model="email"
            type="email"
            placeholder=" "
            autocomplete="email"
            class="peer block w-full h-12 px-4 pt-4 pb-1 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white outline-none box-border transition-all duration-150 focus:border-[#FFD60A] focus:ring-2 focus:ring-[#FFD60A]/20"
          />
          <label
            for="email"
            class="absolute left-4 top-3.5 text-[14px] text-[#6B6B6B] transition-all duration-150 pointer-events-none peer-focus:top-1.5 peer-focus:text-[11px] peer-focus:text-[#FFD60A] peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:text-[#A1A1A1]"
          >
            Email
          </label>
        </div>

        <p v-if="error" class="text-[12px] text-[#EF4444] px-1">{{ error }}</p>

        <button
          type="button"
          :disabled="isLoading || !email"
          @click="submit"
          class="w-full h-12 rounded-[10px] font-bold text-[15px] text-[#0A0A0A] flex items-center justify-center gap-2 transition-all duration-150 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-70 bg-[#FFD60A] enabled:hover:bg-[#FFE066] enabled:active:bg-[#F5C400] enabled:shadow-[0_8px_24px_-8px_rgba(255,214,10,0.5),inset_0_1px_0_rgba(255,255,255,0.25)]"
        >
          <Loader2 v-if="isLoading" :size="18" class="animate-spin" />
          {{ isLoading ? 'Enviando...' : 'Enviar link' }}
        </button>

        <Link
          href="/login"
          class="block w-full text-center h-12 leading-[3rem] text-[14px] font-medium text-[#A1A1A1] hover:text-white transition-colors"
        >
          Voltar para o login
        </Link>
      </div>

      <div v-else class="space-y-4 text-center">
        <div class="w-16 h-16 mx-auto rounded-full bg-[#22C55E]/15 flex items-center justify-center">
          <Check :size="32" :stroke-width="2.5" class="text-[#22C55E]" />
        </div>
        <h2 class="text-[18px] font-semibold text-white">Link enviado</h2>
        <p class="text-[13px] text-[#A1A1A1] leading-relaxed">
          Se o email <span class="text-white">{{ email }}</span> estiver cadastrado, você receberá um link para redefinir a senha.
          Verifique também a caixa de spam.
        </p>
        <Link
          href="/login"
          class="block w-full h-12 leading-[3rem] text-[14px] font-medium text-[#FFD60A] hover:text-[#FFE066] transition-colors"
        >
          Voltar para o login
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Loader2, Check } from 'lucide-vue-next'

const email = ref('')
const isLoading = ref(false)
const sent = ref(false)
const error = ref('')

const submit = async () => {
  error.value = ''
  isLoading.value = true
  try {
    const res = await fetch('/api/auth/forgot-password', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
      },
      body: JSON.stringify({ email: email.value }),
    })

    if (res.ok) {
      sent.value = true
      return
    }

    if (res.status === 422) {
      const body = await res.json().catch(() => ({}))
      error.value = body?.errors?.email?.[0] ?? body?.message ?? 'Email inválido.'
      return
    }
    error.value = `Erro ${res.status}. Tente novamente.`
  } catch {
    error.value = 'Falha de rede. Tente novamente.'
  } finally {
    isLoading.value = false
  }
}
</script>
