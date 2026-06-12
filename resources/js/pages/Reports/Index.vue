<template>
  <AppLayout title="Relatórios" show-back-button>
    <div class="p-4 pb-24 space-y-5">
      <!-- Período: atalhos + intervalo personalizado -->
      <div>
        <div class="flex gap-2 overflow-x-auto scrollbar-none -mx-4 px-4">
          <button
            v-for="p in periods"
            :key="p.value"
            @click="setPeriod(p.value)"
            :class="[
              'px-3.5 h-8 rounded-full text-[12px] font-medium whitespace-nowrap flex-shrink-0 border transition-colors',
              period === p.value
                ? 'bg-[#FFD60A] border-[#FFD60A] text-[#0A0A0A]'
                : 'bg-transparent border-[#2A2A2A] text-[#A1A1A1] hover:text-white hover:border-[#3D3D3D]',
            ]"
          >
            {{ p.label }}
          </button>
          <button
            @click="showCustom = !showCustom"
            :class="[
              'px-3 h-8 rounded-full text-[12px] font-medium whitespace-nowrap flex-shrink-0 border transition-colors flex items-center gap-1.5',
              period === 'custom'
                ? 'bg-[#FFD60A] border-[#FFD60A] text-[#0A0A0A]'
                : 'bg-transparent border-[#2A2A2A] text-[#A1A1A1] hover:text-white hover:border-[#3D3D3D]',
            ]"
          >
            <CalendarRange :size="14" :stroke-width="2" />
            Datas
          </button>
        </div>

        <div v-if="showCustom" class="grid grid-cols-2 gap-2 mt-3">
          <div>
            <label class="block text-[10px] uppercase tracking-[0.08em] text-[#6B6B6B] font-medium mb-1">De</label>
            <input
              v-model="from"
              type="date"
              class="block w-full h-11 px-3 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[13px] text-white outline-none focus:border-[#FFD60A]"
              @change="onCustomDate"
            />
          </div>
          <div>
            <label class="block text-[10px] uppercase tracking-[0.08em] text-[#6B6B6B] font-medium mb-1">Até</label>
            <input
              v-model="to"
              type="date"
              class="block w-full h-11 px-3 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[13px] text-white outline-none focus:border-[#FFD60A]"
              @change="onCustomDate"
            />
          </div>
        </div>
      </div>

      <div v-if="loading" class="space-y-3">
        <Skeleton height="120px" />
        <Skeleton height="80px" />
        <Skeleton height="80px" />
      </div>

      <template v-else-if="data">
        <!-- HERO: Receita -->
        <div class="relative overflow-hidden rounded-[16px] border border-[#2A2A2A] bg-gradient-to-br from-[#1C1C18] to-[#131313] p-5">
          <div class="relative">
            <div class="flex items-center gap-1.5 mb-1.5">
              <TrendingUp :size="15" :stroke-width="2.25" class="text-[#FFD60A]" />
              <span class="text-[11px] uppercase tracking-[0.08em] text-[#A1A1A1] font-medium">Receita no período</span>
            </div>
            <p class="text-[34px] font-bold text-white tabular-nums leading-none">{{ fmt(data.revenue) }}</p>
            <p class="text-[12px] text-[#A1A1A1] mt-2 tabular-nums">
              <template v-if="data.completed_count > 0">
                {{ data.completed_count }} {{ data.completed_count === 1 ? 'atendimento' : 'atendimentos' }}
                · média de {{ fmt(data.avg_ticket) }} cada
              </template>
              <template v-else>Nenhum atendimento concluído ainda</template>
            </p>
            <p class="text-[11px] text-[#A1A1A1] mt-0.5">{{ periodLabel }}</p>
          </div>
        </div>

        <!-- Empty state global -->
        <div v-if="isEmpty" class="text-center py-12">
          <CalendarOff :size="32" :stroke-width="1.75" class="text-[#6B6B6B] mx-auto mb-3" />
          <p class="text-[15px] font-medium text-white mb-1">Sem movimento neste período</p>
          <p class="text-[13px] text-[#A1A1A1]">Escolha outro período ou registre atendimentos.</p>
        </div>

        <template v-else>
          <!-- Comissões (só quando há) -->
          <section v-if="hasCommissions" class="space-y-2">
            <h3 class="flex items-center gap-1.5 text-[12px] font-semibold text-white px-1">
              <Wallet :size="14" :stroke-width="2" class="text-[#A1A1A1]" />
              Comissões dos funcionários
            </h3>
            <div class="grid grid-cols-2 gap-2">
              <div class="bg-[#131313] border border-[#2A2A2A] rounded-[12px] p-3.5">
                <p class="text-[11px] text-[#A1A1A1]">A pagar</p>
                <p class="text-[19px] font-bold tabular-nums mt-1" :class="data.commissions_pending > 0 ? 'text-[#FB923C]' : 'text-white'">
                  {{ fmt(data.commissions_pending) }}
                </p>
              </div>
              <div class="bg-[#131313] border border-[#2A2A2A] rounded-[12px] p-3.5">
                <p class="text-[11px] text-[#A1A1A1]">Já pagas</p>
                <p class="text-[19px] font-bold tabular-nums mt-1 text-[#22C55E]">{{ fmt(data.commissions_paid) }}</p>
              </div>
            </div>
            <!-- Fica pra barbearia (receita − comissões) -->
            <div class="flex items-center justify-between bg-[#0F140F] border border-[#22C55E]/25 rounded-[12px] p-3.5">
              <div class="flex items-center gap-2 min-w-0">
                <PiggyBank :size="18" :stroke-width="2" class="text-[#22C55E] flex-shrink-0" />
                <div class="min-w-0">
                  <p class="text-[13px] font-medium text-white">Fica pra barbearia</p>
                  <p class="text-[11px] text-[#6B6B6B]">depois de pagar os barbeiros</p>
                </div>
              </div>
              <p class="text-[18px] font-bold text-[#22C55E] tabular-nums flex-shrink-0">{{ fmt(data.net_revenue) }}</p>
            </div>
          </section>

          <!-- Por unidade (consolidado da rede) — só quando há mais de uma -->
          <section v-if="(data.per_unit?.length ?? 0) > 1" class="space-y-2">
            <h3 class="flex items-center gap-1.5 text-[12px] font-semibold text-white px-1">
              <Store :size="14" :stroke-width="2" class="text-[#A1A1A1]" />
              Por unidade
            </h3>
            <div class="space-y-2">
              <div
                v-for="u in data.per_unit"
                :key="u.unit_id"
                class="bg-[#131313] border border-[#2A2A2A] rounded-[12px] p-3 flex items-center gap-3"
              >
                <div class="flex-1 min-w-0">
                  <p class="text-[14px] font-medium text-white truncate">{{ u.name }}</p>
                  <p class="text-[11px] text-[#A1A1A1] tabular-nums">{{ u.count }} {{ u.count === 1 ? 'atendimento' : 'atendimentos' }}</p>
                </div>
                <p class="text-[14px] font-bold text-[#FFD60A] tabular-nums flex-shrink-0">{{ fmt(u.revenue) }}</p>
              </div>
            </div>
          </section>

          <!-- Atendimentos -->
          <section class="space-y-2">
            <h3 class="flex items-center gap-1.5 text-[12px] font-semibold text-white px-1">
              <CalendarCheck :size="14" :stroke-width="2" class="text-[#A1A1A1]" />
              Atendimentos
            </h3>
            <div class="grid grid-cols-3 gap-2">
              <div class="bg-[#131313] border border-[#2A2A2A] rounded-[12px] p-3 text-center">
                <p class="text-[24px] font-bold text-[#22C55E] tabular-nums leading-none">{{ data.completed_count }}</p>
                <p class="text-[11px] text-[#D4D4D4] mt-1.5">Concluídos</p>
              </div>
              <div class="bg-[#131313] border border-[#2A2A2A] rounded-[12px] p-3 text-center">
                <p class="text-[24px] font-bold text-white tabular-nums leading-none">{{ data.cancelled_count }}</p>
                <p class="text-[11px] text-[#D4D4D4] mt-1.5">Cancelados</p>
              </div>
              <div class="bg-[#131313] border border-[#2A2A2A] rounded-[12px] p-3 text-center">
                <p class="text-[24px] font-bold text-white tabular-nums leading-none">{{ data.no_show_count }}</p>
                <p class="text-[11px] text-[#D4D4D4] mt-1.5">Faltaram</p>
              </div>
            </div>
          </section>

          <!-- Clientes novos -->
          <section class="space-y-2">
            <h3 class="flex items-center gap-1.5 text-[12px] font-semibold text-white px-1">
              <UserPlus :size="14" :stroke-width="2" class="text-[#A1A1A1]" />
              Clientes
            </h3>
            <div class="flex items-center justify-between bg-[#131313] border border-[#2A2A2A] rounded-[12px] p-3.5">
              <p class="text-[13px] text-[#A1A1A1]">Clientes novos no período</p>
              <p class="text-[20px] font-bold text-white tabular-nums">{{ data.new_customers }}</p>
            </div>
          </section>

          <!-- Top barbeiros -->
          <section v-if="data.top_barbers.length" class="space-y-2">
            <h3 class="flex items-center gap-1.5 text-[12px] font-semibold text-white px-1">
              <Scissors :size="14" :stroke-width="2" class="text-[#A1A1A1]" />
              Quem mais atendeu
            </h3>
            <div class="space-y-2">
              <div
                v-for="(b, i) in data.top_barbers"
                :key="b.barber_id"
                class="bg-[#131313] border border-[#2A2A2A] rounded-[12px] p-3 flex items-center gap-3"
              >
                <span
                  class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold flex-shrink-0 tabular-nums"
                  :class="i === 0 ? 'bg-[#FFD60A] text-[#0A0A0A]' : 'bg-[#1F1F1F] text-[#A1A1A1]'"
                >
                  {{ i + 1 }}
                </span>
                <div class="flex-1 min-w-0">
                  <p class="text-[14px] font-medium text-white truncate">{{ b.name }}</p>
                  <p class="text-[11px] text-[#A1A1A1] tabular-nums">{{ b.count }} {{ b.count === 1 ? 'atendimento' : 'atendimentos' }}</p>
                </div>
                <p class="text-[14px] font-bold text-[#FFD60A] tabular-nums flex-shrink-0">{{ fmt(b.revenue) }}</p>
              </div>
            </div>
          </section>

          <!-- Top clientes -->
          <section v-if="data.top_customers.length" class="space-y-2">
            <h3 class="flex items-center gap-1.5 text-[12px] font-semibold text-white px-1">
              <Users :size="14" :stroke-width="2" class="text-[#A1A1A1]" />
              Clientes mais fiéis
            </h3>
            <div class="space-y-2">
              <div
                v-for="(c, i) in data.top_customers"
                :key="c.customer_id"
                class="bg-[#131313] border border-[#2A2A2A] rounded-[12px] p-3 flex items-center gap-3"
              >
                <span
                  class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold flex-shrink-0 tabular-nums"
                  :class="i === 0 ? 'bg-[#FFD60A] text-[#0A0A0A]' : 'bg-[#1F1F1F] text-[#A1A1A1]'"
                >
                  {{ i + 1 }}
                </span>
                <div class="flex-1 min-w-0">
                  <p class="text-[14px] font-medium text-white truncate">{{ c.name }}</p>
                  <p class="text-[11px] text-[#A1A1A1] tabular-nums">{{ c.count }} {{ c.count === 1 ? 'visita' : 'visitas' }}</p>
                </div>
                <p class="text-[14px] font-bold text-[#FFD60A] tabular-nums flex-shrink-0">{{ fmt(c.revenue) }}</p>
              </div>
            </div>
          </section>
        </template>
      </template>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  TrendingUp, Wallet, PiggyBank, CalendarCheck, CalendarOff,
  CalendarRange, UserPlus, Scissors, Users, Store,
} from 'lucide-vue-next'
import AppLayout from '../../layouts/AppLayout.vue'
import Skeleton from '../../components/Skeleton.vue'
import { useApi } from '../../composables/useApi'
import { useFormatting } from '../../composables/useFormatting'

