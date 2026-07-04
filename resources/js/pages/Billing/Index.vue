<template>
  <AppLayout title="Plano e cobrança" show-back-button>
    <div v-if="loading" class="p-4 space-y-3">
      <Skeleton height="80px" />
      <Skeleton height="120px" />
      <Skeleton height="120px" />
    </div>

    <div v-else class="p-4 pb-32 space-y-4">
      <!-- Status atual -->
      <div class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4">
        <div class="flex items-center justify-between gap-3">
          <div class="flex-1 min-w-0">
            <p class="text-[10px] uppercase tracking-[0.08em] text-[#6B6B6B]">Status</p>
            <p class="text-[15px] font-semibold text-white mt-1">{{ statusLabel }}</p>
            <p v-if="info.trial_ends_at" class="text-[12px] text-[#A1A1A1] mt-1">
              Trial até {{ formatDate(info.trial_ends_at) }}
            </p>
            <p v-if="info.next_due_date" class="text-[12px] text-[#A1A1A1] mt-1">
              Próxima cobrança: {{ formatDate(info.next_due_date) }}
            </p>
          </div>
          <div
            class="px-2.5 py-1 rounded-[6px] text-[11px] font-medium"
            :class="info.status === 'active'
              ? 'bg-[#22C55E]/15 text-[#22C55E]'
              : info.status === 'past_due'
                ? 'bg-[#EF4444]/15 text-[#EF4444]'
                : 'bg-[#F59E0B]/15 text-[#F59E0B]'"
          >
            {{ statusBadge }}
          </div>
        </div>
      </div>

      <!-- Planos -->
      <p class="text-[12px] text-[#6B6B6B] uppercase tracking-[0.08em] pt-2">Escolha seu plano</p>

      <div
        v-for="plan in plans"
        :key="plan.id"
        class="bg-[#131313] border rounded-[14px] p-4 relative"
        :class="(plan.featured || info.plan === plan.id) ? 'border-[#FFD60A]' : 'border-[#2A2A2A]'"
      >
        <span
          v-if="plan.featured"
          class="absolute -top-2 left-4 px-2 py-0.5 rounded-full bg-[#FFD60A] text-[#0A0A0A] text-[10px] font-bold uppercase tracking-wide"
        >
          Mais escolhido
        </span>

        <div class="flex items-start justify-between gap-3 mb-3" :class="plan.featured ? 'mt-1' : ''">
          <div class="min-w-0">
            <p class="text-[16px] font-semibold text-white">{{ plan.name }}</p>
            <p class="text-[12px] text-[#A1A1A1] mt-0.5">{{ plan.segment }}</p>
          </div>
          <div class="text-right flex-shrink-0">
            <span class="text-[22px] font-bold text-[#FFD60A] tabular-nums">R$ {{ plan.price }}</span>
            <span class="text-[12px] text-[#6B6B6B]">/mês</span>
          </div>
        </div>

        <p v-if="plan.inherits" class="text-[12px] text-[#A1A1A1] mb-2">{{ plan.inherits }}</p>

        <ul class="text-[13px] text-white/90 space-y-1.5 mb-4">
          <li v-for="f in plan.features" :key="f" class="flex items-start gap-2">
            <Check :size="15" :stroke-width="2.5" class="text-[#22C55E] flex-shrink-0 mt-0.5" />
            <span>{{ f }}</span>
          </li>
        </ul>

        <button
          type="button"
          :disabled="info.plan === plan.id || selecting === plan.id"
          @click="selectPlan(plan.id)"
          class="w-full h-11 rounded-[10px] text-[14px] font-bold transition-colors disabled:cursor-not-allowed"
          :class="info.plan === plan.id
            ? 'bg-[#1F1F1F] text-[#6B6B6B]'
            : 'bg-[#FFD60A] text-[#0A0A0A] hover:bg-[#FFE066]'"
        >
          <Loader2 v-if="selecting === plan.id" :size="16" class="animate-spin inline -mt-0.5 mr-1" />
          {{ info.plan === plan.id ? 'Plano atual' : selecting === plan.id ? 'Processando...' : 'Selecionar' }}
        </button>
      </div>

      <p class="text-center text-[12px] text-[#6B6B6B] pt-1">Troque de plano quando quiser, sem multa.</p>

      <p v-if="generalError" class="text-center text-[12px] text-[#EF4444] pt-2">{{ generalError }}</p>

      <!-- Cancelar assinatura: só quando existe uma assinatura ativa pra cancelar -->
      <div v-if="canCancel" class="pt-4 mt-2 border-t border-[#1F1F1F]">
        <button
          type="button"
          :disabled="cancelling"
          @click="cancelPlan"
          class="w-full h-11 rounded-[10px] text-[14px] font-medium text-[#EF4444] border border-[#EF4444]/30 hover:bg-[#EF4444]/10 transition-colors disabled:opacity-60"
        >
          <Loader2 v-if="cancelling" :size="16" class="animate-spin inline -mt-0.5 mr-1" />
          {{ cancelling ? 'Cancelando...' : 'Cancelar assinatura' }}
        </button>
        <p class="text-center text-[12px] text-[#6B6B6B] mt-2">
          Sua assinatura é encerrada e o acesso às telas pagas fica bloqueado. Seus dados continuam guardados.
        </p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Check, Loader2 } from 'lucide-vue-next'
