<template>
  <AppLayout :title="customer?.name || 'Cliente'" show-back-button back-href="/customers">
    <div v-if="loading" class="p-4 space-y-3">
      <Skeleton height="120px" />
      <Skeleton height="60px" />
      <Skeleton height="60px" />
    </div>

    <div v-else-if="!customer" class="text-center py-16 text-[13px] text-[#6B6B6B]">
      Cliente não encontrado.
    </div>

    <div v-else class="pb-32 animate-enter">
      <!-- HERO -->
      <div class="relative overflow-hidden bg-gradient-to-br from-[#1C1C18] to-[#131313] border-b border-[#2A2A2A] p-5">
        <div class="relative">
          <div class="flex items-center gap-4 mb-5">
            <div class="w-16 h-16 rounded-full bg-[#1A1A1A] border border-[#2A2A2A] flex items-center justify-center text-[22px] font-bold text-[#FFD60A] flex-shrink-0">
              {{ initials }}
            </div>
            <div class="flex-1 min-w-0">
              <h1 class="text-[20px] font-bold text-white truncate">{{ customer.name }}</h1>
              <p class="text-[13px] text-[#A1A1A1] mt-0.5">{{ customer.phone ? formatPhone(customer.phone) : 'Sem telefone' }}</p>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-2">
            <div class="bg-[#0A0A0A]/60 border border-[#2A2A2A] rounded-[12px] p-3 text-center">
              <p class="text-[10px] uppercase tracking-[0.08em] text-[#6B6B6B]">Visitas</p>
              <p class="text-[18px] font-bold text-white tabular-nums mt-1">{{ customer.total_visits ?? 0 }}</p>
            </div>
            <div class="bg-[#0A0A0A]/60 border border-[#2A2A2A] rounded-[12px] p-3 text-center">
              <p class="text-[10px] uppercase tracking-[0.08em] text-[#6B6B6B]">Gasto</p>
              <p class="text-[18px] font-bold text-[#FFD60A] tabular-nums mt-1">{{ formatBRL(customer.total_spent ?? 0) }}</p>
            </div>
            <div class="bg-[#0A0A0A]/60 border border-[#2A2A2A] rounded-[12px] p-3 text-center">
              <p class="text-[10px] uppercase tracking-[0.08em] text-[#6B6B6B]">Última</p>
              <p class="text-[14px] font-semibold text-white mt-1">{{ customer.last_visit_at ? formatRelative(customer.last_visit_at) : 'Nunca' }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Ações -->
      <div class="p-4 grid grid-cols-3 gap-2">
        <Link
          :href="`/appointments/create?customer_id=${customer.id}`"
          class="h-12 rounded-[12px] bg-[#FFD60A] text-[13px] font-bold text-[#0A0A0A] flex items-center justify-center gap-1 hover:bg-[#FFE066] active:scale-[0.98] transition-all"
        >
          <CalendarPlus :size="15" :stroke-width="2.25" />
          Agendar
        </Link>
        <a
          v-if="customer.phone"
          :href="`https://wa.me/55${customer.phone.replace(/\D/g, '')}`"
          target="_blank"
          rel="noopener"
          class="h-12 rounded-[12px] border border-[#22C55E]/40 text-[13px] font-medium text-[#22C55E] hover:bg-[#22C55E]/10 active:scale-[0.98] flex items-center justify-center gap-1 transition-all"
        >
          <MessageCircle :size="14" :stroke-width="1.75" />
          WhatsApp
        </a>
        <Link
          :href="`/customers/${customer.id}/edit`"
          class="h-12 rounded-[12px] border border-[#2A2A2A] text-[13px] font-medium text-[#A1A1A1] hover:text-white hover:border-[#3D3D3D] active:scale-[0.98] flex items-center justify-center gap-1 transition-all"
        >
          <Pencil :size="14" :stroke-width="2" />
          Editar
        </Link>
      </div>

      <!-- Observações -->
      <div v-if="customer.notes" class="px-4 mt-2">
        <h3 class="flex items-center gap-1.5 text-[13px] font-semibold text-white mb-2">
          <FileText :size="14" :stroke-width="2" class="text-[#A1A1A1]" />
          Observações
        </h3>
        <div class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4">
          <p class="text-[13px] text-[#F5F5F5] whitespace-pre-wrap break-words leading-relaxed">{{ customer.notes }}</p>
        </div>
      </div>

      <!-- Histórico -->
      <div class="px-4 mt-5">
        <h3 class="flex items-center gap-1.5 text-[13px] font-semibold text-white mb-3">
          <History :size="14" :stroke-width="2" class="text-[#A1A1A1]" />
          Histórico
        </h3>

        <div v-if="appointments.length === 0" class="text-center py-8 text-[13px] text-[#6B6B6B]">
          Nenhum agendamento registrado.
        </div>

        <div v-else class="space-y-2 stagger">
          <Link
            v-for="apt in appointments"
            :key="apt.id"
            :href="`/appointments/${apt.id}`"
            class="block bg-[#131313] border border-[#2A2A2A] rounded-[12px] p-3 hover:border-[#3D3D3D] active:scale-[0.99] transition-all"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1 space-y-0.5 leading-tight">
                <p class="text-[13px] font-medium text-white truncate">{{ apt.barber?.name ?? '—' }}</p>
                <p class="text-[11px] text-[#6B6B6B]">{{ formatDate(apt.starts_at) }}</p>
                <p class="text-[12px] text-[#FFD60A] font-semibold tabular-nums">{{ formatBRL(apt.total_price) }}</p>
              </div>
              <span
                class="text-[10px] px-2 py-0.5 rounded-full font-medium flex-shrink-0"
                :style="{ backgroundColor: statusColor(apt.status) + '26', color: statusColor(apt.status) }"
              >
                {{ apt.status_label ?? apt.status }}
              </span>
            </div>
          </Link>
        </div>
      </div>

      <!-- Excluir -->
      <div class="p-4 pt-6">
        <Button variant="danger" class="w-full" :loading="isDeleting" loading-text="Excluindo..." @click="onDeleteClick">
          Excluir cliente
        </Button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { MessageCircle, CalendarPlus, Pencil, FileText, History } from 'lucide-vue-next'
import AppLayout from '../../layouts/AppLayout.vue'
import Button from '../../components/Button.vue'
import Skeleton from '../../components/Skeleton.vue'
import { useFormatting } from '../../composables/useFormatting'
import { useConfirm } from '../../composables/useConfirm'

const page = usePage()
const customerId = page.url.split('/').filter(Boolean)[1]

const { formatBRL, formatPhone } = useFormatting()
const { ask } = useConfirm()

const loading = ref(true)
const isDeleting = ref(false)
const customer = ref<any>(null)
const appointments = ref<any[]>([])

const initials = computed(() =>
  (customer.value?.name ?? '').trim().split(/\s+/).map((p: string) => p[0]).slice(0, 2).join('').toUpperCase()
)

const xsrf = () =>
  decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

const formatDate = (iso: string) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('pt-BR', {
    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
  })
}