const { formatBRL } = useFormatting()
const api = useApi()

interface ReportData {
  from: string
  to: string
  revenue: number
  avg_ticket: number
  completed_count: number
  cancelled_count: number
  no_show_count: number
  new_customers: number
  commissions_pending: number
  commissions_paid: number
  net_revenue: number
  per_unit: Array<{ unit_id: number; name: string; count: number; revenue: number }>
  top_barbers: Array<{ barber_id: number; name: string; count: number; revenue: number }>
  top_customers: Array<{ customer_id: number; name: string; count: number; revenue: number }>
}

type Period = 'today' | '7d' | '30d' | 'month' | 'custom'

const fmt = (v: number | string) => formatBRL(Number(v) || 0)

const isoDate = (d: Date) => {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

const periods: { value: Period; label: string }[] = [
  { value: 'today', label: 'Hoje' },
  { value: '7d', label: '7 dias' },
  { value: '30d', label: '30 dias' },
  { value: 'month', label: 'Este mês' },
]

const period = ref<Period>('30d')
const showCustom = ref(false)

const today = new Date()
const monthAgo = new Date()
monthAgo.setDate(today.getDate() - 29)

const from = ref(isoDate(monthAgo))
const to = ref(isoDate(today))

const loading = ref(true)
const data = ref<ReportData | null>(null)

const hasCommissions = computed(
  () => !!data.value && data.value.commissions_pending + data.value.commissions_paid > 0
)
const isEmpty = computed(
  () =>
    !!data.value &&
    data.value.completed_count === 0 &&
    data.value.cancelled_count === 0 &&
    data.value.no_show_count === 0 &&
    data.value.new_customers === 0
)

const periodLabel = computed(() => {
  if (!data.value) return ''
  const fmtDay = (iso: string) => {
    const [y, m, d] = iso.split('-').map(Number)
    return new Date(y, m - 1, d).toLocaleDateString('pt-BR', { day: 'numeric', month: 'short' }).replace('.', '')
  }
  return data.value.from === data.value.to
    ? fmtDay(data.value.from)
    : `${fmtDay(data.value.from)} a ${fmtDay(data.value.to)}`
})

const setPeriod = (p: Period) => {
  period.value = p
  showCustom.value = p === 'custom'
  const t = new Date()
  if (p === 'today') {
    from.value = isoDate(t)
    to.value = isoDate(t)
  } else if (p === '7d') {
    const s = new Date()
    s.setDate(t.getDate() - 6)
    from.value = isoDate(s)
    to.value = isoDate(t)
  } else if (p === '30d') {
    const s = new Date()
    s.setDate(t.getDate() - 29)
    from.value = isoDate(s)
    to.value = isoDate(t)
  } else if (p === 'month') {
    from.value = isoDate(new Date(t.getFullYear(), t.getMonth(), 1))
    to.value = isoDate(t)
  }
  load()
}

const onCustomDate = () => {
  period.value = 'custom'
  load()
}

const load = async () => {
  loading.value = true
  const res = await api.get<ReportData>(`/api/reports/summary?from=${from.value}&to=${to.value}`)
  if (res.ok) data.value = res.data
  loading.value = false
}

onMounted(load)
</script>

<style scoped>
.scrollbar-none {
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.scrollbar-none::-webkit-scrollbar {
  display: none;
}
</style>
