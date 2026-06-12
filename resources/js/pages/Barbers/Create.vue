<template>
  <AppLayout title="Novo barbeiro" show-back-button>
    <form class="space-y-6 p-4 pb-24 animate-enter" @submit.prevent="submit">
      <div class="space-y-4">
        <FormField
          v-model="form.name"
          type="text"
          label="Nome"
          :maxlength="100"
          placeholder=" "
          required
          :error="errors.name"
        />

        <FormField
          v-model="form.phone"
          type="tel"
          label="Telefone"
          placeholder=" "
          required
          :error="errors.phone"
          @input="formatPhoneInput"
        />

        <FormField
          v-model.number="form.default_commission_percentage"
          type="number"
          label="Comissão padrão (%)"
          placeholder=" "
          :error="errors.default_commission_percentage"
          @input="onCommissionInput"
        />

        <!-- Unidade: só aparece em rede com mais de uma unidade. -->
        <SelectField
          v-if="showUnitField"
          v-model="form.unit_id"
          :options="unitOptions"
          title="Unidade"
        />
      </div>

      <!-- Submit button -->
      <div class="fixed bottom-0 left-0 right-0 bg-bg-base border-t border-border-subtle p-4">
        <Button
          type="submit"
          variant="primary"
          class="w-full"
          :loading="isLoading"
          loading-text="Salvando..."
        >
          Salvar barbeiro
        </Button>
      </div>
    </form>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'
import FormField from '../../components/FormField.vue'
import SelectField from '../../components/SelectField.vue'
import Button from '../../components/Button.vue'
import { useApi } from '../../composables/useApi'
import { useToast } from '../../composables/useToast'

const api = useApi()
const toast = useToast()
const page = usePage()

// Unidades da rede (vêm compartilhadas via Inertia). Só mostra o seletor se houver > 1.
const units = computed(() => (page.props as any).units ?? null)
const showUnitField = computed(() => (units.value?.list?.length ?? 0) > 1)
const unitOptions = computed(() => (units.value?.list ?? []).map((u: any) => ({ value: u.id, label: u.name })))

const isLoading = ref(false)
const form = reactive({
  name: '',
  phone: '',
  default_commission_percentage: null as number | null,
  // Pré-seleciona a unidade ativa (ou a 1ª); o barbeiro nasce nessa unidade.
  unit_id: (units.value?.active_id ?? units.value?.list?.[0]?.id ?? null) as number | null,
})

const errors = reactive({
  name: '',
  phone: '',
  default_commission_percentage: '',
})

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

// Comissão: máximo 3 dígitos (0 a 999)
const onCommissionInput = (e: Event) => {
  const el = e.target as HTMLInputElement
  const v = el.value.replace(/\D/g, '').slice(0, 3)
  form.default_commission_percentage = v === '' ? null : Number(v)
  el.value = v
}

const validateForm = () => {
  let isValid = true
  errors.name = ''
  errors.phone = ''
  errors.default_commission_percentage = ''

  if (!form.name) {
    errors.name = 'Nome é obrigatório'
    isValid = false
  }
  if (!form.phone) {
    errors.phone = 'Telefone é obrigatório'
    isValid = false
  }
  const commission = form.default_commission_percentage
  if (commission === null || (commission as unknown) === '') {
    errors.default_commission_percentage = 'Comissão é obrigatória'
    isValid = false
  } else if (commission < 0 || commission > 100) {
    errors.default_commission_percentage = 'Comissão deve ser entre 0 e 100'
    isValid = false
  }

  return isValid
}

const submit = async () => {
  if (!validateForm()) return

  isLoading.value = true
  try {
    const res = await api.post('/api/barbers', form)
    if (res.ok) {
      router.visit('/barbers')
    } else if (res.status === 422 && res.errors) {
      for (const [k, v] of Object.entries(res.errors)) (errors as any)[k] = v[0] ?? ''
    } else {
      toast.error(res.message ?? `Erro ${res.status}.`)
    }
  } finally {
    isLoading.value = false
  }
}
</script>