const formatRelative = (iso: string) => {
  if (!iso) return '—'
  const d = new Date(iso)
  const now = new Date()
  const days = Math.floor((now.getTime() - d.getTime()) / 86400000)
  if (days === 0) return 'Hoje'
  if (days === 1) return 'Ontem'
  if (days < 30) return `Há ${days}d`
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })
}

const statusColor = (s: string) =>
  ({
    scheduled: '#6B6B6B',
    confirmed: '#A1A1A1',
    in_progress: '#FFD60A',
    completed: '#22C55E',
    cancelled: '#EF4444',
    no_show: '#F59E0B',
  }[s] ?? '#6B6B6B')

const onDeleteClick = async () => {
  const ok = await ask(
    'Excluir cliente?',
    'Esta ação não pode ser desfeita. Todos os dados deste cliente serão perdidos.',
    { confirmText: 'Excluir', destructive: true }
  )
  if (!ok) return

  isDeleting.value = true
  try {
    const res = await fetch(`/api/customers/${customer.value.id}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrf(),
      },
    })
    if (res.ok || res.status === 204) {
      router.visit('/customers')
    }
  } finally {
    isDeleting.value = false
  }
}

onMounted(async () => {
  try {
    const [customerRes, appointmentsRes] = await Promise.all([
      fetch(`/api/customers/${customerId}`, { headers: { Accept: 'application/json' } }),
      fetch(`/api/appointments?customer_id=${customerId}`, { headers: { Accept: 'application/json' } }),
    ])
    if (customerRes.ok) {
      const json = await customerRes.json()
      customer.value = json.data ?? json
    }
    if (appointmentsRes.ok) appointments.value = (await appointmentsRes.json()).data ?? []
  } finally {
    loading.value = false
  }
})
</script>
