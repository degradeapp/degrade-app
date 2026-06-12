<template>
  <AppLayout title="Notificações" show-back-button>
    <div v-if="loading" class="p-4 space-y-3">
      <Skeleton height="60px" />
      <Skeleton height="60px" />
      <Skeleton height="60px" />
    </div>

    <div v-else class="p-4 pb-32 space-y-6 animate-enter">
      <!-- Canais -->
      <section class="space-y-2">
        <h3 class="flex items-center gap-1.5 text-[13px] font-semibold text-white">
          <MessageSquare :size="14" :stroke-width="2" class="text-[#A1A1A1]" />
          Canais de envio
        </h3>
        <ToggleRow
          v-for="ch in channels"
          :key="ch.value"
          :label="ch.label"
          :description="ch.description"
          :model-value="form.channels.includes(ch.value)"
          @update:model-value="toggleChannel(ch.value)"
        />
      </section>

      <!-- Avisos automáticos (transacionais) -->
      <section class="space-y-2">
        <h3 class="flex items-center gap-1.5 text-[13px] font-semibold text-white">
          <CalendarCheck :size="14" :stroke-width="2" class="text-[#A1A1A1]" />
          Avisos do agendamento
        </h3>
        <ToggleRow label="Agendamento confirmado" description="Quando o horário é marcado" v-model="form.appointment_confirmed" />
        <ToggleRow label="Agendamento remarcado" description="Quando muda de horário" v-model="form.appointment_rescheduled" />
        <ToggleRow label="Agendamento cancelado" description="Quando o horário é cancelado" v-model="form.appointment_cancelled" />
      </section>

      <!-- Lembretes -->
      <section class="space-y-2">
        <h3 class="flex items-center gap-1.5 text-[13px] font-semibold text-white">
          <Clock :size="14" :stroke-width="2" class="text-[#A1A1A1]" />
          Lembretes ao cliente
        </h3>
        <ToggleRow label="Na véspera (24h antes)" description="Reduz faltas em até 30%" v-model="form.reminder_24h_before" />
        <ToggleRow label="No dia (1h antes)" description="Aviso final pouco antes" v-model="form.reminder_1h_before" />
      </section>

      <div class="fixed bottom-0 left-0 right-0 bg-[#0A0A0A] border-t border-[#1F1F1F] p-4">
        <Button type="button" variant="primary" class="w-full" :loading="saving" loading-text="Salvando..." @click="save">
          Salvar preferências
        </Button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, h, onMounted } from 'vue'
import { MessageSquare, CalendarCheck, Clock } from 'lucide-vue-next'
import AppLayout from '../../layouts/AppLayout.vue'
import Button from '../../components/Button.vue'
import Skeleton from '../../components/Skeleton.vue'
import { useToast } from '../../composables/useToast'

const toast = useToast()
const loading = ref(true)
const saving = ref(false)

const form = reactive({
  channels: [] as string[],
  reminder_24h_before: true,
  reminder_1h_before: true,
  appointment_confirmed: true,
  appointment_rescheduled: true,
  appointment_cancelled: true,
})

const channels = [
  { value: 'whatsapp', label: 'WhatsApp', description: 'Recomendado' },
  { value: 'email', label: 'Email', description: 'Pelo seu remetente padrão' },
  { value: 'sms', label: 'SMS', description: 'Provedor externo, com custo (opcional)' },
]

const ToggleRow = (props: { label: string; description: string; modelValue: boolean }, { emit }: any) =>
  h(
    'label',
    {
      class:
        'flex items-center gap-3 bg-[#131313] border border-[#2A2A2A] rounded-[12px] p-4 cursor-pointer hover:border-[#3D3D3D] active:scale-[0.99] transition-all',
    },
    [
      h('div', { class: 'flex-1 min-w-0' }, [
        h('p', { class: 'text-[14px] font-medium text-white' }, props.label),
        h('p', { class: 'text-[12px] text-[#A1A1A1] mt-0.5' }, props.description),
      ]),
      h('input', {
        type: 'checkbox',
        checked: props.modelValue,
        class: 'w-5 h-5 accent-[#FFD60A] flex-shrink-0',
        onChange: (e: Event) => emit('update:modelValue', (e.target as HTMLInputElement).checked),
      }),
    ]
  )

const xsrf = () =>
  decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

const toggleChannel = (ch: string) => {
  if (form.channels.includes(ch)) {
    form.channels = form.channels.filter((c) => c !== ch)
  } else {
    form.channels = [...form.channels, ch]
  }
}

onMounted(async () => {
  try {
    const res = await fetch('/api/notification-settings', { headers: { Accept: 'application/json' } })
    if (res.ok) {
      const json = await res.json()
      Object.assign(form, json.data)
    }
  } finally {
    loading.value = false
  }
})

const save = async () => {
  saving.value = true
  try {
    const res = await fetch('/api/notification-settings', {
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
      toast.success('Preferências salvas')
    }
  } finally {
    saving.value = false
  }
}
</script>
