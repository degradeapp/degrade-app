<template>
  <AppLayout title="Barbearia" show-back-button>
    <form class="space-y-6 p-4 pb-32 animate-enter" @submit.prevent="submit">
      <div class="flex flex-col items-center gap-2 pb-1">
        <AvatarUpload
          v-model="logoUrl"
          :name="form.name"
          upload-url="/api/tenant/logo"
          delete-url="/api/tenant/logo"
          field-name="logo"
          url-key="logo_url"
          shape="square"
          :size="96"
        />
        <p class="text-[12px] text-[#6B6B6B]">Logo da barbearia</p>
      </div>

      <div class="space-y-4">
        <FormField
          v-model="form.name"
          type="text"
          label="Nome da barbearia"
          :maxlength="100"
          placeholder=" "
          required
          :error="errors.name"
        />

        <SelectField v-model="form.timezone" :options="BR_TIMEZONES" label="Fuso horário" title="Fuso horário" />

        <FormField
          v-model.number="form.cancellation_policy_hours"
          type="number"
          label="Janela de cancelamento (horas)"
          placeholder=" "
          hint="Quantas horas antes o cliente pode cancelar"
          :error="errors.cancellation_policy_hours"
        />

        <FormField
          v-model.number="form.default_commission_percentage"
          type="number"
          label="Comissão padrão (%)"
          placeholder=" "
          hint="Usada quando serviço/barbeiro não tiver comissão própria"
          :error="errors.default_commission_percentage"
        />
      </div>

      <div class="fixed bottom-0 left-0 right-0 bg-[#0A0A0A] border-t border-[#1F1F1F] p-4">
        <Button type="submit" variant="primary" class="w-full" :loading="isLoading" loading-text="Salvando...">
          Salvar configurações
        </Button>
      </div>
    </form>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import AppLayout from '../../layouts/AppLayout.vue'
import FormField from '../../components/FormField.vue'
import Button from '../../components/Button.vue'
import SelectField from '../../components/SelectField.vue'
import AvatarUpload from '../../components/AvatarUpload.vue'
import { useToast } from '../../composables/useToast'
import { BR_TIMEZONES } from '@/data/timezones'

const toast = useToast()
const isLoading = ref(false)
const logoUrl = ref<string | null>(null)

const form = reactive({
  name: '',
  timezone: 'America/Manaus',
  cancellation_policy_hours: 24,
  default_commission_percentage: 15,
})

const errors = reactive<Record<string, string>>({})

const xsrf = () =>
  decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

const submit = async () => {
  Object.keys(errors).forEach((k) => delete errors[k])

  if (!form.name) {
    errors.name = 'Obrigatório'
    return
  }

  isLoading.value = true
  try {
    const res = await fetch('/api/tenant/settings', {
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
      toast.success('Configurações salvas')
    } else if (res.status === 422) {
      const data = await res.json()
      Object.assign(errors, Object.fromEntries(Object.entries(data.errors ?? {}).map(([k, v]: any) => [k, v[0]])))
    }
  } finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  try {
    const res = await fetch('/api/tenant/settings', { headers: { Accept: 'application/json' } })
    if (res.ok) {
      const json = await res.json()
      const data = json.data ?? {}
      form.name = data.name || ''
      form.timezone = data.timezone || 'America/Manaus'
      form.cancellation_policy_hours = data.cancellation_policy_hours ?? 24
      form.default_commission_percentage = data.default_commission_percentage ?? 15
      logoUrl.value = data.logo_url ?? null
    }
  } catch (e) {
    console.error('Error:', e)
  }
})
</script>
