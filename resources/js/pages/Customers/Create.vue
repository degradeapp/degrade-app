<template>
  <AppLayout title="Novo cliente" show-back-button>
    <form class="space-y-6 p-4 pb-32 animate-enter" @submit.prevent="submit">
      <div class="space-y-4">
        <FormField
          v-model="form.name"
          type="text"
          label="Nome"
          :maxlength="150"
          placeholder=" "
          required
          :error="errors.name"
        />

        <FormField
          v-model="form.phone"
          type="tel"
          label="Telefone (opcional)"
          placeholder=" "
          :error="errors.phone"
          @input="formatPhoneInput"
        />

        <FormField
          v-model="form.email"
          type="email"
          label="Email (opcional)"
          :maxlength="150"
          placeholder=" "
          :error="errors.email"
        />

        <div class="relative">
          <textarea
            id="notes"
            v-model="form.notes"
            placeholder=" "
            rows="3"
            maxlength="200"
            class="peer block w-full min-h-[88px] px-4 pt-5 pb-2 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white outline-none resize-none transition-all duration-150 focus:border-[#FFD60A] focus:ring-2 focus:ring-[#FFD60A]/20 box-border"
          ></textarea>
          <label
            for="notes"
            class="absolute left-4 top-4 text-[14px] text-[#6B6B6B] transition-all duration-150 pointer-events-none peer-focus:top-2 peer-focus:text-[11px] peer-focus:text-[#FFD60A] peer-[:not(:placeholder-shown)]:top-2 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:text-[#A1A1A1]"
          >
            Observações (preferências, alergias, etc.)
          </label>
        </div>
      </div>

      <p v-if="errors.general" class="text-center text-[12px] text-[#EF4444]">{{ errors.general }}</p>

      <div class="fixed bottom-0 left-0 right-0 bg-[#0A0A0A] border-t border-[#1F1F1F] p-4">
        <Button type="submit" variant="primary" class="w-full" :loading="isLoading" loading-text="Salvando...">
          Salvar cliente
        </Button>
      </div>
    </form>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'
import FormField from '../../components/FormField.vue'
import Button from '../../components/Button.vue'
import { useApi } from '../../composables/useApi'

const api = useApi()
const isLoading = ref(false)

const form = reactive({
  name: '',
  phone: '',
  email: '',
  notes: '',
})

const errors = reactive<Record<string, string>>({})

const formatPhoneInput = (e: Event) => {
  const input = e.target as HTMLInputElement
  let value = input.value.replace(/\D/g, '')
  if (value.length > 11) value = value.slice(0, 11)
  if (value.length >= 2) value = `(${value.slice(0, 2)}) ${value.slice(2)}`
  if (value.length > 9) {
    const beforeDash = value.slice(0, -4)
    const afterDash = value.slice(-4)
    value = `${beforeDash}-${afterDash}`
  }
  form.phone = value
  input.value = value
}

const validate = () => {
  Object.keys(errors).forEach((k) => delete errors[k])
  if (!form.name) errors.name = 'Nome é obrigatório'
  if (form.email && !form.email.includes('@')) errors.email = 'Email inválido'
  return !errors.name && !errors.email
}

const submit = async () => {
  if (!validate()) return

  isLoading.value = true
  try {
    const res = await api.post('/api/customers', form)
    if (res.ok) {
      router.visit('/customers')
    } else if (res.status === 422 && res.errors) {
      for (const [k, v] of Object.entries(res.errors)) errors[k] = v[0] ?? ''
    } else {
      errors.general = res.message ?? `Erro ${res.status}.`
    }
  } finally {
    isLoading.value = false
  }
}
</script>
