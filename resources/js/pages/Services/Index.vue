<template>
  <AppLayout title="Serviços" back-href="/settings" @fab-click="router.visit('/services/create')">
    <div class="p-4 space-y-3 pb-24 animate-enter">
      <div class="relative">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Buscar serviço..."
          class="block w-full h-12 pl-11 pr-4 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[14px] text-white outline-none focus:border-[#FFD60A] placeholder-[#6B6B6B]"
          @input="onSearchInput"
        />
        <Search :size="18" class="absolute left-3.5 top-3.5 text-[#6B6B6B]" :stroke-width="1.75" />
      </div>

      <button
        type="button"
        class="w-full h-11 rounded-[10px] border border-[#2A2A2A] text-[13px] font-medium text-[#A1A1A1] hover:text-white hover:border-[#3D3D3D] transition-colors flex items-center justify-center gap-2"
        @click="openCommon"
      >
        <Plus :size="16" :stroke-width="2" /> Alterar serviços
      </button>

      <div v-if="loading && services.length === 0" class="space-y-3">
        <Skeleton v-for="i in 3" :key="i" height="80px" />
      </div>

      <div v-else-if="services.length === 0" class="text-center py-16">
        <Tag :size="32" class="text-[#6B6B6B] mx-auto mb-3" :stroke-width="1.75" />
        <p class="text-[15px] font-medium text-white mb-1">{{ searchQuery ? 'Nada encontrado' : 'Nenhum serviço' }}</p>
        <p class="text-[13px] text-[#A1A1A1]">
          {{ searchQuery ? 'Tente outro termo.' : 'Cadastre os serviços que sua barbearia oferece.' }}
        </p>
        <Link
          v-if="!searchQuery"
          href="/services/create"
          class="inline-block mt-4 h-11 px-5 leading-[2.75rem] rounded-[10px] bg-[#FFD60A] text-[14px] font-bold text-[#0A0A0A] hover:bg-[#FFE066]"
        >
          + Novo serviço
        </Link>
      </div>

      <div v-else class="space-y-2 stagger">
        <Link
          v-for="service in services"
          :key="service.id"
          :href="`/services/${service.id}/edit`"
          class="flex items-center gap-3 bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4 hover:border-[#3D3D3D] active:scale-[0.99] transition-all"
        >
          <div class="w-10 h-10 rounded-[10px] bg-[#FFD60A]/10 flex items-center justify-center flex-shrink-0">
            <Scissors :size="17" class="text-[#FFD60A]" :stroke-width="2" />
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-[14px] font-medium text-white truncate">{{ service.name }}</p>
            <p v-if="service.commission_percentage" class="text-[12px] text-[#A1A1A1] mt-0.5">
              Comissão {{ service.commission_percentage }}%
            </p>
          </div>
          <p class="text-[15px] font-semibold text-[#FFD60A] tabular-nums flex-shrink-0">
            {{ formatBRL(service.price) }}
          </p>
        </Link>
      </div>
    </div>

    <!-- Bottom sheet: serviços comuns + preço base -->
    <Teleport to="body">
      <div v-if="sheetOpen" class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-black/60" @click="sheetOpen = false"></div>
        <div class="absolute bottom-0 left-0 right-0 max-w-[640px] mx-auto bg-[#131313] border-t border-[#2A2A2A] rounded-t-[20px] max-h-[85vh] flex flex-col">
          <div class="px-5 pt-4 pb-3 border-b border-[#1F1F1F]">
            <h3 class="text-[17px] font-semibold text-white">Serviços</h3>
            <p class="text-[12px] text-[#A1A1A1] mt-0.5">Marque os que você oferece. O preço base vale pra todos; ajuste as exceções.</p>
          </div>

          <div class="px-5 py-3 border-b border-[#1F1F1F]">
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[13px] text-[#6B6B6B] pointer-events-none">R$</span>
              <input
                :value="basePrice ?? ''"
                type="number"
                step="0.01"
                inputmode="decimal"
                placeholder="Preço base (ex: 40)"
                class="block w-full h-11 pl-9 pr-4 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white outline-none focus:border-[#FFD60A] placeholder-[#6B6B6B] tabular-nums"
                @input="onBasePriceInput"
              />
            </div>
          </div>

          <div class="flex-1 overflow-y-auto px-5 py-3 space-y-2">
            <div
              v-for="item in commonItems"
              :key="item.name"
              class="flex items-center gap-3 bg-[#161616] border rounded-[10px] px-3 h-12"
              :class="item.selected ? 'border-[#FFD60A]/40' : 'border-[#2A2A2A]'"
            >
              <button type="button" class="flex items-center gap-2.5 flex-1 min-w-0 text-left" @click="toggleItem(item)">
                <span
                  class="w-5 h-5 rounded-[6px] border flex items-center justify-center flex-shrink-0"
                  :class="item.selected ? 'bg-[#FFD60A] border-[#FFD60A]' : 'border-[#3D3D3D]'"
                >
                  <Check v-if="item.selected" :size="14" :stroke-width="3" class="text-[#0A0A0A]" />
                </span>
                <span class="text-[14px] truncate" :class="item.selected ? 'text-white' : 'text-[#A1A1A1]'">{{ item.name }}</span>
              </button>
              <div v-if="item.selected" class="relative w-[88px] flex-shrink-0">
                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[12px] text-[#6B6B6B] pointer-events-none">R$</span>
                <input
                  :value="item.price ?? ''"
                  type="number"
                  step="0.01"
                  inputmode="decimal"
                  placeholder="base"
                  class="w-full h-9 pl-7 pr-2 bg-[#0A0A0A] border border-[#2A2A2A] rounded-[8px] text-[14px] text-white outline-none focus:border-[#FFD60A] placeholder-[#6B6B6B] tabular-nums"
                  @input="(e) => onItemPriceInput(e, item)"
                />
              </div>
            </div>
          </div>

          <div class="px-5 py-4 border-t border-[#1F1F1F]">
            <button
              type="button"
              :disabled="!canSaveCommon || savingCommon"
              class="w-full h-12 rounded-[10px] font-bold text-[15px] text-[#0A0A0A] flex items-center justify-center gap-2 bg-[#FFD60A] enabled:hover:bg-[#FFE066] disabled:opacity-70 disabled:cursor-not-allowed"
              @click="saveCommon"
            >
              <Loader2 v-if="savingCommon" :size="18" class="animate-spin" />
              {{ savingCommon ? 'Salvando...' : `Alterar ${selectedCount} ${selectedCount === 1 ? 'serviço' : 'serviços'}` }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { Tag, Search, Plus, Check, Loader2, Scissors } from 'lucide-vue-next'
import AppLayout from '../../layouts/AppLayout.vue'
import Skeleton from '../../components/Skeleton.vue'
import { useFormatting } from '../../composables/useFormatting'
import { useApi } from '../../composables/useApi'
import { COMMON_SERVICES } from '@/data/commonServices'
import { capPrice } from '@/data/numericInput'

const { formatBRL } = useFormatting()
const api = useApi()

interface Service {
  id: number
  name: string
  price: number
  commission_percentage?: number
}

const searchQuery = ref('')
const loading = ref(true)
const services = ref<Service[]>([])

// ---- Serviços comuns (bottom sheet com preço base) ----
const sheetOpen = ref(false)
const savingCommon = ref(false)
const basePrice = ref<number | null>(null)
interface CommonItem {
  name: string
  selected: boolean
  price: number | null
  touched: boolean
}
// Montado em openCommon: catálogo comum + serviços personalizados já cadastrados.
const commonItems = ref<CommonItem[]>([])
const resolvedPrice = (item: { price: number | null }) =>
  typeof item.price === 'number' ? item.price : basePrice.value

const toggleItem = (item: { selected: boolean; touched: boolean; price: number | null }) => {
  item.selected = !item.selected
  if (item.selected && !item.touched && basePrice.value != null) item.price = basePrice.value
}

// Preço: até 6 dígitos + 2 decimais
const onBasePriceInput = (e: Event) => {
  const el = e.target as HTMLInputElement
  const v = capPrice(el.value)
  basePrice.value = v === '' || v === '.' ? null : Number(v)
  if (v !== el.value && !v.endsWith('.')) el.value = v
  commonItems.value.forEach((i) => {
    if (i.selected && !i.touched) i.price = basePrice.value
  })
}
const onItemPriceInput = (e: Event, item: { price: number | null; touched: boolean }) => {
  const el = e.target as HTMLInputElement
  const v = capPrice(el.value)
  item.price = v === '' || v === '.' ? null : Number(v)
  item.touched = true
  if (v !== el.value && !v.endsWith('.')) el.value = v
}
const selectedCount = computed(() => commonItems.value.filter((i) => i.selected).length)
const canSaveCommon = computed(() =>
  commonItems.value.some((i) => i.selected) &&
  commonItems.value.filter((i) => i.selected).every((i) => {
    const p = resolvedPrice(i)
    return typeof p === 'number' && p >= 0
  })
)

const openCommon = () => {
  basePrice.value = null

  // 1) Catálogo comum: serviços que já existem vêm marcados com o preço atual.
  //    touched=false de propósito, pra que digitar um "preço base" sobrescreva todos.
  const commonNames = new Set(COMMON_SERVICES.map((s) => s.name))
  const items: CommonItem[] = COMMON_SERVICES.map((s) => {
    const existing = services.value.find((sv) => sv.name === s.name)
    return {
      name: s.name,
      selected: existing ? true : s.preselected,
      price: existing ? Number(existing.price) : null,
      touched: false,
    }
  })

  // 2) Serviços personalizados (criados pelo usuário) que não estão no catálogo.
  //    Sempre marcados, com o preço atual — pra que apareçam e não somam ao salvar.
  for (const sv of services.value) {
    if (!commonNames.has(sv.name)) {
      items.push({ name: sv.name, selected: true, price: Number(sv.price), touched: false })
    }
  }

  commonItems.value = items
  sheetOpen.value = true
}

const saveCommon = async () => {
  if (!canSaveCommon.value) return
  savingCommon.value = true
  try {
    const payload = commonItems.value
      .filter((i) => i.selected)
      .map((i) => ({ name: i.name, price: resolvedPrice(i) }))
    const res = await api.post('/api/services/bulk', { services: payload })
    if (res.ok) {
      sheetOpen.value = false
      await load()
    }
  } finally {
    savingCommon.value = false
  }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null

const load = async () => {
  loading.value = true
  const q = searchQuery.value.trim()
  const url = q.length >= 2 ? `/api/services?q=${encodeURIComponent(q)}` : '/api/services'
  const res = await api.get<Service[]>(url)
  if (res.ok) services.value = (res.data as any) ?? []
  loading.value = false
}

const onSearchInput = () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(load, 300)
}

onMounted(load)
</script>
