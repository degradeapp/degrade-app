<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useFormatting } from '@/composables/useFormatting'
import AppLayout from '@/layouts/AppLayout.vue'
import { ChevronLeft, ChevronRight, CalendarOff, FileText } from 'lucide-vue-next'

defineOptions({
  layout: AppLayout,
})

type AppointmentStatus = 'scheduled' | 'confirmed' | 'in_progress' | 'awaiting_completion' | 'completed' | 'cancelled' | 'no_show'

interface AppointmentItem {
  id: number
  customer_name: string
  customer_initials: string
  barber_name: string
  barber_initials: string
  services: string
  starts_at: string
  duration_minutes: number
  price: number
  status: AppointmentStatus
  notes?: string | null
}

interface AppointmentGroup {
  primary: AppointmentItem
  conflicts: AppointmentItem[]
  startPx: number
}

interface Props {
  appointments: AppointmentItem[]
  date?: string
  business_hours?: { start: string; end: string }
}

const props = withDefaults(defineProps<Props>(), {
  appointments: () => [],
  business_hours: () => ({ start: '08:00', end: '22:00' }),
})

const { formatBRL, formatDateLong } = useFormatting()

// Timeline: 06:00 → 22:00. O card fica na posição EXATA do horário (top proporcional),
// alinhado ao eixo de horas à esquerda. Cada card tem altura fixa (CARD_HEIGHT) e a
// escala é 2.4px/min, então dois cards só "cabem" separados se começarem com pelo menos
// ~23min de diferença. Por isso o agrupamento funde num único card "+N" tanto quando há
// sobreposição REAL de horário quanto quando dois cards ficariam visualmente colados
// (ex.: serviços curtos quase em sequência) — garantindo que cards separados nunca colidam.
const TIMELINE_START_HOUR = 6
const TIMELINE_END_HOUR = 22
const PIXELS_PER_MINUTE = 2.4 // 30min = 72px; card 56px → ~16px de respiro entre cards
const HOUR_HEIGHT = 60 * PIXELS_PER_MINUTE // 144px
const TIMELINE_HOURS = TIMELINE_END_HOUR - TIMELINE_START_HOUR + 1 // 17
const TIMELINE_HEIGHT = TIMELINE_HOURS * HOUR_HEIGHT
const CARD_HEIGHT = 56 // tamanho cheio do card (a folga vem da escala, não de encolher)
// Distância mínima (px) entre os topos de dois cards separados. = altura do card + folga
// pra sombra/respiro. Abaixo disso eles se fundem num "+N" em vez de encavalar.
const MIN_CARD_GAP_PX = CARD_HEIGHT + 8 // 64px ≈ 26.6min
// Fuso da loja (vem do backend); exibe/calcula horários nele, não no do navegador.
const TENANT_TZ = ((usePage().props as any).tenant?.timezone as string) || 'America/Manaus'

// Recepção/barbeiro não veem dinheiro: o resumo de receita do dia (realizado/previsto)
// é só pra dono/gerente. A contagem de agendamentos continua pra todos.
const canSeeFinance = computed(() => {
  const r = (usePage().props as any).auth?.user?.role
  return r === 'owner' || r === 'manager'
})

const businessStartHour = computed(() => Number(props.business_hours.start.split(':')[0]))
const businessEndHour = computed(() => Number(props.business_hours.end.split(':')[0]))

const view = ref<'day' | 'week'>('day')
const selectedDate = ref(props.date ? new Date(props.date) : new Date())
const selectedStatus = ref<AppointmentStatus | 'all'>('all')

const conflictSheetOpen = ref(false)
const selectedGroup = ref<AppointmentGroup | null>(null)
const timelineRef = ref<HTMLElement | null>(null)

const statusFilters: { value: AppointmentStatus | 'all'; label: string }[] = [
  { value: 'all', label: 'Todos' },
  { value: 'scheduled', label: 'Agendados' },
  { value: 'in_progress', label: 'Em andamento' },
  { value: 'awaiting_completion', label: 'A concluir' },
  { value: 'completed', label: 'Concluídos' },
]

