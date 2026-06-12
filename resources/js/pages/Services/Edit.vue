<template>
  <AppLayout title="Editar serviço" show-back-button back-href="/services">
    <div v-if="loading" class="p-4">
      <Skeleton height="60px" class="mb-3" />
      <Skeleton height="60px" class="mb-3" />
      <Skeleton height="60px" class="mb-3" />
      <Skeleton height="60px" />
    </div>

    <form v-else class="space-y-6 p-4 pb-24 animate-enter" @submit.prevent="submit">
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

      <div class="fixed bottom-0 left-0 right-0 bg-bg-base border-t border-border-subtle p-4 flex flex-col gap-3">
        <Button type="submit" variant="primary" class="w-full" :loading="isLoading" loading-text="Salvando...">
          Salvar alterações
        </Button>
        <Button
          type="button"
          variant="danger"
          class="w-full"
          :loading="isDeleting"
          loading-text="Excluindo..."
          @click="onDeleteClick"
        >
          Excluir serviço
        </Button>
      </div>
    </form>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'
import FormField from '../../components/FormField.vue'
import Button from '../../components/Button.vue'
import Skeleton from '../../components/Skeleton.vue'
import { capPrice } from '@/data/numericInput'
import { useApi } from '../../composables/useApi'
import { useConfirm } from '../../composables/useConfirm'

const api = useApi()
const { ask } = useConfirm()
const page = usePage()
const serviceId = page.url.split('/').filter(Boolean)[1]

const loading = ref(true)
const isLoading = ref(false)
const isDeleting = ref(false)

const form = reactive({
  name: '',
  price: null as number | null,
  commission_percentage: null as number | null,
})

const errors = reactive({
  name: '',
  price: '',
  commission_percentage: '',
})

const clearErrors = () => {
  errors.name = ''
  errors.price = ''
  errors.commission_percentage = ''
}

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

onMounted(async () => {
  try {
    const res = await api.get(`/api/services/${serviceId}`)
    if (res.ok && res.data) {
      const s: any = res.data
      form.name = s.name ?? ''
      form.price = s.price != null ? Number(s.price) : null
      form.commission_percentage = s.commission_percentage != null ? Number(s.commission_percentage) : null
    }
  } finally {
    loading.value = false
  }
})

const submit = async () => {
  clearErrors()
  if (!form.name) errors.name = 'Obrigatório'
  if (!form.price) errors.price = 'Obrigatório'
  if (errors.name || errors.price) return

  isLoading.value = true
  try {
    const res = await api.put(`/api/services/${serviceId}`, form)
    if (res.ok) {
      router.visit('/services')
    } else if (res.status === 422 && res.errors) {
      for (const [k, v] of Object.entries(res.errors)) (errors as any)[k] = v[0] ?? ''
    }
  } finally {
    isLoading.value = false
  }
}

const onDeleteClick = async () => {
  const ok = await ask(
    'Excluir serviço?',
    'Esta ação não pode ser desfeita. Os agendamentos existentes não serão afetados.',
    { confirmText: 'Excluir', destructive: true }
  )
  if (!ok) return

  isDeleting.value = true
  try {
    const res = await api.delete(`/api/services/${serviceId}`)
    if (res.ok || res.status === 204) {
      router.visit('/services')
    }
  } finally {
    isDeleting.value = false
  }
}
</script>
