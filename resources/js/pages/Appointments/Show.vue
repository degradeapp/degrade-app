<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { ChevronLeft, Check, X, Loader2 } from 'lucide-vue-next'
import { useFormatting } from '@/composables/useFormatting'

interface AppointmentService {
  id: number
  service: { id: number; name: string }
  barber: { id: number; name: string } | null
  price_snapshot: number
}
interface Appointment {
  id: number
  customer: { id: number; name: string; phone: string }
  barber: { id: number; name: string } | null
  services: AppointmentService[]
  status: string
  status_label: string
  starts_at: string
  ends_at: string | null
  total_price: number
  notes: string | null
}

const props = defineProps<{ appointment: any }>()

// Defensivo: aceita o agendamento direto OU embrulhado em { data } (o Inertia pode
// embrulhar um Resource) e nunca quebra se algum campo vier ausente.
const appt = computed<Appointment>(() => {
  const raw: any = props.appointment ?? {}
  return (raw.data ?? raw) as Appointment
})

// services pode vir como array puro OU embrulhado em { data: [...] } (resource collection).
const services = computed<AppointmentService[]>(() => {
  const s: any = appt.value.services
  return Array.isArray(s) ? s : (s?.data ?? [])
})

const { formatBRL, formatPhone, formatDateLong } = useFormatting()

const STATUS_COLORS: Record<string, string> = {
  scheduled: '#6B6B6B',
  confirmed: '#A1A1A1',
  in_progress: '#FFD60A',
  awaiting_completion: '#FB923C',
  completed: '#22C55E',
  cancelled: '#EF4444',
  no_show: '#F59E0B',
}
const statusColor = computed(() => STATUS_COLORS[appt.value.status] ?? '#6B6B6B')

const initials = computed(() =>
  (appt.value.customer?.name ?? '')
    .trim()
    .split(/\s+/)
    .map((p: string) => p[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
)

const dateLabel = computed(() => {
  const iso = appt.value.starts_at ?? ''
  if (!iso) return ''
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number)
  return formatDateLong(new Date(y, m - 1, d))
})
const timeLabel = computed(() => {
  const match = (appt.value.starts_at ?? '').match(/T(\d{2}):(\d{2})/)
  return match ? `${match[1]}:${match[2]}` : ''
})

// Cancelar, concluir e remarcar valem para qualquer agendamento ainda "aberto":
// agendado, confirmado, em atendimento ou a concluir. "Em atendimento"/"a concluir"
// são status DERIVADOS (starts_at vs agora) — o gravado segue scheduled/confirmed, e
// remarcar exige horário futuro, então o card simplesmente volta pro futuro. Isso cobre
// o caso real: cliente atrasado (já "em atendimento" no relógio) que quer empurrar o horário.
const OPEN_STATUSES = ['scheduled', 'confirmed', 'in_progress', 'awaiting_completion']
const canCancel = computed(() => OPEN_STATUSES.includes(appt.value.status))
const canComplete = computed(() => OPEN_STATUSES.includes(appt.value.status))
const canReschedule = computed(() => OPEN_STATUSES.includes(appt.value.status))

const actionLoading = ref<null | 'cancel' | 'no_show' | 'complete' | 'reschedule'>(null)
const actionError = ref('')
const confirmingCancel = ref(false)
const reschedulingOpen = ref(false)
const newStartsAt = ref('')

const xsrf = () =>
  decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

const apiCall = async (path: string, method: string, body?: any) => {
  const res = await fetch(path, {
    method,
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-XSRF-TOKEN': xsrf(),
    },
    body: body ? JSON.stringify(body) : undefined,
  })
  if (res.status === 401) window.location.href = '/login' // sessão perdida → login
  return res
}

