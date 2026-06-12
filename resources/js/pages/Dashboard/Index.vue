<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useFormatting } from '@/composables/useFormatting'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import AppLayout from '@/layouts/AppLayout.vue'
import { CalendarOff, ChevronRight, Sparkles, TrendingUp, CalendarDays, Gauge, Wallet, Check, UserX, Loader2 } from 'lucide-vue-next'

defineOptions({
  layout: AppLayout,
})

interface UpcomingAppointment {
  id: number
  customer_name: string
  service_name: string
  barber_name: string
  barber_initials: string
  starts_at: string
  status: 'scheduled' | 'confirmed' | 'in_progress' | 'awaiting_completion' | 'completed' | 'cancelled' | 'no_show'
}

interface TenantInfo {
  name?: string
  status?: string
  plan?: string | null
  trial_days_left?: number | null
  onboarding_completed_at?: string | null
}

interface RevenueDay {
  date: string
  label: string
  total: number
}

interface DashboardProps {
  user: { id: number; name: string }
  tenant?: TenantInfo
  stats: {
    appointments_today: number
    appointments_completed: number
    appointments_pending: number
    appointments_awaiting: number
    revenue_today: number
    avg_ticket_today: number
    occupation_rate: number
    occupation_booked_hours: number
    occupation_available_hours: number
  }
  upcoming_appointments: UpcomingAppointment[]
  awaiting_appointments: UpcomingAppointment[]
  revenue_week?: RevenueDay[]
  pending_commissions?: number
  can_see_finance?: boolean
}

const props = withDefaults(defineProps<DashboardProps>(), {
  upcoming_appointments: () => [],
  awaiting_appointments: () => [],
  tenant: () => ({}),
  revenue_week: () => [],
  pending_commissions: 0,
  can_see_finance: true,
})

// Recepcionista/barbeiro não veem dinheiro (receita, ticket, comissões).
const canSeeFinance = computed(() => props.can_see_finance !== false)

// Banner do trial leva pro Plano (só dono mexe em cobrança). Gerente/recepção/barbeiro
// NÃO veem — tocar levaria a /403. É decisão de conta, exclusiva do dono.
const role = computed(() => (usePage().props as any).auth?.user?.role ?? null)
const showTrialBanner = computed(() => role.value === 'owner' && props.tenant?.status === 'trial' && (props.tenant?.trial_days_left ?? 0) > 0)
const trialUrgent = computed(() => (props.tenant?.trial_days_left ?? 99) <= 3)

const maxRevenue = computed(() => Math.max(1, ...props.revenue_week.map((d) => d.total)))
const totalWeek = computed(() => props.revenue_week.reduce((sum, d) => sum + d.total, 0))

const { formatBRL, formatDateRelative } = useFormatting()

const firstName = computed(() => props.user?.name?.split(' ')[0] ?? 'Bem-vindo')

const longDate = computed(() => {
  const d = new Date()
  const weekday = d.toLocaleDateString('pt-BR', { weekday: 'long' })
  const day = d.getDate()
  const month = d.toLocaleDateString('pt-BR', { month: 'long' })
  const cap = (s: string) => s.charAt(0).toUpperCase() + s.slice(1)
  return `${cap(weekday)}, ${day} de ${month}`
})

// Expediente de hoje: 0 = dia fechado / sem barbeiro escalado → mostra "sem expediente".
const hasSchedule = computed(() => (props.stats?.occupation_available_hours ?? 0) > 0)

const formatHours = (h: number): string =>
  `${Number.isInteger(h) ? h : h.toFixed(1).replace('.', ',')}h`

// Hora sempre no fuso da barbearia (vem do backend), não do navegador, pra bater com a agenda.
const TENANT_TZ = ((usePage().props as any).tenant?.timezone as string) || 'America/Manaus'
const formatHora = (iso: string): string => {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: TENANT_TZ,
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(new Date(iso))
  const h = parts.find((p) => p.type === 'hour')?.value ?? '00'
  const m = parts.find((p) => p.type === 'minute')?.value ?? '00'
  return `${h}:${m}`
}

