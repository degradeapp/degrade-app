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

        <!-- Endereço editável (só o dono): vira a bio do Instagram e o QR do balcão -->
        <div v-if="isOwner">
          <div
            class="flex items-center bg-[#161616] border rounded-[10px] h-12 px-3 focus-within:border-[#FFD60A]"
            :class="errors.slug ? 'border-[#EF4444]' : 'border-[#2A2A2A]'"
          >
            <span class="text-[13px] text-[#6B6B6B] whitespace-nowrap">/agendar/</span>
            <input
              v-model="slugInput"
              type="text"
              maxlength="40"
              autocapitalize="off"
              autocomplete="off"
              spellcheck="false"
              class="flex-1 min-w-0 bg-transparent outline-none text-[15px] text-white"
              @input="slugInput = slugInput.toLowerCase().replace(/[^a-z0-9-]/g, '')"
            />
          </div>
          <p v-if="errors.slug" class="text-[12px] text-[#EF4444] mt-1.5">{{ errors.slug }}</p>
        </div>

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

        <!-- QR code pro balcão/espelho: o cliente aponta a câmera e agenda -->
        <div v-if="qrDataUrl" class="flex items-center gap-4 bg-[#131313] border border-[#2A2A2A] rounded-[12px] p-3 mt-2">
          <img :src="qrDataUrl" alt="QR code do link de agendamento" class="w-24 h-24 rounded-[6px] bg-white p-1 flex-shrink-0" />
          <div class="min-w-0">
            <p class="text-[13px] font-medium text-white">QR code do link</p>
            <a
              :href="qrDataUrl"
              :download="`qr-agendamento-${slug}.png`"
              class="inline-flex items-center gap-1.5 mt-2 h-9 px-3 rounded-[8px] bg-[#1A1A1A] border border-[#2A2A2A] text-[12px] font-medium text-white hover:border-[#FFD60A] transition-colors"
            >
              <Download :size="14" :stroke-width="2" />
              Baixar para imprimir
            </a>
          </div>
        </div>
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
import { ref, reactive, onMounted, watch } from 'vue'
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Copy, Check, Download } from 'lucide-vue-next'
import QRCode from 'qrcode'
import { useConfirm } from '../../composables/useConfirm'
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

const { ask } = useConfirm()
const page = usePage()
const isOwner = computed(() => (page.props as any).auth?.user?.role === 'owner')

// Link público de agendamento. O slug salvo começa pelo dos props compartilhados e é
// atualizado com a resposta da API (o dono pode trocar o endereço aqui).
const slug = ref<string | undefined>((page.props as any).tenant?.slug)
const slugInput = ref(slug.value ?? '')
const bookingUrl = computed(() =>
  slug.value && typeof window !== 'undefined' ? `${window.location.origin}/agendar/${slug.value}` : ''
)

// QR em PNG grande (nítido impresso), preto no branco pra qualquer câmera ler.
const qrDataUrl = ref('')
watch(
  bookingUrl,
  async (url) => {
    qrDataUrl.value = url
      ? await QRCode.toDataURL(url, { width: 1024, margin: 2, errorCorrectionLevel: 'M', color: { dark: '#0A0A0A', light: '#FFFFFF' } })
      : ''
  },
  { immediate: true }
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

  const slugChanged = isOwner.value && slugInput.value && slugInput.value !== slug.value
  if (slugChanged) {
    const ok = await ask(
      'Trocar o endereço do link?',
      `O link novo passa a ser /agendar/${slugInput.value}. O endereço antigo para de funcionar, inclusive em QR codes já impressos e na bio do Instagram.`,
      { confirmText: 'Trocar endereço' }
    )
    if (!ok) return
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
      body: JSON.stringify(slugChanged ? { ...form, slug: slugInput.value } : form),
    })
    if (res.ok) {
      const json = await res.json().catch(() => ({}))
      if (json.data?.slug) {
        slug.value = json.data.slug
        slugInput.value = json.data.slug
      }
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
      if (data.slug) {
        slug.value = data.slug
        slugInput.value = data.slug
      }
    }
  } catch (e) {
    console.error('Error:', e)
  }
})
</script>
