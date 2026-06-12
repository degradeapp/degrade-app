<template>
  <AppLayout :title="barber?.name || 'Barbeiro'" show-back-button back-href="/barbers">
    <div v-if="loading" class="p-4 space-y-3">
      <Skeleton height="120px" />
      <Skeleton height="60px" />
      <Skeleton height="80px" />
    </div>

    <div v-else-if="!barber" class="text-center py-16 text-[13px] text-[#6B6B6B]">
      Barbeiro não encontrado.
    </div>

    <div v-else class="pb-32 animate-enter">
      <!-- HERO -->
      <div class="relative overflow-hidden bg-gradient-to-br from-[#1C1C18] to-[#131313] border-b border-[#2A2A2A] p-5">
        <div class="relative flex items-center gap-4">
          <div class="w-16 h-16 rounded-full overflow-hidden bg-[#1A1A1A] border border-[#2A2A2A] flex items-center justify-center text-[22px] font-bold text-[#FFD60A] flex-shrink-0">
            <img v-if="barber.photo_url" :src="barber.photo_url" alt="" class="w-full h-full object-cover" />
            <span v-else>{{ initials }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <h1 class="text-[20px] font-bold text-white truncate">{{ barber.name }}</h1>
              <span v-if="!barber.is_active" class="text-[10px] px-1.5 py-0.5 rounded-full bg-[#3D3D3D]/40 text-[#A1A1A1] font-medium flex-shrink-0">
                Inativo
              </span>
            </div>
            <p class="text-[13px] text-[#A1A1A1] mt-0.5">{{ formatPhone(barber.phone) }}</p>
            <span class="inline-block mt-2 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-[#FFD60A]/15 text-[#FFD60A] tabular-nums">
              Comissão {{ Number(barber.default_commission_percentage ?? 0).toFixed(0) }}%
            </span>
          </div>
        </div>
      </div>

      <!-- Ações -->
      <div class="p-4 grid grid-cols-2 gap-2">
        <Link
          :href="`/barbers/${barber.id}/edit`"
          class="h-12 rounded-[12px] bg-[#FFD60A] text-[14px] font-bold text-[#0A0A0A] flex items-center justify-center gap-1.5 hover:bg-[#FFE066] active:scale-[0.98] transition-all"
        >
          <Pencil :size="15" :stroke-width="2.25" />
          Editar
        </Link>
        <Link
          :href="`/barbers/${barber.id}/schedule`"
          class="h-12 rounded-[12px] border border-[#2A2A2A] text-[14px] font-medium text-[#A1A1A1] hover:text-white hover:border-[#3D3D3D] active:scale-[0.98] flex items-center justify-center gap-1.5 transition-all"
        >
          <Clock :size="15" :stroke-width="2" />
          Horários
        </Link>
      </div>

      <!-- Horário semanal -->
      <div class="px-4 mt-2">
        <h3 class="flex items-center gap-1.5 text-[13px] font-semibold text-white mb-3">
          <CalendarClock :size="14" :stroke-width="2" class="text-[#A1A1A1]" />
          Horário semanal
        </h3>
        <div class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] divide-y divide-[#1F1F1F] overflow-hidden">
          <div
            v-for="d in days"
            :key="d.value"
            class="flex items-center justify-between px-4 py-2.5"
          >
            <span class="text-[13px] w-14" :class="isOpen(d.value) ? 'text-[#A1A1A1]' : 'text-[#4D4D4D]'">{{ d.label }}</span>
            <span class="text-[13px] font-medium tabular-nums" :class="isOpen(d.value) ? 'text-white' : 'text-[#4D4D4D]'">{{ scheduleFor(d.value) }}</span>
          </div>
        </div>
      </div>

      <!-- Próximos agendamentos -->
      <div class="px-4 mt-5">
        <h3 class="flex items-center gap-1.5 text-[13px] font-semibold text-white mb-3">
          <CalendarDays :size="14" :stroke-width="2" class="text-[#A1A1A1]" />
          Próximos agendamentos
        </h3>
        <div v-if="upcomingAppointments.length === 0" class="text-center py-6 text-[13px] text-[#6B6B6B]">
          Nenhum agendamento futuro.
        </div>
        <div v-else class="space-y-2 stagger">
          <Link
            v-for="apt in upcomingAppointments"
            :key="apt.id"
            :href="`/appointments/${apt.id}`"
            class="block bg-[#131313] border border-[#2A2A2A] rounded-[12px] p-3 hover:border-[#3D3D3D] active:scale-[0.99] transition-all"
          >
            <div class="flex items-center justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-[13px] font-medium text-white truncate">{{ apt.customer?.name ?? '—' }}</p>
                <p class="text-[11px] text-[#A1A1A1] mt-0.5 tabular-nums">{{ formatDateTime(apt.starts_at) }}</p>
              </div>
              <span class="text-[12px] font-semibold text-[#FFD60A] tabular-nums">{{ formatBRL(apt.total_price) }}</span>
            </div>
          </Link>
        </div>
      </div>

      <!-- Ativo + excluir -->
      <div class="p-4 pt-6 space-y-2">
        <label class="flex items-center gap-3 bg-[#131313] border border-[#2A2A2A] rounded-[12px] p-4 cursor-pointer active:scale-[0.99] transition-transform">
          <input
            v-model="barber.is_active"
            type="checkbox"
            class="w-5 h-5 accent-[#FFD60A]"
            @change="updateStatus"
          />
          <div>
            <p class="text-[14px] font-medium text-white">Barbeiro ativo</p>
            <p class="text-[12px] text-[#A1A1A1] mt-0.5">Aparece como opção em agendamentos</p>
          </div>
        </label>

        <Button variant="danger" class="w-full" :loading="isDeleting" loading-text="Excluindo..." @click="onDeleteClick">
          Excluir barbeiro
        </Button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { CalendarClock, CalendarDays, Pencil, Clock } from 'lucide-vue-next'
import AppLayout from '../../layouts/AppLayout.vue'
import Button from '../../components/Button.vue'
import Skeleton from '../../components/Skeleton.vue'
import { useFormatting } from '../../composables/useFormatting'
import { useConfirm } from '../../composables/useConfirm'
import { useToast } from '../../composables/useToast'

const page = usePage()
const barberId = page.url.split('/').filter(Boolean)[1]

const { formatBRL, formatPhone } = useFormatting()
const { ask } = useConfirm()
const toast = useToast()

const loading = ref(true)
const isDeleting = ref(false)
const barber = ref<any>(null)
const schedule = ref<any[]>([])
const upcomingAppointments = ref<any[]>([])

const days = [
  { value: 0, label: 'Dom' },
  { value: 1, label: 'Seg' },
  { value: 2, label: 'Ter' },
  { value: 3, label: 'Qua' },
  { value: 4, label: 'Qui' },
  { value: 5, label: 'Sex' },
  { value: 6, label: 'Sáb' },
]

const initials = computed(() =>
  (barber.value?.name ?? '').trim().split(/\s+/).map((p: string) => p[0]).slice(0, 2).join('').toUpperCase()
)

const xsrf = () =>
  decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

const isOpen = (dayOfWeek: number) => !!schedule.value.find((x) => x.day_of_week === dayOfWeek)

const scheduleFor = (dayOfWeek: number) => {
  const s = schedule.value.find((x) => x.day_of_week === dayOfWeek)
  if (!s) return 'Fechado'
  return `${(s.start_time ?? '').slice(0, 5)} às ${(s.end_time ?? '').slice(0, 5)}`
}

const formatDateTime = (iso?: string) => {
  if (!iso) return ''
  return new Date(iso).toLocaleString('pt-BR', {
    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
  })
}

const updateStatus = async () => {
  await fetch(`/api/barbers/${barber.value.id}`, {
    method: 'PUT',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-XSRF-TOKEN': xsrf(),
    },
    body: JSON.stringify({ is_active: barber.value.is_active }),
  })
}

