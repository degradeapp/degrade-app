<template>
  <AppLayout title="Histórico de atividades" show-back-button>
    <div v-if="loading" class="p-4 space-y-2">
      <Skeleton v-for="i in 5" :key="i" height="60px" />
    </div>

    <div v-else-if="logs.length === 0" class="text-center py-16 text-[13px] text-[#6B6B6B]">
      Nenhuma atividade registrada ainda.
    </div>

    <div v-else class="p-4 pb-24 space-y-2 stagger">
      <div
        v-for="log in logs"
        :key="log.id"
        class="bg-[#131313] border border-[#2A2A2A] rounded-[12px] p-3"
      >
        <div class="flex items-start justify-between gap-3 mb-1">
          <p class="text-[13px] font-medium text-white">
            <span :class="actionColor(log.action)">{{ actionLabel(log.action) }}</span>
            <span class="text-[#A1A1A1] ml-1">{{ log.model_label }}</span>
            <template v-if="log.entity_label"><span class="text-white"> · {{ log.entity_label }}</span></template>
            <span v-else class="text-[#6B6B6B]"> #{{ log.model_id }}</span>
          </p>
          <span class="text-[11px] text-[#6B6B6B] tabular-nums flex-shrink-0">{{ formatTime(log.created_at) }}</span>
        </div>
        <p v-if="log.user_name" class="text-[12px] text-[#A1A1A1]">
          por {{ log.user_name }}
        </p>
      </div>

      <button
        v-if="hasMore"
        type="button"
        :disabled="loadingMore"
        @click="loadMore"
        class="w-full h-11 rounded-[10px] border border-[#2A2A2A] text-[13px] font-medium text-[#A1A1A1] hover:text-white hover:border-[#3D3D3D] transition-colors flex items-center justify-center gap-2 disabled:opacity-60"
      >
        <Loader2 v-if="loadingMore" :size="16" class="animate-spin" />
        {{ loadingMore ? 'Carregando...' : 'Carregar mais' }}
      </button>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Loader2 } from 'lucide-vue-next'
import AppLayout from '../../layouts/AppLayout.vue'
import Skeleton from '../../components/Skeleton.vue'

interface Log {
  id: number
  action: string
  model_label: string
  model_id: number
  entity_label?: string | null
  user_name?: string
  metadata?: any
  created_at: string
}

const loading = ref(true)
const loadingMore = ref(false)
const logs = ref<Log[]>([])
const page = ref(1)
const lastPage = ref(1)
const hasMore = computed(() => page.value < lastPage.value)

const actionLabel = (a: string) =>
  ({ created: 'Criou', updated: 'Atualizou', deleted: 'Removeu' }[a] ?? a)

const actionColor = (a: string) =>
  ({
    created: 'text-[#22C55E]',
    updated: 'text-[#FFD60A]',
    deleted: 'text-[#EF4444]',
  }[a] ?? 'text-white')

const formatTime = (iso: string) => {
  if (!iso) return ''
  const d = new Date(iso)
  const now = new Date()
  const sameDay = d.toDateString() === now.toDateString()
  if (sameDay) return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

const loadPage = async (p: number) => {
  const res = await fetch(`/api/audit?page=${p}`, { headers: { Accept: 'application/json' } })
  if (res.ok) {
    const json = await res.json()
    const rows = (json.data ?? []) as Log[]
    logs.value = p === 1 ? rows : [...logs.value, ...rows]
    lastPage.value = json.meta?.last_page ?? p
    page.value = p
  }
}

const loadMore = async () => {
  if (loadingMore.value || !hasMore.value) return
  loadingMore.value = true
  try {
    await loadPage(page.value + 1)
  } finally {
    loadingMore.value = false
  }
}

onMounted(async () => {
  try {
    await loadPage(1)
  } finally {
    loading.value = false
  }
})
</script>
