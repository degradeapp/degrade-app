<template>
  <AppLayout title="Detalhes da comissão" show-back-button>
    <div v-if="loading" class="p-4 space-y-3">
      <Skeleton height="80px" />
      <Skeleton height="60px" />
      <Skeleton height="60px" />
    </div>

    <div v-else-if="!commission" class="p-4 text-center text-[13px] text-[#6B6B6B] py-16">
      Comissão não encontrada.
    </div>

    <div v-else class="p-4 pb-32 space-y-4 animate-enter">
      <!-- Valor + status -->
      <div class="relative overflow-hidden bg-gradient-to-br from-[#1C1C18] to-[#131313] border border-[#2A2A2A] rounded-[16px] p-6 text-center">
        <div class="relative">
          <p class="text-[10px] uppercase tracking-[0.08em] text-[#A1A1A1] mb-1.5">Valor da comissão</p>
          <p class="text-[36px] font-bold tabular-nums text-white tracking-tight leading-none">{{ formatCurrency(commission.amount) }}</p>
          <div class="mt-3 inline-flex items-center gap-1.5 px-3 py-1 rounded-full"
            :class="commission.status === 'paid'
              ? 'bg-[#22C55E]/15 text-[#22C55E]'
              : 'bg-[#F59E0B]/15 text-[#F59E0B]'"
          >
            <component :is="commission.status === 'paid' ? CheckCircle2 : Clock" :size="13" :stroke-width="2.5" />
            <span class="text-[12px] font-medium">{{ commission.status === 'paid' ? 'Pago' : 'Pendente' }}</span>
          </div>
        </div>
      </div>

      <!-- Detalhes -->
      <div class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] divide-y divide-[#1F1F1F]">
        <Row label="Barbeiro" :value="commission.barber?.name ?? '—'" />
        <Row label="Origem" :value="commission.reference_type === 'appointment' ? 'Agendamento' : commission.reference_type" />
        <Row v-if="commission.appointment" label="Agendamento" :value="`#${commission.appointment.id}`" />
        <Row label="Data da referência" :value="formatDate(commission.reference_date)" />
        <Row v-if="commission.paid_at" label="Pago em" :value="formatDateTime(commission.paid_at)" />
      </div>

      <p v-if="generalError" class="text-center text-[12px] text-[#EF4444]">{{ generalError }}</p>

      <!-- Ações -->
      <div v-if="commission.status === 'pending'" class="fixed bottom-0 left-0 right-0 bg-[#0A0A0A] border-t border-[#1F1F1F] p-4">
        <Button type="button" variant="primary" class="w-full" :loading="marking" loading-text="Salvando..." @click="markPaid">
          Marcar como pago
        </Button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, h, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { CheckCircle2, Clock } from 'lucide-vue-next'
import AppLayout from '../../layouts/AppLayout.vue'
import Button from '../../components/Button.vue'
import Skeleton from '../../components/Skeleton.vue'

interface Commission {
  id: number
  amount: number
  status: string
  reference_type: string
  reference_date: string
  paid_at?: string | null
  barber?: { id: number; name: string }
  appointment?: { id: number }
}

const Row = (props: { label: string; value: string }) =>
  h('div', { class: 'flex items-center justify-between gap-3 px-4 py-3' }, [
    h('span', { class: 'text-[12px] uppercase tracking-[0.08em] text-[#6B6B6B]' }, props.label),
    h('span', { class: 'text-[14px] font-medium text-white text-right' }, props.value),
  ])

const page = usePage()
const commissionId = page.url.split('/').filter(Boolean)[1]

const loading = ref(true)
const marking = ref(false)
const generalError = ref('')
const commission = ref<Commission | null>(null)

const xsrf = () =>
  decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

const formatCurrency = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v) || 0)

const formatDate = (iso: string) =>
  new Date(iso + 'T00:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })

const formatDateTime = (iso: string) =>
  new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: 'long', hour: '2-digit', minute: '2-digit' })

onMounted(async () => {
  try {
    const res = await fetch(`/api/commissions/${commissionId}`, { headers: { Accept: 'application/json' } })
    if (res.ok) {
      const json = await res.json()
      commission.value = json.data ?? json
    }
  } finally {
    loading.value = false
  }
})

const markPaid = async () => {
  if (!commission.value) return
  generalError.value = ''
  marking.value = true
  try {
    const res = await fetch(`/api/commissions/${commission.value.id}/mark-as-paid`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrf(),
      },
    })
    if (res.ok) {
      const json = await res.json()
      commission.value = json.data ?? json
    } else {
      generalError.value = `Erro ${res.status}.`
    }
  } finally {
    marking.value = false
  }
}
</script>
