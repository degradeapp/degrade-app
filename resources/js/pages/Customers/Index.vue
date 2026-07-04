<template>
  <AppLayout title="Clientes" @fab-click="router.visit('/customers/create')">
    <div class="p-4 space-y-3 pb-24 animate-enter">
      <div class="relative">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Buscar por nome ou telefone..."
          class="block w-full h-12 pl-11 pr-4 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[14px] text-white outline-none focus:border-[#FFD60A] placeholder-[#6B6B6B]"
          @input="onSearchInput"
        />
        <Search :size="18" class="absolute left-3.5 top-3.5 text-[#6B6B6B]" :stroke-width="1.75" />
      </div>

      <div v-if="loading && customers.length === 0" class="space-y-3">
        <Skeleton v-for="i in 4" :key="i" height="80px" />
      </div>

      <div v-else-if="customers.length === 0" class="text-center py-16">
        <Users :size="32" class="text-[#6B6B6B] mx-auto mb-3" :stroke-width="1.75" />
        <p class="text-[15px] font-medium text-white mb-1">{{ searchQuery ? 'Nada encontrado' : 'Nenhum cliente' }}</p>
        <p class="text-[13px] text-[#A1A1A1]">
          {{ searchQuery ? 'Tente outro termo.' : 'Comece cadastrando seu primeiro cliente.' }}
        </p>
        <Link
          v-if="!searchQuery"
          href="/customers/create"
          class="inline-block mt-4 h-11 px-5 leading-[2.75rem] rounded-[10px] bg-[#FFD60A] text-[14px] font-bold text-[#0A0A0A] hover:bg-[#FFE066]"
        >
          + Novo cliente
        </Link>
      </div>

      <div v-else class="space-y-2 stagger">
        <div class="flex items-center justify-between px-1">
          <p class="text-[12px] text-[#6B6B6B] tabular-nums">
            {{ customers.length }} {{ customers.length === 1 ? 'cliente' : 'clientes' }}
          </p>
          <a
            v-if="isOwner"
            href="/api/customers/export"
            class="flex items-center gap-1.5 h-9 px-3 -mr-1 rounded-[8px] text-[12px] font-medium text-[#A1A1A1] hover:text-white transition-colors"
          >
            <Download :size="14" :stroke-width="2" />
            Exportar CSV
          </a>
        </div>
        <Link
          v-for="customer in customers"
          :key="customer.id"
          :href="`/customers/${customer.id}`"
          class="block bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4 hover:border-[#3D3D3D] transition-colors flex items-center gap-3"
        >
          <div class="w-10 h-10 rounded-full bg-[#1A1A1A] border border-[#2A2A2A] flex items-center justify-center text-[12px] font-semibold text-[#FFD60A] flex-shrink-0">
            {{ initials(customer.name) }}
          </div>
          <div class="flex-1 min-w-0 space-y-0.5">
            <p class="text-[14px] font-medium text-white truncate leading-tight">{{ customer.name }}</p>
            <p v-if="customer.phone" class="text-[12px] text-[#A1A1A1] truncate leading-tight">{{ formatPhone(customer.phone) }}</p>
            <p class="text-[11px] text-[#6B6B6B] tabular-nums leading-tight">
              {{ customer.total_visits ?? 0 }} {{ Number(customer.total_visits) === 1 ? 'visita' : 'visitas' }}
              <span v-if="Number(customer.total_spent) > 0"> · {{ formatBRL(customer.total_spent) }}</span>
            </p>
          </div>
          <ChevronRight :size="16" class="text-[#3D3D3D] flex-shrink-0" :stroke-width="1.75" />
        </Link>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { Users, Search, ChevronRight, Download } from 'lucide-vue-next'
import AppLayout from '../../layouts/AppLayout.vue'
import Skeleton from '../../components/Skeleton.vue'
import { useFormatting } from '../../composables/useFormatting'
import { useApi } from '../../composables/useApi'

const { formatBRL, formatPhone } = useFormatting()
const api = useApi()
const page = usePage()

// Exportar a base é decisão de conta: só o dono (a rota também exige owner).
const isOwner = computed(() => (page.props as any).auth?.user?.role === 'owner')

interface Customer {
  id: number
  name: string
  phone: string
  total_visits?: number | string
  total_spent?: number | string
}

const searchQuery = ref('')
const loading = ref(true)
const customers = ref<Customer[]>([])

let searchTimer: ReturnType<typeof setTimeout> | null = null

const initials = (name: string) =>
  name.trim().split(/\s+/).map((p) => p[0]).slice(0, 2).join('').toUpperCase()

const load = async () => {
  loading.value = true
  const q = searchQuery.value.trim()
  const url = q.length >= 2 ? `/api/customers?q=${encodeURIComponent(q)}` : '/api/customers'
  const res = await api.get<Customer[]>(url)
  if (res.ok) customers.value = (res.data as any) ?? []
  loading.value = false
}

const onSearchInput = () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(load, 300)
}

onMounted(load)
</script>
