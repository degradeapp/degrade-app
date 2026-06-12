<template>
  <AppLayout title="Comissões" back-href="/settings">
    <div class="p-4 pb-24 space-y-4">
      <!-- Tabs + filtro de mês (mês só faz sentido no histórico de pagas) -->
      <div class="flex items-center gap-2">
        <button
          class="px-4 h-9 rounded-full text-[13px] font-medium transition-colors"
          :class="activeTab === 'pending' ? 'bg-[#FFD60A] text-[#0A0A0A]' : 'bg-[#161616] text-[#A1A1A1] hover:text-white'"
          @click="activeTab = 'pending'"
        >
          Pendentes
        </button>
        <button
          class="px-4 h-9 rounded-full text-[13px] font-medium transition-colors"
          :class="activeTab === 'paid' ? 'bg-[#FFD60A] text-[#0A0A0A]' : 'bg-[#161616] text-[#A1A1A1] hover:text-white'"
          @click="activeTab = 'paid'"
        >
          Pagas
        </button>

        <button
          v-if="activeTab === 'paid'"
          class="ml-auto h-9 pl-3 pr-2.5 rounded-full bg-[#161616] border border-[#2A2A2A] text-[13px] text-[#A1A1A1] hover:text-white flex items-center gap-1.5 transition-colors"
          @click="monthSheetOpen = true"
        >
          {{ selectedMonthLabel }}
          <ChevronDown :size="14" :stroke-width="2" />
        </button>
      </div>

      <!-- Resumo do total -->
      <div
        v-if="!loading && summaryCount"
        class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4 flex items-center justify-between"
      >
        <div>
          <p class="text-[10px] uppercase tracking-[0.08em] text-[#6B6B6B]">
            {{ activeTab === 'pending' ? 'Total a pagar' : 'Total pago' }}
          </p>
          <p class="text-[12px] text-[#A1A1A1] mt-0.5">
            {{ summaryCount }} {{ summaryCount === 1 ? 'comissão' : 'comissões' }}
          </p>
        </div>
        <p class="text-[22px] font-bold text-[#FFD60A] tabular-nums">{{ formatBRL(summaryTotal) }}</p>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="space-y-2">
        <Skeleton v-for="i in 3" :key="i" height="92px" />
      </div>

      <!-- PENDENTES: agrupado por barbeiro, paga tudo de uma vez -->
      <template v-else-if="activeTab === 'pending'">
        <div v-if="pendingGroups.length" class="space-y-2">
          <div
            v-for="g in pendingGroups"
            :key="g.barber_id"
            class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4"
          >
            <component
              :is="g.count > 1 ? 'button' : 'div'"
              type="button"
              class="w-full flex items-center justify-between gap-3 mb-3 text-left"
              @click="g.count > 1 && toggle(g.barber_id)"
            >
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-full bg-[#1A1A1A] border border-[#2A2A2A] flex items-center justify-center text-[12px] font-semibold text-[#FFD60A] flex-shrink-0">
                  {{ initials(g.barber_name) }}
                </div>
                <div class="min-w-0 leading-tight space-y-0.5">
                  <p class="text-[14px] font-medium text-white truncate">{{ g.barber_name }}</p>
                  <p class="text-[12px] text-[#A1A1A1]">
                    {{ g.count }} {{ g.count === 1 ? 'comissão' : 'comissões' }}{{ g.count > 1 ? (isExpanded(g.barber_id) ? ' · toque pra fechar' : ' · toque pra ver') : '' }}
                  </p>
                </div>
              </div>
              <div class="flex items-center gap-2 flex-shrink-0">
                <p class="text-[16px] font-bold text-[#FFD60A] tabular-nums">{{ formatBRL(g.total) }}</p>
                <ChevronDown
                  v-if="g.count > 1"
                  :size="16"
                  class="text-[#6B6B6B] transition-transform"
                  :class="isExpanded(g.barber_id) ? 'rotate-180' : ''"
                  :stroke-width="2"
                />
              </div>
            </component>

            <!-- Itens individuais: pagar uma a uma -->
            <div v-if="g.count > 1 && isExpanded(g.barber_id)" class="space-y-1.5 mb-3">
              <div
                v-for="it in g.items"
                :key="it.id"
                class="flex items-center justify-between gap-3 bg-[#0A0A0A] border border-[#1F1F1F] rounded-[8px] pl-3 pr-2 h-10"
              >
                <span class="text-[12px] text-[#A1A1A1] tabular-nums">
                  {{ fmtDate(it.reference_date) }} · <span class="text-white font-medium">{{ formatBRL(it.amount) }}</span>
                </span>
                <button
                  type="button"
                  :disabled="payingBarberId !== null || payingCommissionId !== null"
                  class="px-3 h-7 rounded-[7px] text-[12px] font-semibold text-[#FFD60A] hover:bg-[#FFD60A]/10 disabled:opacity-50 transition-colors flex items-center gap-1"
                  @click="payOne(it)"
                >
                  <Loader2 v-if="payingCommissionId === it.id" :size="13" class="animate-spin" />
                  Pagar
                </button>
              </div>
            </div>

            <button
              type="button"
              :disabled="payingBarberId !== null || payingCommissionId !== null"
              class="w-full h-11 rounded-[10px] font-bold text-[14px] text-[#0A0A0A] flex items-center justify-center gap-2 bg-[#FFD60A] enabled:hover:bg-[#FFE066] disabled:opacity-70 disabled:cursor-not-allowed transition-colors"
              @click="payBarber(g)"
            >
              <Loader2 v-if="payingBarberId === g.barber_id" :size="16" class="animate-spin" />
              {{ payingBarberId === g.barber_id ? 'Pagando...' : (g.count > 1 ? `Pagar tudo · ${formatBRL(g.total)}` : `Pagar ${formatBRL(g.total)}`) }}
            </button>
          </div>
        </div>

        <div v-else class="text-center py-16">
          <Wallet :size="32" class="text-[#6B6B6B] mx-auto mb-3" :stroke-width="1.75" />
          <p class="text-[15px] font-medium text-white mb-1">Tudo em dia</p>
          <p class="text-[13px] text-[#A1A1A1] max-w-[280px] mx-auto">
            Nenhuma comissão a pagar. Elas surgem aqui quando você conclui um atendimento de um funcionário.
          </p>
        </div>
      </template>

      <!-- PAGAS: histórico (lista plana) -->
      <template v-else>
        <div v-if="paidFiltered.length" class="space-y-2">
          <Link
            v-for="c in paidFiltered"
            :key="c.id"
            :href="`/commissions/${c.id}`"
            class="block bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4 hover:border-[#3D3D3D] transition-colors"
          >
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-full bg-[#1A1A1A] border border-[#2A2A2A] flex items-center justify-center text-[12px] font-semibold text-[#FFD60A] flex-shrink-0">
                  {{ initials(c.barber?.name) }}
                </div>
                <div class="min-w-0 leading-tight space-y-0.5">
                  <p class="text-[14px] font-medium text-white truncate">{{ c.barber?.name ?? '—' }}</p>
                  <p class="text-[12px] text-[#6B6B6B]">{{ fmtDate(c.reference_date) }}</p>
                </div>
              </div>
              <div class="text-right flex-shrink-0">
                <p class="text-[15px] font-bold text-[#FFD60A] tabular-nums">{{ formatBRL(Number(c.amount)) }}</p>
                <span class="inline-block mt-1 text-[10px] px-2 py-0.5 rounded-[6px] font-medium bg-[#22C55E]/15 text-[#22C55E]">
                  Paga
                </span>
              </div>
            </div>
          </Link>
        </div>

        <div v-else class="text-center py-16">
          <Wallet :size="32" class="text-[#6B6B6B] mx-auto mb-3" :stroke-width="1.75" />
          <p class="text-[15px] font-medium text-white mb-1">Nenhuma comissão paga</p>
          <p class="text-[13px] text-[#A1A1A1] max-w-[280px] mx-auto">
            Quando você fechar o pagamento de um barbeiro, o histórico aparece aqui.
          </p>
        </div>
      </template>
    </div>

    <!-- Bottom sheet: escolher mês -->
    <Teleport to="body">
      <div v-if="monthSheetOpen" class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-black/60" @click="monthSheetOpen = false"></div>
        <div class="absolute bottom-0 left-0 right-0 max-w-[640px] mx-auto bg-[#131313] border-t border-[#2A2A2A] rounded-t-[20px] max-h-[70vh] flex flex-col pb-[max(1rem,env(safe-area-inset-bottom))]">
          <div class="flex justify-center pt-2 pb-1">
            <div class="w-10 h-1 bg-[#3D3D3D] rounded-full"></div>
          </div>
          <div class="px-5 pt-3 pb-2 border-b border-[#1F1F1F]">
            <h3 class="text-[16px] font-semibold text-white">Mês</h3>
          </div>
          <div class="overflow-y-auto px-3 py-2">
            <button
              v-for="opt in monthOptions"
              :key="opt.value"
              class="w-full flex items-center justify-between px-3 h-12 rounded-[10px] text-left hover:bg-[#1A1A1A] transition-colors"
              :class="selectedMonth === opt.value ? 'text-[#FFD60A]' : 'text-white'"
              @click="selectMonth(opt.value)"
            >
              <span class="text-[14px]">{{ opt.label }}</span>
              <Check v-if="selectedMonth === opt.value" :size="16" :stroke-width="2.5" />
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ChevronDown, Check, Wallet, Loader2 } from 'lucide-vue-next'
import AppLayout from '../../layouts/AppLayout.vue'
import Skeleton from '../../components/Skeleton.vue'
import { useFormatting } from '../../composables/useFormatting'
import { useApi } from '../../composables/useApi'
import { useConfirm } from '../../composables/useConfirm'
import { useToast } from '../../composables/useToast'

const { formatBRL } = useFormatting()
const api = useApi()
const { ask } = useConfirm()
const toast = useToast()

interface CommissionItem {
  id: number
  amount: number
  reference_date: string | null
}
interface PendingGroup {
  barber_id: number
  barber_name: string
  count: number
  total: number
  items: CommissionItem[]
}
interface PaidCommission {
  id: number
  barber?: { id: number; name: string }
  amount: number | string
  reference_date: string | null
}

const activeTab = ref<'pending' | 'paid'>('pending')
const selectedMonth = ref('')
const monthSheetOpen = ref(false)
const loading = ref(true)
const payingBarberId = ref<number | null>(null)
const payingCommissionId = ref<number | null>(null)
const expandedBarbers = ref<number[]>([])

const pendingGroups = ref<PendingGroup[]>([])
const paidList = ref<PaidCommission[]>([])

const isExpanded = (barberId: number) => expandedBarbers.value.includes(barberId)
const toggle = (barberId: number) => {
  isExpanded(barberId)
    ? (expandedBarbers.value = expandedBarbers.value.filter((id) => id !== barberId))
    : expandedBarbers.value.push(barberId)
}

const months = [
  { value: '01', label: 'Janeiro' },
  { value: '02', label: 'Fevereiro' },
  { value: '03', label: 'Março' },
  { value: '04', label: 'Abril' },
  { value: '05', label: 'Maio' },
  { value: '06', label: 'Junho' },
  { value: '07', label: 'Julho' },
  { value: '08', label: 'Agosto' },
  { value: '09', label: 'Setembro' },
  { value: '10', label: 'Outubro' },
  { value: '11', label: 'Novembro' },
  { value: '12', label: 'Dezembro' },
]
const monthOptions = [{ value: '', label: 'Todos os meses' }, ...months]
const selectedMonthLabel = computed(
  () => monthOptions.find((m) => m.value === selectedMonth.value)?.label ?? 'Todos os meses'
)
const selectMonth = (v: string) => {
  selectedMonth.value = v
  monthSheetOpen.value = false
}

const initials = (name?: string) =>
  (name ?? '').trim().split(/\s+/).map((p) => p[0]).slice(0, 2).join('').toUpperCase()

const fmtDate = (iso: string | null) => {
  if (!iso) return '—'
  const [y, m, d] = iso.split('-')
  return `${d}/${m}/${y}`
}

// Pagas filtram por mês (histórico). Pendentes mostram o total devido (sem mês).
const paidFiltered = computed(() =>
  paidList.value.filter((c) => !selectedMonth.value || (c.reference_date ?? '').includes(`-${selectedMonth.value}-`))
)

const summaryCount = computed(() =>
  activeTab.value === 'pending'
    ? pendingGroups.value.reduce((s, g) => s + g.count, 0)
    : paidFiltered.value.length
)
const summaryTotal = computed(() =>
  activeTab.value === 'pending'
    ? pendingGroups.value.reduce((s, g) => s + g.total, 0)
    : paidFiltered.value.reduce((s, c) => s + Number(c.amount), 0)
)

const loadData = async () => {
  loading.value = true
  try {
    const [pending, paid] = await Promise.all([
      api.get<PendingGroup[]>('/api/commissions/pending-summary'),
      api.get<PaidCommission[]>('/api/commissions?status=paid&per_page=200'),
    ])
    if (pending.ok) pendingGroups.value = (pending.data as any) ?? []
    if (paid.ok) paidList.value = (paid.data as any) ?? []
  } finally {
    loading.value = false
  }
}

const payBarber = async (g: PendingGroup) => {
  const ok = await ask(
    `Pagar ${g.barber_name}?`,
    `Isso marca ${g.count} ${g.count === 1 ? 'comissão' : 'comissões'} (${formatBRL(g.total)}) como ${g.count === 1 ? 'paga' : 'pagas'}.`,
    { confirmText: 'Pagar' }
  )
  if (!ok) return

  payingBarberId.value = g.barber_id
  try {
    const res = await api.post('/api/commissions/pay-barber', { barber_id: g.barber_id })
    if (res.ok) {
      toast.success(`${formatBRL(g.total)} pagos a ${g.barber_name}`)
      await loadData()
    } else {
      toast.error(res.message || 'Não foi possível registrar o pagamento.')
    }
  } finally {
    payingBarberId.value = null
  }
}

const payOne = async (it: CommissionItem) => {
  const ok = await ask('Marcar como paga?', `Comissão de ${formatBRL(it.amount)}.`, { confirmText: 'Pagar' })
  if (!ok) return

  payingCommissionId.value = it.id
  try {
    const res = await api.post(`/api/commissions/${it.id}/mark-as-paid`)
    if (res.ok) {
      toast.success(`${formatBRL(it.amount)} pago`)
      await loadData()
    } else {
      toast.error(res.message || 'Não foi possível registrar o pagamento.')
    }
  } finally {
    payingCommissionId.value = null
  }
}

onMounted(loadData)
</script>
