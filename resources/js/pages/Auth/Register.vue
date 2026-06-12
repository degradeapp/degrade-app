<script setup lang="ts">
import { ref, reactive } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { Eye, EyeOff, Loader2, Check } from 'lucide-vue-next'

const showPassword = ref(false)
const showPasswordConfirm = ref(false)
const isLoading = ref(false)
const errorMessage = ref('')

const form = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  passwordConfirmation: '',
})

const errors = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  passwordConfirmation: '',
})

const formatPhone = (value: string) => {
  const digits = value.replace(/\D/g, '').slice(0, 11)
  if (digits.length <= 2) return digits
  if (digits.length <= 7) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`
  if (digits.length <= 10) return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`
  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`
}

const onPhoneInput = (e: Event) => {
  const el = e.target as HTMLInputElement
  form.phone = formatPhone(el.value)
  // Força o DOM: sem isso, se o valor formatado não muda, o Vue não repinta o
  // input e os dígitos extras digitados continuam visíveis.
  el.value = form.phone
}

const validate = (): boolean => {
  let ok = true
  Object.keys(errors).forEach(k => errors[k as keyof typeof errors] = '')

  if (!form.name.trim()) { errors.name = 'Seu nome é obrigatório'; ok = false }
  if (!form.email) { errors.email = 'Email obrigatório'; ok = false }
  else if (!form.email.includes('@')) { errors.email = 'Email inválido'; ok = false }
  if (!form.phone) { errors.phone = 'Telefone obrigatório'; ok = false }
  if (!form.password) { errors.password = 'Senha obrigatória'; ok = false }
  else if (form.password.length < 8) { errors.password = 'Mínimo 8 caracteres'; ok = false }
  if (form.password !== form.passwordConfirmation) {
    errors.passwordConfirmation = 'As senhas não coincidem'; ok = false
  }
  return ok
}

const submit = () => {
  if (!validate()) return
  isLoading.value = true
  errorMessage.value = ''

  router.post('/register', {
    name: form.name,
    email: form.email,
    phone: form.phone,
    password: form.password,
    password_confirmation: form.passwordConfirmation,
  }, {
    onError: (pageErrors: any) => {
      if (pageErrors.name) errors.name = pageErrors.name
      if (pageErrors.email) errors.email = pageErrors.email
      if (pageErrors.phone) errors.phone = pageErrors.phone
      if (pageErrors.password) errors.password = pageErrors.password
      if (!Object.values(errors).some(e => e)) {
        errorMessage.value = 'Erro ao criar conta. Tente novamente.'
      }
    },
    onFinish: () => { isLoading.value = false },
  })
}
</script>

<template>
  <div class="min-h-dvh w-full bg-[#0A0A0A] flex items-center justify-center px-5 py-8">
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
          Crie sua conta
        </h2>
        <p class="text-[13px] text-[#A1A1A1] mt-1">
          Agenda, clientes e comissões da sua barbearia num só lugar.
        </p>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="space-y-3">

        <!-- Seu nome (dono) -->
        <div class="relative">
          <input
            id="name"
            v-model="form.name"
            type="text"
            placeholder=" "
            autocomplete="name"
            maxlength="100"
            class="peer block w-full h-12 px-4 pt-4 pb-1 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white outline-none transition-all duration-150 focus:border-[#FFD60A] focus:ring-2 focus:ring-[#FFD60A]/20 box-border"
          />
          <label for="name"
                 class="absolute left-4 top-3.5 text-[14px] text-[#6B6B6B] transition-all duration-150 pointer-events-none peer-focus:top-1.5 peer-focus:text-[11px] peer-focus:text-[#FFD60A] peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:text-[#A1A1A1]">
            Seu nome
          </label>
          <div v-if="errors.name" class="flex items-center gap-1.5 mt-1.5">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" class="flex-shrink-0 text-[#EF4444]">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.75"/>
              <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
            </svg>
            <span class="text-[12px] text-[#EF4444]">{{ errors.name }}</span>
          </div>
        </div>

        <!-- Email -->
        <div class="relative">
          <input
            id="email"
            v-model="form.email"
            type="email"
            placeholder=" "
            autocomplete="email"
            maxlength="150"
            class="peer block w-full h-12 px-4 pt-4 pb-1 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white outline-none transition-all duration-150 focus:border-[#FFD60A] focus:ring-2 focus:ring-[#FFD60A]/20 box-border"
          />
          <label for="email"
                 class="absolute left-4 top-3.5 text-[14px] text-[#6B6B6B] transition-all duration-150 pointer-events-none peer-focus:top-1.5 peer-focus:text-[11px] peer-focus:text-[#FFD60A] peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:text-[#A1A1A1]">
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

        <!-- Telefone -->
        <div class="relative">
          <input
            id="phone"
            :value="form.phone"
            @input="onPhoneInput"
            type="tel"
            placeholder=" "
            inputmode="numeric"
            maxlength="15"
            autocomplete="tel"
            class="peer block w-full h-12 px-4 pt-4 pb-1 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white outline-none transition-all duration-150 focus:border-[#FFD60A] focus:ring-2 focus:ring-[#FFD60A]/20 box-border tabular-nums"
          />
          <label for="phone"
                 class="absolute left-4 top-3.5 text-[14px] text-[#6B6B6B] transition-all duration-150 pointer-events-none peer-focus:top-1.5 peer-focus:text-[11px] peer-focus:text-[#FFD60A] peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:text-[#A1A1A1]">
            Telefone
          </label>
          <div v-if="errors.phone" class="flex items-center gap-1.5 mt-1.5">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" class="flex-shrink-0 text-[#EF4444]">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.75"/>
              <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
            </svg>
            <span class="text-[12px] text-[#EF4444]">{{ errors.phone }}</span>
          </div>
        </div>

        <!-- Senha -->
        <div class="relative">
          <input
            id="password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            placeholder=" "
            autocomplete="new-password"
            maxlength="72"
            class="peer block w-full h-12 px-4 pt-4 pb-1 pr-12 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white outline-none transition-all duration-150 focus:border-[#FFD60A] focus:ring-2 focus:ring-[#FFD60A]/20 box-border"
          />
          <label for="password"
                 class="absolute left-4 top-3.5 text-[14px] text-[#6B6B6B] transition-all duration-150 pointer-events-none peer-focus:top-1.5 peer-focus:text-[11px] peer-focus:text-[#FFD60A] peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:text-[#A1A1A1]">
            Senha
          </label>
          <button type="button" @click="showPassword = !showPassword"
                  class="absolute right-4 top-[22px] -translate-y-1/2 text-[#6B6B6B] hover:text-white transition-colors"
                  :disabled="isLoading" aria-label="Mostrar/ocultar senha">
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
          <p v-else class="text-[11px] text-[#6B6B6B] mt-1.5 ml-1">Mínimo 8 caracteres</p>
        </div>

        <!-- Confirmar senha -->
        <div class="relative">
          <input
            id="passwordConfirmation"
            v-model="form.passwordConfirmation"
            :type="showPasswordConfirm ? 'text' : 'password'"
            placeholder=" "
            autocomplete="new-password"
            maxlength="72"
            class="peer block w-full h-12 px-4 pt-4 pb-1 pr-12 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white outline-none transition-all duration-150 focus:border-[#FFD60A] focus:ring-2 focus:ring-[#FFD60A]/20 box-border"
          />
          <label for="passwordConfirmation"
                 class="absolute left-4 top-3.5 text-[14px] text-[#6B6B6B] transition-all duration-150 pointer-events-none peer-focus:top-1.5 peer-focus:text-[11px] peer-focus:text-[#FFD60A] peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:text-[#A1A1A1]">
            Confirme a senha
          </label>
          <button type="button" @click="showPasswordConfirm = !showPasswordConfirm"
                  class="absolute right-4 top-[22px] -translate-y-1/2 text-[#6B6B6B] hover:text-white transition-colors"
                  :disabled="isLoading" aria-label="Mostrar/ocultar senha">
            <Eye v-if="showPasswordConfirm" :size="18" :stroke-width="1.75" />
            <EyeOff v-else :size="18" :stroke-width="1.75" />
          </button>
          <div v-if="errors.passwordConfirmation" class="flex items-center gap-1.5 mt-1.5">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" class="flex-shrink-0 text-[#EF4444]">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.75"/>
              <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
            </svg>
            <span class="text-[12px] text-[#EF4444]">{{ errors.passwordConfirmation }}</span>
          </div>
        </div>

        <!-- Trial info (flat, sem gradient) -->
        <div class="flex items-start gap-2.5 bg-[#FFD60A]/[0.06] border border-[#FFD60A]/20 rounded-[10px] px-3.5 py-3 mt-1">
          <Check :size="16" :stroke-width="2.25" class="text-[#FFD60A] flex-shrink-0 mt-0.5" />
          <div class="leading-relaxed">
            <p class="text-[13px] font-semibold text-white">14 dias grátis</p>
            <p class="text-[12px] text-[#A1A1A1] mt-0.5">Sem cartão. Cancele quando quiser.</p>
          </div>
        </div>

        <!-- Error geral -->
        <div v-if="errorMessage" class="flex items-start gap-2 text-[12px] text-[#EF4444]">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" class="flex-shrink-0 mt-0.5">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.75"/>
            <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
          </svg>
          <span>{{ errorMessage }}</span>
        </div>

        <!-- Submit -->
        <button
          type="submit"
          :disabled="isLoading"
          class="w-full h-12 rounded-[10px] font-bold text-[15px] text-[#0A0A0A] flex items-center justify-center gap-2 transition-all duration-150 active:scale-[0.98] disabled:cursor-not-allowed mt-2
                 bg-[#FFD60A]
                 enabled:hover:bg-[#FFE066]
                 enabled:active:bg-[#F5C400]
                 enabled:shadow-[0_8px_24px_-8px_rgba(255,214,10,0.5),inset_0_1px_0_rgba(255,255,255,0.25)]
                 disabled:opacity-70"
        >
          <Loader2 v-if="isLoading" :size="18" class="animate-spin" />
          {{ isLoading ? 'Criando conta...' : 'Criar conta grátis' }}
        </button>

        <!-- Termos -->
        <p class="text-[11px] text-[#6B6B6B] text-center leading-relaxed mt-1">
          Ao se cadastrar, você concorda com os
          <a href="/terms" target="_blank" rel="noopener" class="text-[#A1A1A1] hover:text-white underline underline-offset-2 transition-colors">Termos de Serviço</a>
          e
          <a href="/privacy" target="_blank" rel="noopener" class="text-[#A1A1A1] hover:text-white underline underline-offset-2 transition-colors">Política de Privacidade</a>.
        </p>
      </form>

      <!-- Footer -->
      <div class="mt-6 text-center">
        <span class="text-[12px] text-[#A1A1A1]">Já tem conta? </span>
        <Link href="/login" class="text-[12px] font-semibold text-[#FFD60A] hover:text-[#FFE066] transition-colors">
          Fazer login
        </Link>
      </div>

    </div>
  </div>
</template>
