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
          :error="errors.cancellation_policy_hours"
        />

        <FormField
          v-model.number="form.default_commission_percentage"
          type="number"
          label="Comissão padrão (%)"
          placeholder=" "
          :error="errors.default_commission_percentage"
        />
      </div>

      <div v-if="slug" class="space-y-2">
        <p class="text-[13px] font-medium text-white">Link de agendamento</p>
        <p class="text-[12px] text-[#6B6B6B] -mt-1">Seus clientes marcam sozinhos por aqui.</p>
        <div class="flex items-center gap-2 bg-[#131313] border border-[#2A2A2A] rounded-[10px] p-2.5">
          <span class="flex-1 min-w-0 truncate text-[13px] text-[#A1A1A1]">{{ bookingUrl }}</span>
          <button
            type="button"
            @click="copyLink"
            class="flex-shrink-0 h-9 px-3 rounded-[8px] bg-[#1A1A1A] border border-[#2A2A2A] text-[12px] font-medium text-white hover:border-[#FFD60A] transition-colors flex items-center gap-1.5 active:scale-[0.97]"
          >
            <component :is="copied ? Check : Copy" :size="14" :stroke-width="2" :class="copied ? 'text-[#22C55E]' : ''" />
            {{ copied ? 'Copiado' : 'Copiar' }}
          </button>
        </div>
        <a
          :href="bookingUrl"
          target="_blank"
          rel="noopener"
          class="inline-block text-[12px] font-medium text-[#FFD60A] hover:text-[#FFE066] transition-colors"
        >
          Abrir página de agendamento
        </a>
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
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Copy, Check } from 'lucide-vue-next'
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

// Link público de agendamento: base = slug do tenant (vem dos props compartilhados).
const slug = computed(() => (usePage().props as any).tenant?.slug as string | undefined)
const bookingUrl = computed(() =>
  slug.value && typeof window !== 'undefined' ? `${window.location.origin}/agendar/${slug.value}` : ''
)
const copied = ref(false)
const copyLink = async () => {
  if (!bookingUrl.value) return
  try {
    await navigator.clipboard.writeText(bookingUrl.value)
    copied.value = true
    toast.success('Link copiado')
    setTimeout(() => (copied.value = false), 2000)
  } catch {
    toast.error('Não foi possível copiar. Copie o link manualmente.')
  }
}

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
