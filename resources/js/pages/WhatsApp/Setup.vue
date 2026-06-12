<template>
  <AppLayout title="Configurar WhatsApp" show-back-button>
    <div v-if="loading" class="p-4 space-y-3">
      <Skeleton height="60px" />
      <Skeleton height="60px" />
      <Skeleton height="60px" />
    </div>

    <form v-else class="p-4 pb-32 space-y-5" @submit.prevent="save">
      <p class="text-[13px] text-[#A1A1A1] leading-relaxed">
        Cole abaixo o <span class="text-white">Phone Number ID</span> e o <span class="text-white">Access Token</span>
        que você gerou no Meta Developers (Cloud API).
      </p>

      <div class="space-y-3">
        <FormField
          v-model="form.phone_number_id"
          type="text"
          label="Phone Number ID"
          placeholder=" "
          required
          :error="errors.phone_number_id"
        />

        <FormField
          v-model="form.access_token"
          type="password"
          label="Access Token"
          placeholder=" "
          required
          :error="errors.access_token"
        />
      </div>

      <div v-if="existingAccount" class="bg-[#131313] border border-[#2A2A2A] rounded-[10px] p-3">
        <p class="text-[11px] uppercase tracking-[0.08em] text-[#6B6B6B] mb-1">Status atual</p>
        <p class="text-[14px] text-white">
          {{ existingAccount.is_active ? '✅ Ativo' : '⏸ Inativo' }} · ID {{ existingAccount.phone_number_id }}
        </p>
      </div>

      <p v-if="generalError" class="text-center text-[12px] text-[#EF4444]">{{ generalError }}</p>
      <p v-if="status" class="text-center text-[12px] text-[#22C55E]">{{ status }}</p>

      <div class="fixed bottom-0 left-0 right-0 bg-[#0A0A0A] border-t border-[#1F1F1F] p-4">
        <Button type="submit" variant="primary" class="w-full" :loading="saving" loading-text="Salvando...">
          Salvar
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
import Skeleton from '../../components/Skeleton.vue'

interface Account {
  id: number
  phone_number_id: string
  is_active: boolean
}

const loading = ref(true)
const saving = ref(false)
const status = ref('')
const generalError = ref('')

const existingAccount = ref<Account | null>(null)

const form = reactive({ phone_number_id: '', access_token: '' })
const errors = reactive<Record<string, string>>({})

const xsrf = () =>
  decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

onMounted(async () => {
  try {
    const res = await fetch('/api/whatsapp/account', { headers: { Accept: 'application/json' } })
    if (res.ok) {
      const json = await res.json()
      existingAccount.value = json.data ?? null
      if (existingAccount.value) {
        form.phone_number_id = existingAccount.value.phone_number_id
      }
    }
  } finally {
    loading.value = false
  }
})

const save = async () => {
  Object.keys(errors).forEach((k) => delete errors[k])
  generalError.value = ''
  status.value = ''
  saving.value = true

  try {
    const res = await fetch('/api/whatsapp/account', {
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
      const json = await res.json()
      existingAccount.value = json.data
      status.value = 'Conta salva. Configure o webhook no Meta Developers para finalizar.'
      form.access_token = ''
    } else if (res.status === 422) {
      const body = await res.json().catch(() => ({}))
      Object.assign(errors, Object.fromEntries(Object.entries(body.errors ?? {}).map(([k, v]: any) => [k, v[0]])))
      generalError.value = body.message ?? ''
    } else {
      generalError.value = `Erro ${res.status}.`
    }
  } finally {
    saving.value = false
  }
}
</script>