const doComplete = async () => {
  actionError.value = ''
  actionLoading.value = 'complete'
  try {
    const res = await apiCall(`/api/appointments/${appt.value.id}/complete`, 'POST')
    if (res.ok) {
      router.reload()
    } else {
      const b = await res.json().catch(() => ({}))
      actionError.value = b.message ?? `Erro ${res.status}.`
    }
  } finally {
    actionLoading.value = null
  }
}

const doCancel = async () => {
  confirmingCancel.value = false
  actionError.value = ''
  actionLoading.value = 'cancel'
  try {
    const res = await apiCall(`/api/appointments/${appt.value.id}/cancel`, 'POST', { reason: 'Cliente desmarcou' })
    if (res.ok) {
      router.reload()
    } else {
      const b = await res.json().catch(() => ({}))
      actionError.value = b.message ?? `Erro ${res.status}.`
    }
  } finally {
    actionLoading.value = null
  }
}

const doNoShow = async () => {
  confirmingCancel.value = false
  actionError.value = ''
  actionLoading.value = 'no_show'
  try {
    const res = await apiCall(`/api/appointments/${appt.value.id}/no-show`, 'POST')
    if (res.ok) {
      router.reload()
    } else {
      const b = await res.json().catch(() => ({}))
      actionError.value = b.message ?? `Erro ${res.status}.`
    }
  } finally {
    actionLoading.value = null
  }
}

const doReschedule = async () => {
  if (!newStartsAt.value) return
  actionError.value = ''
  actionLoading.value = 'reschedule'
  try {
    // datetime-local devolve "YYYY-MM-DDTHH:mm" (sem segundos); o back exige
    // Y-m-d\TH:i:s. Sem o ":00" a validação dava 422 e o modal não fechava.
    const startsAt = newStartsAt.value.length === 16 ? `${newStartsAt.value}:00` : newStartsAt.value
    const res = await apiCall(`/api/appointments/${appt.value.id}/reschedule`, 'PUT', { starts_at: startsAt })
    if (res.ok) {
      reschedulingOpen.value = false
      router.reload()
    } else {
      const b = await res.json().catch(() => ({}))
      actionError.value = b.message ?? `Erro ${res.status}.`
    }
  } finally {
    actionLoading.value = null
  }
}
</script>

