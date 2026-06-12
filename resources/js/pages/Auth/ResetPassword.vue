<template>
  <div class="min-h-dvh w-full bg-[#0A0A0A] flex flex-col items-center justify-center px-5">
    <div class="w-full max-w-[400px]">
      <div class="mb-8 text-center">
        <h1 class="text-[22px] font-bold tracking-tight text-white leading-none">Nova senha</h1>
        <p class="text-[13px] text-[#A1A1A1] mt-2">
          Defina sua nova senha para <span class="text-white">{{ email }}</span>.
        </p>
      </div>

      <div v-if="!done" class="space-y-3">
        <div class="relative">
          <input
            id="password"
            v-model="password"
            type="password"
            placeholder=" "
            autocomplete="new-password"
            class="peer block w-full h-12 px-4 pt-4 pb-1 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white outline-none focus:border-[#FFD60A] focus:ring-2 focus:ring-[#FFD60A]/20"
          />
          <label
            for="password"
            class="absolute left-4 top-3.5 text-[14px] text-[#6B6B6B] transition-all duration-150 pointer-events-none peer-focus:top-1.5 peer-focus:text-[11px] peer-focus:text-[#FFD60A] peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:text-[#A1A1A1]"
          >
            Nova senha (mín. 8)
          </label>
        </div>

        <div class="relative">
          <input
            id="password_confirmation"
            v-model="passwordConfirmation"
            type="password"
            placeholder=" "
            autocomplete="new-password"
            class="peer block w-full h-12 px-4 pt-4 pb-1 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white outline-none focus:border-[#FFD60A] focus:ring-2 focus:ring-[#FFD60A]/20"
          />
          <label
            for="password_confirmation"
            class="absolute left-4 top-3.5 text-[14px] text-[#6B6B6B] transition-all duration-150 pointer-events-none peer-focus:top-1.5 peer-focus:text-[11px] peer-focus:text-[#FFD60A] peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:text-[#A1A1A1]"
          >
            Confirmar nova senha
          </label>
        </div>

        <p v-if="error" class="text-[12px] text-[#EF4444] px-1">{{ error }}</p>

        <button
          type="button"
          :disabled="isLoading || !valid"
          @click="submit"
          class="w-full h-12 rounded-[10px] font-bold text-[15px] text-[#0A0A0A] flex items-center justify-center gap-2 transition-all duration-150 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-70 bg-[#FFD60A] enabled:hover:bg-[#FFE066] enabled:active:bg-[#F5C400]"
        >
          <Loader2 v-if="isLoading" :size="18" class="animate-spin" />
          {{ isLoading ? 'Salvando...' : 'Definir nova senha' }}
        </button>
      </div>

      <div v-else class="space-y-4 text-center">
        <div class="w-16 h-16 mx-auto rounded-full bg-[#22C55E]/15 flex items-center justify-center">
          <Check :size="32" :stroke-width="2.5" class="text-[#22C55E]" />
        </div>
        <h2 class="text-[18px] font-semibold text-white">Senha redefinida</h2>
        <p class="text-[13px] text-[#A1A1A1] leading-relaxed">
          Você já pode entrar com a nova senha.
        </p>
        <Link
          href="/login"
          class="block w-full h-12 leading-[3rem] text-[14px] font-medium text-[#FFD60A] hover:text-[#FFE066]"
        >
          Ir para o login
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Loader2, Check } from 'lucide-vue-next'

const props = defineProps<{ token?: string; email?: string }>()

const password = ref('')
const passwordConfirmation = ref('')
const isLoading = ref(false)
const error = ref('')
const done = ref(false)

const email = ref(props.email ?? '')
const token = ref(props.token ?? '')

onMounted(() => {
  const url = new URL(window.location.href)
  if (!email.value) email.value = url.searchParams.get('email') ?? ''
  if (!token.value) token.value = url.searchParams.get('token') ?? ''
})

const valid = computed(
  () => password.value.length >= 8 && password.value === passwordConfirmation.value && email.value && token.value
)

const submit = async () => {
  error.value = ''
  isLoading.value = true
  try {
    const res = await fetch('/api/auth/reset-password', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
      },
      body: JSON.stringify({
        email: email.value,
        token: token.value,
        password: password.value,
        password_confirmation: passwordConfirmation.value,
      }),
    })

    if (res.ok) {
      done.value = true
      return
    }

    if (res.status === 422) {
      const body = await res.json().catch(() => ({}))
      error.value = Object.values(body?.errors ?? {}).flat()[0] as string ?? body?.message ?? 'Não foi possível redefinir.'
      return
    }
    error.value = `Erro ${res.status}.`
  } catch {
    error.value = 'Falha de rede. Tente novamente.'
  } finally {
    isLoading.value = false
  }
}
</script>
