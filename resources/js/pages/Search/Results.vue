<template>
  <AppLayout title="Buscar" show-back-button>
    <div class="p-4 pb-24 space-y-4">
      <div class="relative">
        <input
          ref="searchInput"
          v-model="query"
          type="text"
          placeholder="Cliente, telefone, barbeiro..."
          class="block w-full h-12 pl-11 pr-4 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[14px] text-white outline-none focus:border-[#FFD60A] placeholder-[#6B6B6B]"
          @input="onInput"
        />
        <Search :size="18" class="absolute left-3.5 top-3.5 text-[#6B6B6B]" :stroke-width="1.75" />
      </div>

      <div v-if="loading" class="space-y-2">
        <Skeleton v-for="i in 3" :key="i" height="64px" />
      </div>

      <div v-else-if="query.length >= 2 && results.length === 0" class="text-center py-12 text-[13px] text-[#6B6B6B]">
        Nada encontrado para "{{ query }}".
      </div>

      <div v-else-if="results.length" class="space-y-2 stagger">
        <Link
          v-for="r in results"
          :key="`${r.type}-${r.id}`"
          :href="hrefFor(r)"
          class="block bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-3 hover:border-[#3D3D3D] active:scale-[0.99] transition-all flex items-center gap-3"
        >
          <div class="w-10 h-10 rounded-[10px] bg-[#1A1A1A] border border-[#2A2A2A] flex items-center justify-center text-[#FFD60A]">
            <component :is="iconFor(r.type)" :size="16" :stroke-width="1.75" />
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-[14px] font-medium text-white truncate">{{ r.title }}</p>
            <p class="text-[12px] text-[#A1A1A1] truncate">{{ r.subtitle }}</p>
          </div>
          <span class="text-[10px] uppercase tracking-[0.08em] text-[#6B6B6B]">{{ typeLabel(r.type) }}</span>
        </Link>
      </div>

      <div v-else-if="query.length < 2" class="text-center py-12 text-[13px] text-[#6B6B6B]">
        Digite pelo menos 2 caracteres para buscar.
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Search, User, Scissors, Calendar } from 'lucide-vue-next'
import AppLayout from '../../layouts/AppLayout.vue'
import Skeleton from '../../components/Skeleton.vue'

interface Result {
  id: number
  type: 'customer' | 'barber' | 'appointment'
  title: string
  subtitle: string
}

const query = ref('')
const loading = ref(false)
const results = ref<Result[]>([])
const searchInput = ref<HTMLInputElement | null>(null)

let timer: ReturnType<typeof setTimeout> | null = null

const iconFor = (t: Result['type']) =>
  ({ customer: User, barber: Scissors, appointment: Calendar }[t] ?? User)

const typeLabel = (t: Result['type']) =>
  ({ customer: 'Cliente', barber: 'Barbeiro', appointment: 'Agendamento' }[t] ?? '')

const hrefFor = (r: Result) =>
  ({
    customer: `/customers/${r.id}`,
    barber: `/barbers/${r.id}`,
    appointment: `/appointments/${r.id}`,
  }[r.type])

const onInput = () => {
  if (timer) clearTimeout(timer)
  if (query.value.length < 2) {
    results.value = []
    return
  }
  timer = setTimeout(doSearch, 250)
}

const doSearch = async () => {
  loading.value = true
  try {
    const res = await fetch(`/api/search?q=${encodeURIComponent(query.value)}`, {
      headers: { Accept: 'application/json' },
    })
    if (res.ok) {
      const json = await res.json()
      results.value = (json.data ?? []).map((r: any) => ({
        id: r.id,
        type: r.type,
        title: r.title ?? r.name ?? '',
        subtitle: r.subtitle ?? r.phone ?? '',
      }))
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  searchInput.value?.focus()
})
</script>