const statusBorderColor = (status: UpcomingAppointment['status']): string => {
  const map: Record<UpcomingAppointment['status'], string> = {
    scheduled: '#6B6B6B',
    confirmed: '#A1A1A1',
    in_progress: '#FFD60A',
    awaiting_completion: '#F59E0B',
    completed: '#22C55E',
    cancelled: '#EF4444',
    no_show: '#F59E0B',
  }
  return map[status] ?? '#6B6B6B'
}

// Resumo do card "Hoje": concluídos / a fazer (futuro) / a concluir (passou e ninguém fechou).
// Mostra só os trechos que têm valor, pra não inventar "0 a fazer" quando não há.
const todayBreakdown = computed(() => {
  const s = props.stats
  const parts: string[] = []
  if (s.appointments_completed > 0) parts.push(`${s.appointments_completed} ${s.appointments_completed === 1 ? 'concluído' : 'concluídos'}`)
  if (s.appointments_pending > 0) parts.push(`${s.appointments_pending} a fazer`)
  if ((s.appointments_awaiting ?? 0) > 0) parts.push(`${s.appointments_awaiting} a concluir`)
  return parts.length > 0 ? parts.join(' · ') : 'Nada marcado'
})

// --- Ações rápidas pra baixar a fila "A concluir" (padrão checkout do Square):
// um toque por item, sem fechar nada automático. Concluir gera venda + comissão
// (por isso é explícito); falta é confirmada por ser estado negativo do cliente.
const { ask } = useConfirm()
const toast = useToast()
const resolvingId = ref<number | null>(null)
const resolvingAction = ref<'complete' | 'no_show' | null>(null)

const xsrf = () =>
  decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

const postAction = (id: number, path: string) =>
  fetch(`/api/appointments/${id}/${path}`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-XSRF-TOKEN': xsrf(),
    },
  })

const completeAppt = async (apt: UpcomingAppointment) => {
  if (resolvingId.value) return
  resolvingId.value = apt.id
  resolvingAction.value = 'complete'
  try {
    const res = await postAction(apt.id, 'complete')
    if (res.ok) {
      toast.success('Atendimento concluído.')
      router.reload()
    } else if (res.status === 401) {
      window.location.href = '/login' // sessão perdida → login
    } else {
      const b = await res.json().catch(() => ({}))
      toast.error(b.message ?? 'Não foi possível concluir.')
    }
  } finally {
    resolvingId.value = null
    resolvingAction.value = null
  }
}

const noShowAppt = async (apt: UpcomingAppointment) => {
  if (resolvingId.value) return
  const ok = await ask(
    'Marcar como falta?',
    `${apt.customer_name} não compareceu. Não gera comissão e fica registrado no histórico do cliente.`,
    { confirmText: 'Não compareceu', destructive: true }
  )
  if (!ok) return
  resolvingId.value = apt.id
  resolvingAction.value = 'no_show'
  try {
    const res = await postAction(apt.id, 'no-show')
    if (res.ok) {
      toast.success('Marcado como falta.')
      router.reload()
    } else if (res.status === 401) {
      window.location.href = '/login' // sessão perdida → login
    } else {
      const b = await res.json().catch(() => ({}))
      toast.error(b.message ?? 'Não foi possível marcar a falta.')
    }
  } finally {
    resolvingId.value = null
    resolvingAction.value = null
  }
}
</script>