import AppLayout from '../../layouts/AppLayout.vue'
import Skeleton from '../../components/Skeleton.vue'
import { useConfirm } from '../../composables/useConfirm'
import { useToast } from '../../composables/useToast'

interface BillingInfo {
  status: string
  current_plan?: string | null
  plan?: string | null
  trial_ends_at?: string | null
  next_due_date?: string | null
  current_price?: number | null
  staff_limit?: number
  staff_count?: number
  asaas_subscription_id?: string | null
}

const { ask } = useConfirm()
const toast = useToast()

const loading = ref(true)
const selecting = ref<string | null>(null)
const cancelling = ref(false)
const generalError = ref('')

// Só faz sentido cancelar se existe assinatura e ela ainda não foi cancelada.
const canCancel = computed(
  () => !!info.value.asaas_subscription_id && info.value.status !== 'cancelled'
)

const info = ref<BillingInfo>({ status: 'trial', plan: null })

// Os dois planos têm TUDO (bot de WhatsApp 24h incluso); o único diferencial
// é o número de profissionais.
const plans = [
  {
    id: 'solo',
    name: 'Solo',
    price: 59,
    segment: 'Pra quem atende sozinho',
    featured: false,
    inherits: '',
    features: [
      '1 profissional',
      'Agenda e link de agendamento online',
      'Bot de WhatsApp 24h (cliente agenda sozinho)',
      'Comissões e relatórios de faturamento',
      'Clientes com histórico de visitas',
    ],
  },
  {
    id: 'barbearia',
    name: 'Barbearia',
    price: 119,
    segment: 'Pra equipe de até 10',
    featured: true,
    inherits: 'Tudo do Solo, e mais:',
    features: [
      'Até 10 profissionais',
      'Agenda e comissão por barbeiro',
      'Acesso da equipe por função (gerente, recepção)',
      'Ranking de barbeiros nos relatórios',
      'Suporte prioritário no WhatsApp',
    ],
  },
]

const statusLabel = computed(() => {
  const labels: Record<string, string> = {
    trial: 'Em período de teste',
    active: 'Assinatura ativa',
    past_due: 'Cobrança vencida',
    suspended: 'Suspensa',
    cancelled: 'Cancelada',
  }
  return labels[info.value.status] ?? info.value.status
})

const statusBadge = computed(() => {
  const labels: Record<string, string> = {
    trial: 'Trial',
    active: 'Ativo',
    past_due: 'Vencido',
    suspended: 'Suspenso',
    cancelled: 'Cancelado',
  }
  return labels[info.value.status] ?? info.value.status
})

const formatDate = (iso: string) => {
  if (!iso) return ''
  const d = new Date(iso)
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })
}

const xsrf = () =>
  decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

onMounted(async () => {
  try {
    const res = await fetch('/api/billing', { headers: { Accept: 'application/json' } })
    if (res.ok) {
      const json = await res.json()
      const data = json.data ?? json
      info.value = {
        ...data,
        plan: data.current_plan ?? data.plan ?? null,
      }
    }
  } finally {
    loading.value = false
  }
})

const selectPlan = async (planId: string) => {
  generalError.value = ''
  selecting.value = planId
  try {
    const res = await fetch('/api/billing/select-plan', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrf(),
      },
      body: JSON.stringify({ plan: planId }),
    })
    if (res.ok) {
      const json = await res.json()
      const data = json.data ?? json
      info.value = { ...info.value, ...data, plan: data.current_plan ?? data.plan ?? null }
    } else {
      const b = await res.json().catch(() => ({}))
      generalError.value = b.message ?? `Erro ${res.status}.`
    }
  } finally {
    selecting.value = null
  }
}

const cancelPlan = async () => {
  const ok = await ask(
    'Cancelar assinatura?',
    'Sua assinatura é encerrada e o acesso às telas pagas fica bloqueado. Seus dados continuam guardados — você pode assinar de novo quando quiser.',
    { confirmText: 'Cancelar assinatura', destructive: true }
  )
  if (!ok) return

  cancelling.value = true
  try {
    const res = await fetch('/api/billing/cancel', {
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
      const data = json.data ?? json
      info.value = { ...info.value, ...data, plan: data.current_plan ?? data.plan ?? null }
      toast.success('Assinatura cancelada.')
    } else {
      const b = await res.json().catch(() => ({}))
      toast.error(b.message ?? `Erro ${res.status}.`)
    }
  } finally {
    cancelling.value = false
  }
}
</script>