const getStatusColor = (status: AppointmentStatus): string => {
  const colors: Record<AppointmentStatus, string> = {
    scheduled: '#6B6B6B',
    confirmed: '#A1A1A1',
    in_progress: '#FFD60A',
    awaiting_completion: '#FB923C',
    completed: '#22C55E',
    cancelled: '#EF4444',
    no_show: '#F59E0B',
  }
  return colors[status] ?? '#6B6B6B'
}

// Borda esquerda do card: cor forte só para exceções. Status comuns (scheduled/
// confirmed) ficam neutros (#2A2A2A) para a cor voltar a significar "atenção".
const getBorderStyle = (status: AppointmentStatus): { borderLeftWidth: string; borderLeftColor: string } => {
  switch (status) {
    case 'in_progress':
      return { borderLeftWidth: '4px', borderLeftColor: '#FFD60A' }
    case 'awaiting_completion':
      return { borderLeftWidth: '4px', borderLeftColor: '#FB923C' } // pede ação: concluir
    case 'completed':
      return { borderLeftWidth: '3px', borderLeftColor: 'rgba(34, 197, 94, 0.5)' } // #22C55E 50%
    case 'cancelled':
      return { borderLeftWidth: '3px', borderLeftColor: '#EF4444' }
    case 'no_show':
      return { borderLeftWidth: '3px', borderLeftColor: '#F59E0B' }
    default: // scheduled, confirmed → neutro
      return { borderLeftWidth: '3px', borderLeftColor: '#2A2A2A' }
  }
}

// --- Helpers de tempo (parseiam a string ISO no offset do servidor/tenant) ---
const parseTime = (iso: string): { hours: number; minutes: number } => {
  const m = iso.match(/T(\d{2}):(\d{2})/)
  return { hours: m ? Number(m[1]) : 0, minutes: m ? Number(m[2]) : 0 }
}

const dateKeyFromIso = (iso: string): string => iso.slice(0, 10)