<template>
  <div class="h-dvh overflow-hidden flex flex-col bg-[#0A0A0A] text-[#F5F5F5]">
    <!-- HEADER -->
    <div class="flex-shrink-0 h-14 flex items-center px-2 bg-[#0A0A0A] border-b border-[#1F1F1F]">
      <button
        @click="router.visit('/appointments')"
        class="w-10 h-10 flex items-center justify-center text-[#A1A1A1] hover:text-white rounded-full transition-colors"
        aria-label="Voltar"
      >
        <ChevronLeft :size="24" :stroke-width="1.75" />
      </button>
      <h1 class="flex-1 text-center text-[16px] font-semibold text-white pr-10">Agendamento</h1>
    </div>

    <!-- CONTEÚDO -->
    <main class="flex-1 overflow-y-auto px-4 py-4 space-y-3 stagger">
      <!-- STATUS -->
      <div class="flex justify-center">
        <span
          class="inline-flex items-center h-7 px-3 rounded-full text-[12px] font-semibold"
          :style="{ backgroundColor: statusColor + '26', color: statusColor }"
        >
          {{ appt.status_label }}
        </span>
      </div>

      <!-- CLIENTE -->
      <div class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4">
        <p class="text-[11px] text-[#6B6B6B] uppercase tracking-[0.08em] mb-2.5">Cliente</p>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full bg-[#1A1A1A] flex items-center justify-center text-[13px] font-semibold text-[#FFD60A] flex-shrink-0">
            {{ initials }}
          </div>
          <div class="min-w-0">
            <p class="text-[14px] font-medium text-white truncate">{{ appt.customer?.name }}</p>
            <p class="text-[12px] text-[#A1A1A1] tabular-nums">{{ formatPhone(appt.customer?.phone) }}</p>
          </div>
        </div>
      </div>

      <!-- SERVIÇOS -->
      <div class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4">
        <p class="text-[11px] text-[#6B6B6B] uppercase tracking-[0.08em] mb-1">Serviços</p>
        <div
          v-for="(s, i) in services"
          :key="s.id"
          class="flex items-center justify-between py-2"
          :class="i > 0 ? 'border-t border-[#1F1F1F]' : ''"
        >
          <div class="min-w-0 pr-3">
            <p class="text-[14px] font-medium text-white truncate">{{ s.service?.name }}</p>
            <p v-if="s.barber" class="text-[12px] text-[#A1A1A1] mt-0.5">com {{ s.barber.name }}</p>
          </div>
          <span class="text-[14px] font-medium text-white tabular-nums flex-shrink-0">{{ formatBRL(s.price_snapshot) }}</span>
        </div>
        <div class="flex items-center justify-between pt-3 mt-1 border-t border-[#2A2A2A]">
          <span class="text-[14px] font-semibold text-white">Total</span>
          <span class="text-[18px] font-bold text-[#FFD60A] tabular-nums">{{ formatBRL(appt.total_price) }}</span>
        </div>
      </div>

      <!-- DATA E HORÁRIO -->
      <div class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4">
        <p class="text-[11px] text-[#6B6B6B] uppercase tracking-[0.08em] mb-2">Data e horário</p>
        <p class="text-[15px] font-medium text-white">
          {{ dateLabel }} <span class="text-[#FFD60A] tabular-nums">· {{ timeLabel }}</span>
        </p>
      </div>

      <!-- OBSERVAÇÕES (só quando houver) -->
      <div v-if="appt.notes" class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4">
        <p class="text-[11px] text-[#6B6B6B] uppercase tracking-[0.08em] mb-2">Observações</p>
        <p class="text-[14px] text-[#F5F5F5] leading-relaxed whitespace-pre-line break-words">{{ appt.notes }}</p>
      </div>

      <p v-if="actionError" class="text-center text-[12px] text-[#EF4444] pt-1">{{ actionError }}</p>
    </main>

    <!-- AÇÕES FIXAS -->
    <div
      v-if="canCancel || canComplete || canReschedule"
      class="flex-shrink-0 px-4 py-3 bg-[#0A0A0A] border-t border-[#1F1F1F] space-y-2"
    >
      <button
        v-if="canComplete"
        type="button"
        :disabled="actionLoading !== null"
        @click="doComplete"
        class="w-full h-12 rounded-[10px] font-bold text-[15px] text-[#0A0A0A] flex items-center justify-center gap-2 bg-[#FFD60A] enabled:hover:bg-[#FFE066] enabled:active:scale-[0.98] disabled:opacity-70 transition-all"
      >
        <Loader2 v-if="actionLoading === 'complete'" :size="18" class="animate-spin" />
        <Check v-else :size="18" :stroke-width="2" />
        {{ actionLoading === 'complete' ? 'Concluindo...' : 'Concluir atendimento' }}
      </button>

      <div class="flex gap-2">
        <button
          v-if="canReschedule"
          type="button"
          :disabled="actionLoading !== null"
          @click="actionError = ''; reschedulingOpen = true"
          class="flex-1 h-12 rounded-[10px] text-[14px] font-medium text-[#A1A1A1] border border-[#2A2A2A] hover:text-white hover:border-[#3D3D3D] transition-all enabled:active:scale-[0.98] disabled:opacity-70"
        >
          Remarcar
        </button>
        <button
          v-if="canCancel"
          type="button"
          :disabled="actionLoading !== null"
          @click="confirmingCancel = true"
          class="flex-1 h-12 rounded-[10px] text-[14px] font-medium text-[#EF4444] border border-[#EF4444]/30 hover:bg-[#EF4444]/10 transition-all enabled:active:scale-[0.98] disabled:opacity-70"
        >
          Cancelar
        </button>
      </div>
    </div>

    <!-- CONFIRMAR CANCELAMENTO -->
    <Teleport to="body">
      <div v-if="confirmingCancel" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40" @click="confirmingCancel = false"></div>
      <div
        v-if="confirmingCancel"
        class="fixed bottom-0 left-0 right-0 z-50 bg-[#131313] border-t border-[#2A2A2A] rounded-t-[20px] p-5 pb-8 space-y-4 animate-sheet"
      >
        <div class="w-10 h-1 bg-[#3D3D3D] rounded-full mx-auto"></div>
        <h3 class="text-[16px] font-semibold text-white">O que aconteceu?</h3>
        <p class="text-[13px] text-[#A1A1A1]">O atendimento sai da agenda. Não dá pra desfazer.</p>
        <div class="space-y-2">
          <button
            type="button"
            :disabled="actionLoading !== null"
            class="w-full h-12 rounded-[10px] text-[14px] font-medium text-[#EF4444] border border-[#EF4444]/30 hover:bg-[#EF4444]/10 transition-all enabled:active:scale-[0.98] disabled:opacity-70 flex items-center justify-center gap-2"
            @click="doCancel"
          >
            <Loader2 v-if="actionLoading === 'cancel'" :size="16" class="animate-spin" />
            Cliente desmarcou
          </button>
          <button
            type="button"
            :disabled="actionLoading !== null"
            class="w-full h-12 rounded-[10px] text-[14px] font-medium text-[#F59E0B] border border-[#F59E0B]/30 hover:bg-[#F59E0B]/10 transition-all enabled:active:scale-[0.98] disabled:opacity-70 flex items-center justify-center gap-2"
            @click="doNoShow"
          >
            <Loader2 v-if="actionLoading === 'no_show'" :size="16" class="animate-spin" />
            Cliente não apareceu
          </button>
          <button
            type="button"
            class="w-full h-12 rounded-[10px] text-[14px] font-medium text-[#A1A1A1] border border-[#2A2A2A] hover:text-white transition-colors"
            @click="confirmingCancel = false"
          >
            Voltar
          </button>
        </div>
      </div>
    </Teleport>

    <!-- REMARCAR -->
    <Teleport to="body">
      <div v-if="reschedulingOpen" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40" @click="reschedulingOpen = false"></div>
      <div
        v-if="reschedulingOpen"
        class="fixed bottom-0 left-0 right-0 z-50 bg-[#131313] border-t border-[#2A2A2A] rounded-t-[20px] p-5 pb-8 space-y-4 animate-sheet"
      >
        <div class="w-10 h-1 bg-[#3D3D3D] rounded-full mx-auto"></div>
        <h3 class="text-[16px] font-semibold text-white">Remarcar para...</h3>
        <input
          v-model="newStartsAt"
          type="datetime-local"
          class="block w-full h-12 px-4 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[14px] text-white outline-none focus:border-[#FFD60A]"
        />
        <p v-if="actionError" class="text-[12px] text-[#EF4444]">{{ actionError }}</p>
        <div class="flex gap-3">
          <button
            type="button"
            class="flex-1 h-12 rounded-[10px] text-[14px] font-medium text-[#A1A1A1] border border-[#2A2A2A]"
            @click="reschedulingOpen = false"
          >
            Cancelar
          </button>
          <button
            type="button"
            :disabled="!newStartsAt || actionLoading !== null"
            class="flex-1 h-12 rounded-[10px] text-[14px] font-bold text-[#0A0A0A] bg-[#FFD60A] enabled:hover:bg-[#FFE066] disabled:opacity-50"
            @click="doReschedule"
          >
            <Loader2 v-if="actionLoading === 'reschedule'" :size="16" class="animate-spin inline -mt-0.5 mr-1" />
            Confirmar
          </button>
        </div>
      </div>
    </Teleport>
  </div>
</template>