const onDeleteClick = async () => {
  const ok = await ask(
    'Excluir barbeiro?',
    'Ele sai da equipe e da agenda de vez. As comissões e os relatórios antigos continuam com o nome dele. Se quiser poder reativar depois, use Desativar.',
    { confirmText: 'Excluir', destructive: true }
  )
  if (!ok) return

  isDeleting.value = true
  try {
    const res = await fetch(`/api/barbers/${barber.value.id}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrf(),
      },
    })
    if (res.ok || res.status === 204) {
      router.visit('/barbers')
    } else {
      const b = await res.json().catch(() => ({}))
      toast.error(b.message ?? 'Não foi possível excluir.')
    }
  } finally {
    isDeleting.value = false
  }
}

onMounted(async () => {
  try {
    const [barberRes, appointmentsRes] = await Promise.all([
      fetch(`/api/barbers/${barberId}`, { headers: { Accept: 'application/json' } }),
      fetch(`/api/appointments?barber_id=${barberId}`, { headers: { Accept: 'application/json' } }),
    ])
    if (barberRes.ok) {
      const json = await barberRes.json()
      const data = json.data ?? json
      barber.value = data
      schedule.value = data.schedules ?? []
    }
    if (appointmentsRes.ok) {
      const all = (await appointmentsRes.json()).data ?? []
      const now = new Date().getTime()
      upcomingAppointments.value = all.filter((a: any) => new Date(a.starts_at).getTime() > now).slice(0, 5)
    }
  } finally {
    loading.value = false
  }
})
</script>