const localDateKey = (d: Date): string => {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

const formatHora = (iso: string): string => {
  const { hours, minutes } = parseTime(iso)
  return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`
}

// Posição vertical (px) a partir do início da timeline
const getOffsetPx = (iso: string): number => {
  const { hours, minutes } = parseTime(iso)
  return ((hours - TIMELINE_START_HOUR) * 60 + minutes) * PIXELS_PER_MINUTE
}

const isSameDay = (iso: string, d: Date) => dateKeyFromIso(iso) === localDateKey(d)

const dayAppointments = computed(() =>
  props.appointments
    .filter((apt) => isSameDay(apt.starts_at, selectedDate.value))
    .sort((a, b) => getOffsetPx(a.starts_at) - getOffsetPx(b.starts_at))
)

const filteredAppointments = computed(() => {
  if (selectedStatus.value === 'all') return dayAppointments.value
  // "Agendados" cobre scheduled e confirmed (pro dono são a mesma coisa).
  if (selectedStatus.value === 'scheduled') {
    return dayAppointments.value.filter((a) => a.status === 'scheduled' || a.status === 'confirmed')
  }
  return dayAppointments.value.filter((a) => a.status === selectedStatus.value)
})

// --- Agrupamento: cadeia de sobreposições vira 1 card + badge (interval merging) ---
// Um agendamento entra no grupo aberto quando:
//  (a) começa antes do MAIOR fim já acumulado nele (currentMaxEnd) → conflito real de
//      horário; ou
//  (b) começaria perto demais do topo do primário (< MIN_CARD_GAP_PX) → cards encavalariam
//      na tela mesmo sem conflito (serviços curtos quase em sequência).
// Como só o card primário é renderizado (os conflitos ficam empilhados sob ele), basta
// comparar com o topo do primário (primaryStartPx). A lista vem ordenada por início, então
// um item que não toca o grupo atual também não toca os anteriores.
const groupAppointments = (appts: AppointmentItem[]): AppointmentGroup[] => {
  const sorted = [...appts].sort(
    (a, b) => new Date(a.starts_at).getTime() - new Date(b.starts_at).getTime()
  )

  const groups: AppointmentGroup[] = []
  let current: AppointmentGroup | null = null
  let currentMaxEnd = 0
  let primaryStartPx = 0

  for (const apt of sorted) {
    const aptStart = new Date(apt.starts_at).getTime()
    const aptEnd = aptStart + apt.duration_minutes * 60000
    const aptStartPx = getOffsetPx(apt.starts_at)

    const timeOverlap = current && aptStart < currentMaxEnd // "<": encostar no fim não é conflito
    const tooClose = current && aptStartPx - primaryStartPx < MIN_CARD_GAP_PX // encavalaria na tela

    if (current && (timeOverlap || tooClose)) {
      current.conflicts.push(apt)
      currentMaxEnd = Math.max(currentMaxEnd, aptEnd)
      // primaryStartPx permanece o do primário (a âncora não se move).
    } else {
      current = {
        primary: apt,
        conflicts: [],
        startPx: aptStartPx,
      }
      groups.push(current)
      currentMaxEnd = aptEnd
      primaryStartPx = aptStartPx
    }
  }

  return groups
}

const groupedAppointments = computed(() => groupAppointments(filteredAppointments.value))

const onCardTap = (group: AppointmentGroup) => {
  if (group.conflicts.length > 0) {
    selectedGroup.value = group
    conflictSheetOpen.value = true
  } else {
    router.visit(`/appointments/${group.primary.id}`)
  }
}

const goToAppointment = (id: number) => {
  conflictSheetOpen.value = false
  router.visit(`/appointments/${id}`)
}

const daySummary = computed(() => {
  const active = dayAppointments.value.filter((a) => a.status !== 'cancelled' && a.status !== 'no_show')
  const realized = active.filter((a) => a.status === 'completed').reduce((sum, a) => sum + a.price, 0)
  const expected = active.filter((a) => a.status !== 'completed').reduce((sum, a) => sum + a.price, 0)
  return { count: dayAppointments.value.length, realized, expected }
})

const isToday = computed(() => localDateKey(new Date()) === localDateKey(selectedDate.value))
const isPastDay = computed(() => localDateKey(selectedDate.value) < localDateKey(new Date()))

const emptySubtitle = computed(() => {
  if (selectedStatus.value !== 'all') return 'Tente outro filtro.'
  if (isPastDay.value) return 'Nada foi agendado para este dia.'
  return 'Sua agenda está livre.'
})

// Relógio reativo: atualiza periodicamente para a linha e o badge "agora" sempre
// refletirem o minuto real (em vez de ficarem presos no horário do carregamento).
const now = ref(new Date())

// "Agora" no timezone do tenant (alinha com os cards)
const nowOffsetPx = computed(() => {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: TENANT_TZ,
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).formatToParts(now.value)
  const h = Number(parts.find((p) => p.type === 'hour')?.value ?? 0)
  const m = Number(parts.find((p) => p.type === 'minute')?.value ?? 0)
  return ((h - TIMELINE_START_HOUR) * 60 + m) * PIXELS_PER_MINUTE
})

// Horário atual (HH:MM) no fuso do tenant — para o badge da linha "agora".
// Mesmo padrão estático do nowOffsetPx (recalcula no render, sem setInterval).
const nowLabel = computed(() => {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: TENANT_TZ,
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(now.value)
  const h = parts.find((p) => p.type === 'hour')?.value ?? '00'
  const m = parts.find((p) => p.type === 'minute')?.value ?? '00'
  return `${h}:${m}`
})

const showNowLine = computed(
  () => isToday.value && nowOffsetPx.value >= 0 && nowOffsetPx.value <= TIMELINE_HEIGHT
)

// Hora relativa ("Em 2h" / "Em 30min"). Mesma base de minutos do now-line, então só
// faz sentido quando vendo HOJE; mostra apenas para agendamentos que ainda não começaram.
const nowOffsetMin = computed(() => nowOffsetPx.value / PIXELS_PER_MINUTE)
const relativeLabel = (iso: string, status: AppointmentStatus): string => {
  if (!isToday.value) return ''
  // Só para agendamentos por vir (scheduled/confirmed); nunca completed/cancelled/no_show/in_progress.
  if (status !== 'scheduled' && status !== 'confirmed') return ''
  const diff = Math.round(getOffsetPx(iso) / PIXELS_PER_MINUTE - nowOffsetMin.value)
  if (diff <= 0) return ''
  if (diff < 60) return `Em ${diff}min`
  return `Em ${Math.floor(diff / 60)}h`
}

// Hora cheia mais próxima do "agora": escondemos esse label da coluna esquerda
// pra ele não colidir com o badge "HH:MM". null quando a linha "agora" não aparece.
const hiddenHourLabel = computed(() =>
  showNowLine.value ? TIMELINE_START_HOUR + Math.round(nowOffsetMin.value / 60) : null
)

// Zonas não comerciais (overlay escurecido)
const morningBlockedPx = computed(() => Math.max(0, (businessStartHour.value - TIMELINE_START_HOUR) * 60 * PIXELS_PER_MINUTE))
const eveningStartPx = computed(() => (businessEndHour.value - TIMELINE_START_HOUR) * 60 * PIXELS_PER_MINUTE)
const eveningBlockedPx = computed(() => Math.max(0, TIMELINE_HEIGHT - eveningStartPx.value))

// Date navigation
const dateLabel = computed(() => {
  const d = selectedDate.value
  const today = new Date()
  const tomorrow = new Date(today)
  tomorrow.setDate(today.getDate() + 1)
  const yesterday = new Date(today)
  yesterday.setDate(today.getDate() - 1)

  if (localDateKey(d) === localDateKey(today)) return 'Hoje'
  if (localDateKey(d) === localDateKey(tomorrow)) return 'Amanhã'
  if (localDateKey(d) === localDateKey(yesterday)) return 'Ontem'

  const weekday = d.toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', '')
  const day = d.getDate()
  const month = d.toLocaleDateString('pt-BR', { month: 'short' }).replace('.', '')
  const cap = (s: string) => s.charAt(0).toUpperCase() + s.slice(1)
  return `${cap(weekday)}, ${day} ${month}`
})

const prevDay = () => {
  const d = new Date(selectedDate.value)
  d.setDate(d.getDate() - 1)
  selectedDate.value = d
}

const nextDay = () => {
  const d = new Date(selectedDate.value)
  d.setDate(d.getDate() + 1)
  selectedDate.value = d
}

const goToday = () => {
  selectedDate.value = new Date()
}

const prevWeek = () => {
  const d = new Date(selectedDate.value)
  d.setDate(d.getDate() - 7)
  selectedDate.value = d
}

const nextWeek = () => {
  const d = new Date(selectedDate.value)
  d.setDate(d.getDate() + 7)
  selectedDate.value = d
}

// Week strip
const weekDays = computed(() => {
  const base = new Date(selectedDate.value)
  const dayOfWeek = base.getDay()
  const monday = new Date(base)
  monday.setDate(base.getDate() - ((dayOfWeek + 6) % 7))

  const labels = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']
  return Array.from({ length: 7 }, (_, i) => {
    const date = new Date(monday)
    date.setDate(monday.getDate() + i)
    const count = props.appointments.filter((apt) => isSameDay(apt.starts_at, date)).length
    return {
      date,
      label: labels[i],
      dayNum: date.getDate(),
      count,
      isSelected: localDateKey(date) === localDateKey(selectedDate.value),
    }
  })
})

const weekLabel = computed(() => {
  const days = weekDays.value
  if (!days.length) return ''
  const a = days[0].date
  const b = days[6].date
  const mon = (d: Date) => d.toLocaleDateString('pt-BR', { month: 'short' }).replace('.', '')
  return a.getMonth() === b.getMonth()
    ? `${a.getDate()} a ${b.getDate()} de ${mon(a)}`
    : `${a.getDate()} ${mon(a)} a ${b.getDate()} ${mon(b)}`
})

const hourLabels = computed(() =>
  Array.from({ length: TIMELINE_HOURS }, (_, i) => {
    const hour = TIMELINE_START_HOUR + i
    return {
      hour,
      label: `${String(hour).padStart(2, '0')}:00`,
      commercial: hour >= businessStartHour.value && hour < businessEndHour.value,
    }
  })
)

// Relógio reativo (tick a cada 30s) + scroll automático até perto do "agora" ao abrir
let nowTimer: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  nowTimer = setInterval(() => { now.value = new Date() }, 30000)
  if (!isToday.value || !timelineRef.value) return
  const target = Math.max(0, nowOffsetPx.value - 80)
  timelineRef.value.scrollTo({ top: target, behavior: 'smooth' })
})
onUnmounted(() => { if (nowTimer) clearInterval(nowTimer) })
</script>

<template>
  <div class="flex flex-col h-full">
    <!-- TOPO FIXO -->
    <div class="px-4 pt-6 flex-shrink-0">
      <!-- HEADER: título + toggle -->
      <div class="flex items-center justify-between mb-5">
        <h1 class="text-[22px] font-bold text-white">Agenda</h1>
        <div class="flex items-center gap-2">
          <button
            @click="goToday"
            :disabled="isToday"
            class="h-9 px-3 rounded-[10px] bg-[#1A1A1A] border border-[#2A2A2A] text-[13px] font-medium transition-colors disabled:opacity-40 disabled:cursor-default"
            :class="isToday ? 'text-[#6B6B6B]' : 'text-[#A1A1A1] hover:text-white hover:border-[#3D3D3D]'"
          >
            Hoje
          </button>
          <div class="inline-flex p-1 bg-[#1A1A1A] border border-[#2A2A2A] rounded-[10px]">
          <button
            @click="view = 'day'"
            :class="[
              'px-4 h-9 rounded-[7px] text-[13px] font-medium transition-colors',
              view === 'day' ? 'bg-[#FFD60A] text-[#0A0A0A]' : 'text-[#A1A1A1] hover:text-white',
            ]"
          >
            Dia
          </button>
          <button
            @click="view = 'week'"
            :class="[
              'px-4 h-9 rounded-[7px] text-[13px] font-medium transition-colors',
              view === 'week' ? 'bg-[#FFD60A] text-[#0A0A0A]' : 'text-[#A1A1A1] hover:text-white',
            ]"
          >
            Semana
          </button>
          </div>
        </div>
      </div>

      <!-- DAY VIEW: navegação de data compacta -->
      <div v-if="view === 'day'" class="flex items-center justify-between mb-4">
        <button
          @click="prevDay"
          class="w-9 h-9 rounded-[10px] bg-[#1A1A1A] border border-[#2A2A2A] flex items-center justify-center text-[#A1A1A1] hover:text-white hover:border-[#3D3D3D] transition-colors"
          aria-label="Dia anterior"
        >
          <ChevronLeft :size="18" :stroke-width="1.75" />
        </button>
        <button @click="goToday" class="text-center flex-1">
          <p class="text-[16px] font-semibold text-white leading-none">{{ dateLabel }}</p>
          <p class="text-[11px] text-[#6B6B6B] mt-1 leading-none">{{ formatDateLong(selectedDate) }}</p>
        </button>
        <button
          @click="nextDay"
          class="w-9 h-9 rounded-[10px] bg-[#1A1A1A] border border-[#2A2A2A] flex items-center justify-center text-[#A1A1A1] hover:text-white hover:border-[#3D3D3D] transition-colors"
          aria-label="Próximo dia"
        >
          <ChevronRight :size="18" :stroke-width="1.75" />
        </button>
      </div>

      <!-- WEEK VIEW: navegação de semana + strip de 7 dias -->
      <div v-else class="mb-4">
        <div class="flex items-center justify-between mb-2">
          <button
            @click="prevWeek"
            class="w-9 h-9 rounded-[10px] bg-[#1A1A1A] border border-[#2A2A2A] flex items-center justify-center text-[#A1A1A1] hover:text-white hover:border-[#3D3D3D] transition-colors"
            aria-label="Semana anterior"
          >
            <ChevronLeft :size="18" :stroke-width="1.75" />
          </button>
          <button @click="goToday" class="text-center flex-1">
            <p class="text-[14px] font-semibold text-white leading-none">{{ weekLabel }}</p>
          </button>
          <button
            @click="nextWeek"
            class="w-9 h-9 rounded-[10px] bg-[#1A1A1A] border border-[#2A2A2A] flex items-center justify-center text-[#A1A1A1] hover:text-white hover:border-[#3D3D3D] transition-colors"
            aria-label="Próxima semana"
          >
            <ChevronRight :size="18" :stroke-width="1.75" />
          </button>
        </div>
        <div class="grid grid-cols-7 gap-1.5">
          <button
            v-for="wd in weekDays"
            :key="wd.date.toISOString()"
            @click="selectedDate = wd.date"
          :class="[
            'flex flex-col items-center py-2 rounded-[10px] border transition-colors',
            wd.isSelected ? 'bg-[#FFD60A] border-[#FFD60A]' : 'bg-[#131313] border-[#2A2A2A] hover:border-[#3D3D3D]',
          ]"
        >
          <span :class="['text-[10px] font-medium uppercase', wd.isSelected ? 'text-[#0A0A0A]/70' : 'text-[#6B6B6B]']">
            {{ wd.label }}
          </span>
          <span :class="['text-[16px] font-bold tabular-nums mt-0.5', wd.isSelected ? 'text-[#0A0A0A]' : 'text-white']">
            {{ wd.dayNum }}
          </span>
          <span
            :class="[
              'w-1 h-1 rounded-full mt-1',
              wd.count > 0 ? (wd.isSelected ? 'bg-[#0A0A0A]' : 'bg-[#FFD60A]') : 'bg-transparent',
            ]"
          ></span>
          </button>
        </div>
      </div>

      <!-- RESUMO DO DIA (uma linha) -->
      <div v-if="daySummary.count > 0" class="flex items-center flex-wrap gap-x-3 gap-y-1 mb-4 text-[12px]">
        <span class="text-[#A1A1A1]">
          <span class="font-semibold text-white tabular-nums">{{ daySummary.count }}</span>
          {{ daySummary.count === 1 ? 'agendamento' : 'agendamentos' }}
        </span>
        <template v-if="canSeeFinance && daySummary.realized > 0">
          <span class="text-[#3D3D3D]">·</span>
          <span class="text-[#A1A1A1]">
            <span class="font-semibold text-[#22C55E] tabular-nums">{{ formatBRL(daySummary.realized) }}</span> realizado
          </span>
        </template>
        <template v-if="canSeeFinance && daySummary.expected > 0">
          <span class="text-[#3D3D3D]">·</span>
          <span class="text-[#A1A1A1]">
            <span class="font-semibold text-[#22C55E] tabular-nums">{{ formatBRL(daySummary.expected) }}</span> previsto
          </span>
        </template>
      </div>

      <!-- FILTROS DE STATUS -->
      <div class="overflow-x-auto -mx-4 px-4 mb-4 scrollbar-none">
        <div class="flex gap-2 w-max">
          <button
            v-for="filter in statusFilters"
            :key="filter.value"
            @click="selectedStatus = filter.value"
            :class="[
              'px-3.5 h-8 rounded-full text-[12px] font-medium whitespace-nowrap flex-shrink-0 border transition-colors',
              selectedStatus === filter.value
                ? 'bg-[#FFD60A] border-[#FFD60A] text-[#0A0A0A]'
                : 'bg-transparent border-[#2A2A2A] text-[#A1A1A1] hover:text-white hover:border-[#3D3D3D]',
            ]"
          >
            {{ filter.label }}
          </button>
        </div>
      </div>
    </div>

    <!-- TIMELINE (scroll interno) -->
    <div
      v-if="filteredAppointments.length > 0"
      ref="timelineRef"
      class="flex-1 overflow-y-auto scrollbar-none px-4 pb-24"
    >
      <div class="relative" :style="{ height: `${TIMELINE_HEIGHT}px` }">
        <!-- Grade de horas -->
        <div v-for="slot in hourLabels" :key="slot.hour" class="relative flex" :style="{ height: `${HOUR_HEIGHT}px` }">
          <div class="w-12 flex-shrink-0 relative">
            <span
              :class="[
                'absolute -top-[6px] left-0 text-[11px] tabular-nums font-medium leading-none transition-opacity',
                slot.commercial ? 'text-[#A1A1A1]' : 'text-[#6B6B6B]',
                slot.hour === hiddenHourLabel ? 'opacity-0' : '',
              ]"
            >
              {{ slot.label }}
            </span>
          </div>
          <div class="flex-1 border-t border-[#1F1F1F]"></div>
        </div>

        <!-- Zonas não comerciais (hachura diagonal discreta) -->
        <div
          v-if="morningBlockedPx > 0"
          class="absolute left-12 right-0 hatch-zone pointer-events-none"
          :style="{ top: '0px', height: `${morningBlockedPx}px` }"
        ></div>
        <div
          v-if="eveningBlockedPx > 0"
          class="absolute left-12 right-0 hatch-zone pointer-events-none"
          :style="{ top: `${eveningStartPx}px`, height: `${eveningBlockedPx}px` }"
        ></div>

        <!-- Cards de agendamento -->
        <div
          v-for="group in groupedAppointments"
          :key="group.primary.id"
          class="absolute left-12 right-2 z-20"
          :style="{ top: `${group.startPx}px`, height: `${CARD_HEIGHT}px` }"
          @click="onCardTap(group)"
        >
          <!-- Sombra de pilha -->
          <div
            v-if="group.conflicts.length > 0"
            class="absolute inset-0 bg-[#131313] border border-[#2A2A2A] rounded-[10px]"
            style="transform: translate(4px, 4px); opacity: 0.5"
          ></div>

          <!-- Card principal -->
          <div
            class="relative h-full border border-[#2A2A2A] rounded-[10px] px-3 flex items-center gap-2.5 overflow-hidden cursor-pointer hover:border-[#3D3D3D] transition-colors"
            :class="group.primary.status === 'in_progress' ? 'bg-[#1A1A1A]' : 'bg-[#131313]'"
            :style="getBorderStyle(group.primary.status)"
          >
            <div class="w-7 h-7 rounded-full bg-[#1A1A1A] flex items-center justify-center text-[10px] font-semibold text-[#FFD60A] flex-shrink-0">
              {{ group.primary.barber_initials }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[13px] font-medium text-white truncate leading-tight">{{ group.primary.customer_name }}</p>
              <!-- Linha 2: serviço (trunca) + badge +N inline, longe da coluna de horários -->
              <div class="flex items-center mt-0.5 min-w-0">
                <p class="text-[11px] text-[#A1A1A1] truncate leading-tight min-w-0">{{ group.primary.services }}</p>
                <span
                  v-if="group.conflicts.length > 0"
                  class="ml-2 flex-shrink-0 px-1.5 h-[18px] rounded-full bg-[#FFD60A] text-[#0A0A0A] text-[10px] font-bold flex items-center justify-center tabular-nums"
                >
                  +{{ group.conflicts.length }}
                </span>
                <FileText
                  v-if="group.primary.notes"
                  :size="14"
                  :stroke-width="1.75"
                  class="ml-1.5 flex-shrink-0 text-[#6B6B6B]"
                  aria-label="Tem observações"
                />
              </div>
            </div>
            <div class="text-right flex-shrink-0">
              <p class="text-[15px] font-semibold text-white tabular-nums leading-none">{{ formatHora(group.primary.starts_at) }}</p>
              <p
                v-if="relativeLabel(group.primary.starts_at, group.primary.status)"
                class="text-[11px] text-[#6B6B6B] tabular-nums leading-none mt-1"
              >
                {{ relativeLabel(group.primary.starts_at, group.primary.status) }}
              </p>
            </div>
          </div>
        </div>

        <!-- Linha "agora": z-30 acima dos cards (z-20) -> visível atravessando a timeline inteira -->
        <div
          v-if="showNowLine"
          class="absolute left-12 right-0 h-px bg-[#E36F4D] z-30"
          :style="{ top: `${nowOffsetPx}px` }"
        >
          <div class="absolute -left-1 -top-1 w-2 h-2 rounded-full bg-[#E36F4D]"></div>
          <!-- Badge "agora": pill compacto na coluna de horários (≤48px), nunca sobre os cards -->
          <div
            class="absolute -left-12 top-0 -translate-y-1/2 z-30 px-1.5 py-0.5 rounded-md bg-[#E36F4D] text-white text-[10px] font-medium leading-none tabular-nums pointer-events-none"
          >
            {{ nowLabel }}
          </div>
        </div>
      </div>
    </div>

    <!-- EMPTY STATE -->
    <div v-else class="flex-1 flex flex-col items-center justify-center px-4 pb-24">
      <CalendarOff :size="32" :stroke-width="1.75" class="text-[#6B6B6B] mb-3" />
      <p class="text-[15px] font-medium text-white mb-1">
        {{ selectedStatus === 'all' ? 'Nenhum agendamento' : 'Nenhum agendamento neste filtro' }}
      </p>
      <p class="text-[13px] text-[#A1A1A1] text-center">
        {{ emptySubtitle }}
      </p>
    </div>

    <!-- BOTTOM SHEET DE CONFLITOS -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-300"
        leave-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="conflictSheetOpen"
          class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40"
          @click="conflictSheetOpen = false"
        ></div>
      </Transition>

      <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        leave-active-class="transition-transform duration-300 ease-out"
        enter-from-class="translate-y-full"
        enter-to-class="translate-y-0"
        leave-from-class="translate-y-0"
        leave-to-class="translate-y-full"
      >
        <div
          v-if="conflictSheetOpen && selectedGroup"
          class="fixed bottom-0 left-0 right-0 bg-[#131313] rounded-t-[20px] z-50 pb-[env(safe-area-inset-bottom)]"
        >
          <div class="flex justify-center pt-2 pb-1">
            <div class="w-9 h-1 rounded-full bg-[#3D3D3D]"></div>
          </div>

          <div class="px-5 pt-3 pb-4 border-b border-[#1F1F1F]">
            <h3 class="text-[17px] font-semibold text-white">
              {{ selectedGroup.conflicts.length + 1 }} agendamentos neste horário
            </h3>
            <p class="text-[12px] text-[#A1A1A1] mt-0.5">Toque em um agendamento para ver detalhes</p>
          </div>

          <div class="max-h-[60vh] overflow-y-auto px-5 py-2">
            <button
              @click="goToAppointment(selectedGroup.primary.id)"
              class="w-full bg-[#1A1A1A] border border-[#2A2A2A] rounded-[10px] p-3 mb-2 flex items-center gap-3 hover:border-[#3D3D3D] transition-colors text-left"
              :style="{ borderLeftWidth: '3px', borderLeftColor: getStatusColor(selectedGroup.primary.status) }"
            >
              <div class="w-8 h-8 rounded-full bg-[#131313] flex items-center justify-center text-[11px] font-semibold text-[#FFD60A] flex-shrink-0">
                {{ selectedGroup.primary.barber_initials }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-[14px] font-medium text-white truncate">{{ selectedGroup.primary.customer_name }}</p>
                <p class="text-[12px] text-[#A1A1A1] truncate">{{ selectedGroup.primary.services }}</p>
              </div>
              <div class="text-right flex-shrink-0">
                <p class="text-[13px] font-semibold text-white tabular-nums">{{ formatHora(selectedGroup.primary.starts_at) }}</p>
              </div>
            </button>

            <button
              v-for="conflict in selectedGroup.conflicts"
              :key="conflict.id"
              @click="goToAppointment(conflict.id)"
              class="w-full bg-[#1A1A1A] border border-[#2A2A2A] rounded-[10px] p-3 mb-2 flex items-center gap-3 hover:border-[#3D3D3D] transition-colors text-left"
              :style="{ borderLeftWidth: '3px', borderLeftColor: getStatusColor(conflict.status) }"
            >
              <div class="w-8 h-8 rounded-full bg-[#131313] flex items-center justify-center text-[11px] font-semibold text-[#FFD60A] flex-shrink-0">
                {{ conflict.barber_initials }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-[14px] font-medium text-white truncate">{{ conflict.customer_name }}</p>
                <p class="text-[12px] text-[#A1A1A1] truncate">{{ conflict.services }}</p>
              </div>
              <div class="text-right flex-shrink-0">
                <p class="text-[13px] font-semibold text-white tabular-nums">{{ formatHora(conflict.starts_at) }}</p>
              </div>
            </button>
          </div>

          <div class="px-5 pt-2 pb-4">
            <button
              @click="conflictSheetOpen = false"
              class="w-full h-11 bg-transparent border border-[#2A2A2A] rounded-[10px] text-[14px] font-medium text-[#A1A1A1] hover:text-white hover:border-[#3D3D3D] transition-colors"
            >
              Fechar
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.scrollbar-none {
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.scrollbar-none::-webkit-scrollbar {
  display: none;
}

/* Zona fora do expediente: hachura diagonal sutil (perceptível sobre #0A0A0A, sem pesar) */
.hatch-zone {
  background-image: repeating-linear-gradient(
    -45deg,
    rgba(255, 255, 255, 0.025) 0px,
    rgba(255, 255, 255, 0.025) 1px,
    transparent 1px,
    transparent 9px
  );
}
</style>