<template>
  <div class="px-4 py-6 pb-24 space-y-5 stagger">
    <!-- TRIAL BANNER -->
    <Link
      v-if="showTrialBanner"
      href="/billing"
      class="block rounded-[14px] border p-3.5"
      :class="trialUrgent
        ? 'bg-[#EF4444]/10 border-[#EF4444]/30'
        : 'bg-[#FFD60A]/10 border-[#FFD60A]/30'"
    >
      <div class="flex items-center gap-3">
        <Sparkles :size="20" :stroke-width="1.75" :class="trialUrgent ? 'text-[#EF4444]' : 'text-[#FFD60A]'" />
        <div class="flex-1 min-w-0">
          <p class="text-[13px] font-medium text-white">
            {{ tenant?.trial_days_left === 1 ? '1 dia' : `${tenant?.trial_days_left} dias` }} restantes no trial
          </p>
          <p class="text-[11px] text-[#A1A1A1] mt-0.5">Toque para escolher seu plano</p>
        </div>
        <ChevronRight :size="16" class="text-[#A1A1A1]" :stroke-width="1.75" />
      </div>
    </Link>

    <!-- SAUDAÇÃO -->
    <div>
      <h1 class="text-[22px] font-bold text-white">Olá, {{ firstName }}</h1>
      <p class="text-[13px] text-[#A1A1A1] mt-0.5">{{ longDate }}</p>
    </div>

    <!-- HERO: Receita de hoje (só dono/gerente) -->
    <div v-if="canSeeFinance" class="relative overflow-hidden rounded-[16px] border border-[#2A2A2A] bg-gradient-to-br from-[#1C1C18] to-[#131313] p-5">
      <div class="relative">
        <div class="flex items-center gap-1.5 mb-1.5">
          <TrendingUp :size="15" :stroke-width="2.25" class="text-[#FFD60A]" />
          <span class="text-[11px] uppercase tracking-[0.08em] text-[#A1A1A1] font-medium">Receita de hoje</span>
        </div>
        <p class="text-[34px] font-bold text-white tabular-nums leading-none">{{ formatBRL(stats.revenue_today) }}</p>
        <p class="text-[12px] text-[#A1A1A1] mt-2 tabular-nums">
          <template v-if="stats.appointments_today > 0">
            {{ stats.appointments_completed }} de {{ stats.appointments_today }} atendimentos concluídos
          </template>
          <template v-else>Nenhum atendimento marcado pra hoje</template>
        </p>
        <p v-if="stats.appointments_completed > 0" class="text-[12px] text-[#A1A1A1] mt-1 tabular-nums">
          Média de {{ formatBRL(stats.avg_ticket_today) }} por atendimento
        </p>
      </div>
    </div>

    <!-- STATS: agendamentos + ocupação -->
    <div class="grid grid-cols-2 gap-3">
      <div class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4 flex flex-col">
        <div class="flex items-center gap-1.5 mb-3">
          <CalendarDays :size="14" :stroke-width="2" class="text-[#6B6B6B]" />
          <span class="text-[11px] text-[#A1A1A1] font-medium">Hoje</span>
        </div>
        <p class="text-[26px] font-bold text-white tabular-nums leading-none">{{ stats.appointments_today }}</p>
        <p class="text-[12px] text-[#A1A1A1] mt-auto pt-2 tabular-nums">
          {{ todayBreakdown }}
        </p>
      </div>

      <div class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4 flex flex-col">
        <div class="flex items-center gap-1.5 mb-3">
          <Gauge :size="14" :stroke-width="2" class="text-[#6B6B6B]" />
          <span class="text-[11px] text-[#A1A1A1] font-medium">Ocupação</span>
        </div>
        <p class="text-[26px] font-bold text-white tabular-nums leading-none">{{ hasSchedule ? `${stats.occupation_rate}%` : '—' }}</p>
        <div class="mt-auto pt-2.5">
          <template v-if="hasSchedule">
            <div class="h-[4px] bg-[#1F1F1F] rounded-full overflow-hidden mb-1.5">
              <div
                class="h-full bg-[#FFD60A] rounded-full transition-all"
                :style="{ width: `${Math.min(stats.occupation_rate, 100)}%` }"
              ></div>
            </div>
            <p class="text-[11px] text-[#6B6B6B] tabular-nums">{{ formatHours(stats.occupation_booked_hours) }} de {{ formatHours(stats.occupation_available_hours) }} ocupadas</p>
          </template>
          <p v-else class="text-[11px] text-[#6B6B6B]">Sem expediente hoje</p>
        </div>
      </div>
    </div>

    <!-- A CONCLUIR (passaram do horário e seguem em aberto) -->
    <div v-if="awaiting_appointments.length > 0">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-[15px] font-semibold text-white">A concluir</h2>
        <Link href="/appointments" class="text-[12px] font-medium text-[#FFD60A] hover:text-[#FFE066] transition-colors flex items-center gap-0.5">
          Ver tudo
          <ChevronRight :size="14" :stroke-width="2" />
        </Link>
      </div>

      <div class="space-y-2">
        <div
          v-for="apt in awaiting_appointments"
          :key="apt.id"
          class="bg-[#131313] border border-[#F59E0B]/25 rounded-[12px] overflow-hidden"
        >
          <!-- Info: toca pra abrir o detalhe (lá tem remarcar, cancelar etc.) -->
          <button
            type="button"
            @click="router.visit(`/appointments/${apt.id}`)"
            class="w-full flex items-center gap-3 p-3 pl-0 text-left hover:bg-[#1A1A1A] transition-colors"
          >
            <div class="w-[3px] self-stretch rounded-full flex-shrink-0 bg-[#F59E0B]"></div>
            <div class="w-8 h-8 rounded-full bg-[#FFD60A]/15 flex items-center justify-center flex-shrink-0 text-[#FFD60A] font-semibold text-[11px]">
              {{ apt.barber_initials }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[14px] font-medium text-white truncate">{{ apt.customer_name }}</p>
              <p class="text-[12px] text-[#A1A1A1] truncate">{{ apt.service_name }}</p>
            </div>
            <p class="text-[15px] font-semibold tabular-nums text-white leading-none flex-shrink-0 pr-3">{{ formatHora(apt.starts_at) }}</p>
          </button>

          <!-- Ações rápidas (checkout): um toque por item, nada automático -->
          <div class="flex border-t border-[#2A2A2A]">
            <button
              type="button"
              :disabled="resolvingId === apt.id"
              @click="completeAppt(apt)"
              class="flex-1 h-11 flex items-center justify-center gap-1.5 text-[13px] font-semibold text-[#22C55E] hover:bg-[#22C55E]/10 disabled:opacity-50 transition-colors"
            >
              <Loader2 v-if="resolvingId === apt.id && resolvingAction === 'complete'" :size="15" class="animate-spin" />
              <Check v-else :size="15" :stroke-width="2.25" />
              Concluir
            </button>
            <div class="w-px bg-[#2A2A2A]"></div>
            <button
              type="button"
              :disabled="resolvingId === apt.id"
              @click="noShowAppt(apt)"
              class="flex-1 h-11 flex items-center justify-center gap-1.5 text-[13px] font-medium text-[#A1A1A1] hover:bg-[#EF4444]/10 hover:text-[#EF4444] disabled:opacity-50 transition-colors"
            >
              <Loader2 v-if="resolvingId === apt.id && resolvingAction === 'no_show'" :size="15" class="animate-spin" />
              <UserX v-else :size="15" :stroke-width="2" />
              Não compareceu
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- PRÓXIMOS AGENDAMENTOS -->
    <div>
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-[15px] font-semibold text-white">Próximos agendamentos</h2>
        <Link href="/appointments" class="text-[12px] font-medium text-[#FFD60A] hover:text-[#FFE066] transition-colors flex items-center gap-0.5">
          Ver tudo
          <ChevronRight :size="14" :stroke-width="2" />
        </Link>
      </div>

      <div v-if="upcoming_appointments.length > 0" class="space-y-2">
        <Link
          v-for="apt in upcoming_appointments"
          :key="apt.id"
          :href="`/appointments/${apt.id}`"
          class="flex items-center gap-3 bg-[#131313] border border-[#2A2A2A] rounded-[12px] p-3 pl-0 hover:border-[#3D3D3D] transition-colors overflow-hidden"
        >
          <div class="w-[3px] self-stretch rounded-full flex-shrink-0" :style="{ backgroundColor: statusBorderColor(apt.status) }"></div>
          <div class="w-8 h-8 rounded-full bg-[#FFD60A]/15 flex items-center justify-center flex-shrink-0 text-[#FFD60A] font-semibold text-[11px]">
            {{ apt.barber_initials }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-[14px] font-medium text-white truncate">{{ apt.customer_name }}</p>
            <p class="text-[12px] text-[#A1A1A1] truncate">{{ apt.service_name }}</p>
          </div>
          <div class="text-right flex-shrink-0">
            <p class="text-[15px] font-semibold tabular-nums text-white leading-none">{{ formatHora(apt.starts_at) }}</p>
            <p class="text-[11px] text-[#6B6B6B] mt-1 leading-none">{{ formatDateRelative(apt.starts_at) }}</p>
          </div>
        </Link>
      </div>

      <div v-else class="flex flex-col items-center justify-center py-12 bg-[#131313] border border-[#2A2A2A] rounded-[14px]">
        <CalendarOff :size="40" class="text-[#3D3D3D] mb-3" :stroke-width="1.5" />
        <p class="text-[14px] font-medium text-white">Nenhum agendamento à frente</p>
        <p class="text-[12px] text-[#A1A1A1] mt-1">
          {{ awaiting_appointments.length > 0 ? 'Ainda há atendimentos a concluir acima.' : 'Sua agenda está livre.' }}
        </p>
      </div>
    </div>

    <!-- RECEITA 7 DIAS -->
    <div v-if="canSeeFinance && revenue_week.length > 0" class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4">
      <div class="flex items-center justify-between mb-3">
        <h3 class="flex items-center gap-1.5 text-[13px] font-semibold text-white">
          <TrendingUp :size="14" :stroke-width="2" class="text-[#A1A1A1]" />
          Receita 7 dias
        </h3>
        <p class="text-[15px] font-bold text-white tabular-nums">{{ formatBRL(totalWeek) }}</p>
      </div>
      <div class="flex items-end gap-1.5 h-20">
        <div v-for="d in revenue_week" :key="d.date" class="flex-1 flex flex-col items-center gap-1 group">
          <div
            class="w-full rounded-t-[4px] bg-[#FFD60A]/80 group-hover:bg-[#FFD60A] transition-colors"
            :style="{ height: `${Math.max(2, (d.total / maxRevenue) * 100)}%` }"
            :title="formatBRL(d.total)"
          ></div>
          <span class="text-[9px] text-[#6B6B6B] uppercase">{{ d.label }}</span>
        </div>
      </div>
    </div>

    <!-- COMISSÕES PENDENTES (só quando há algo a pagar) -->
    <Link
      v-if="canSeeFinance && (pending_commissions ?? 0) > 0"
      href="/commissions"
      class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4 hover:border-[#3D3D3D] transition-colors flex items-center gap-3"
    >
      <div class="w-10 h-10 rounded-full bg-[#FFD60A]/15 flex items-center justify-center flex-shrink-0">
        <Wallet :size="18" :stroke-width="2" class="text-[#FFD60A]" />
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-[11px] text-[#A1A1A1] font-medium">Comissões a pagar</p>
        <p class="text-[20px] font-bold text-[#FFD60A] tabular-nums leading-none mt-1">{{ formatBRL(pending_commissions ?? 0) }}</p>
      </div>
      <ChevronRight :size="18" class="text-[#6B6B6B] flex-shrink-0" :stroke-width="1.75" />
    </Link>

  </div>
</template>
