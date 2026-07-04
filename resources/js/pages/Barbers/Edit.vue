<template>
  <AppLayout title="Editar barbeiro" show-back-button :back-href="`/barbers/${barberId}`">
    <div v-if="loading" class="p-4 space-y-3">
      <Skeleton height="60px" />
      <Skeleton height="60px" />
      <Skeleton height="60px" />
    </div>

    <form v-else class="space-y-6 p-4 pb-32 animate-enter" @submit.prevent="submit">
      <div class="flex flex-col items-center gap-2 pb-1">
        <AvatarUpload
          v-model="photoUrl"
          :name="form.name"
          :upload-url="`/api/barbers/${barberId}/photo`"
          :delete-url="`/api/barbers/${barberId}/photo`"
          field-name="photo"
          url-key="photo_url"
          :size="96"
        />
        <p class="text-[12px] text-[#6B6B6B]">Foto que aparece na equipe</p>
      </div>

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

        <label class="flex items-center gap-3 bg-[#131313] border border-[#2A2A2A] rounded-[10px] p-4 cursor-pointer">
          <input
            v-model="form.is_active"
            type="checkbox"
            class="w-5 h-5 accent-[#FFD60A]"
          />
          <div>
            <p class="text-[14px] font-medium text-white">Barbeiro ativo</p>
            <p class="text-[12px] text-[#A1A1A1] mt-0.5">Desative para esconder dos agendamentos</p>
          </div>
        </label>
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
          Excluir barbeiro
        </Button>
        <p v-if="deleteError" class="text-[12px] text-[#F59E0B] text-center">{{ deleteError }}</p>
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
import AvatarUpload from '../../components/AvatarUpload.vue'
import { useFormatting } from '../../composables/useFormatting'
import { useConfirm } from '../../composables/useConfirm'

const { formatPhone } = useFormatting()
const { ask } = useConfirm()
const page = usePage()
const barberId = page.url.split('/').filter(Boolean)[1]

const loading = ref(true)
const isLoading = ref(false)
const isDeleting = ref(false)
const deleteError = ref('')
const photoUrl = ref<string | null>(null)

const form = reactive({
  name: '',
  phone: '',
  default_commission_percentage: 15 as number | null,
  is_active: true,
})

const errors = reactive({
  name: '',
  phone: '',
  default_commission_percentage: '',
})

const xsrf = () =>
  decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

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

onMounted(async () => {
  try {
    const res = await fetch(`/api/barbers/${barberId}`, { headers: { Accept: 'application/json' } })
    if (res.ok) {
      const json = await res.json()
      const b = json.data ?? json
      form.name = b.name ?? ''
      form.phone = formatPhone(b.phone ?? '')
      form.default_commission_percentage = Number(b.default_commission_percentage ?? 15)
      form.is_active = !!b.is_active
      photoUrl.value = b.photo_url ?? null
    }
  } finally {
    loading.value = false
  }
})

const submit = async () => {
  errors.name = ''
  errors.phone = ''
  errors.default_commission_percentage = ''

  if (!form.name) errors.name = 'Nome é obrigatório'
  if (!form.phone) errors.phone = 'Telefone é obrigatório'
  if (errors.name || errors.phone) return

  isLoading.value = true
  try {
    const res = await fetch(`/api/barbers/${barberId}`, {
      method: 'PUT',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrf(),
      },
      body: JSON.stringify(form),
    })
    if (res.ok) {
      router.visit(`/barbers/${barberId}`)
    } else if (res.status === 422) {
      const data = await res.json()
      Object.assign(errors, data.errors || {})
    }
  } finally {
    isLoading.value = false
  }
}

const onDeleteClick = async () => {
  const ok = await ask(
    'Excluir barbeiro?',
    'Esta ação não pode ser desfeita. Os agendamentos passados serão mantidos.',
    { confirmText: 'Excluir', destructive: true }
  )
  if (!ok) return

  isDeleting.value = true
  try {
    const res = await fetch(`/api/barbers/${barberId}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrf(),
      },
    })
    if (res.ok || res.status === 204) {
      router.visit('/barbers')
    } else {
      const data = await res.json().catch(() => ({}))
      deleteError.value = data.message || 'Não foi possível excluir.'
    }
  } finally {
    isDeleting.value = false
  }
}
</script>
