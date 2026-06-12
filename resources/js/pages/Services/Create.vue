<template>
  <AppLayout title="Novo serviço" show-back-button>
    <form class="space-y-6 p-4 pb-24 animate-enter" @submit.prevent="submit">
      <div class="space-y-4">
        <FormField
          v-model="form.name"
          type="text"
          label="Nome do serviço"
          :maxlength="80"
          placeholder=" "
          required
          :error="errors.name"
        />

        <FormField
          v-model.number="form.price"
          type="number"
          label="Preço"
          placeholder=" "
          hint="R$"
          required
          :error="errors.price"
          step="0.01"
          @input="onPriceInput"
        />

        <FormField
          v-model.number="form.commission_percentage"
          type="number"
          label="Comissão (%) (opcional)"
          placeholder=" "
          :error="errors.commission_percentage"
          @input="onCommissionInput"
        />
      </div>

      <div class="fixed bottom-0 left-0 right-0 bg-bg-base border-t border-border-subtle p-4">
        <Button type="submit" variant="primary" class="w-full" :loading="isLoading" loading-text="Salvando...">
          Salvar serviço
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
import { capPrice } from '@/data/numericInput'

const api = useApi()
const isLoading = ref(false)

const form = reactive({
  name: '',
  price: null as number | null,
  commission_percentage: null as number | null,
})

const errors = reactive<Record<string, string>>({})

// Comissão: máximo 3 dígitos (0 a 999)
const onCommissionInput = (e: Event) => {
  const el = e.target as HTMLInputElement
  const v = el.value.replace(/\D/g, '').slice(0, 3)
  form.commission_percentage = v === '' ? null : Number(v)
  el.value = v
}

// Preço: até 6 dígitos + 2 decimais
const onPriceInput = (e: Event) => {
  const el = e.target as HTMLInputElement
  const v = capPrice(el.value)
  form.price = v === '' || v === '.' ? null : Number(v)
  if (v !== el.value && !v.endsWith('.')) el.value = v
}

const submit = async () => {
  Object.keys(errors).forEach((k) => delete errors[k])

  if (!form.name) errors.name = 'Obrigatório'
  if (!form.price) errors.price = 'Obrigatório'
  if (errors.name || errors.price) return

  isLoading.value = true
  try {
    const res = await api.post('/api/services', form)
    if (res.ok) {
      router.visit('/services')
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
