<template>
  <AppLayout :title="title" show-back-button>
    <div v-if="loading" class="p-4 space-y-3">
      <Skeleton height="60px" />
      <Skeleton height="60px" />
      <Skeleton height="60px" />
    </div>

    <div v-else class="p-4 pb-32 space-y-2">
      <header class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4 mb-2">
        <p class="text-[10px] uppercase tracking-[0.08em] text-[#6B6B6B]">Estado</p>
        <p class="text-[14px] font-medium text-white mt-1">{{ state }}</p>
      </header>

      <div v-if="messages.length === 0" class="text-center py-12 text-[13px] text-[#6B6B6B]">
        Nenhuma mensagem ainda.
      </div>

      <div
        v-for="m in messages"
        :key="m.id"
        class="flex"
        :class="m.direction === 'outgoing' ? 'justify-end' : 'justify-start'"
      >
        <div
          class="max-w-[80%] rounded-[14px] px-3 py-2"
          :class="m.direction === 'outgoing' ? 'bg-[#FFD60A]/15 border border-[#FFD60A]/30' : 'bg-[#131313] border border-[#2A2A2A]'"
        >
          <p class="text-[13px] text-white whitespace-pre-wrap leading-relaxed">{{ m.content }}</p>
          <p class="text-[10px] text-[#6B6B6B] mt-1 tabular-nums text-right">{{ formatTime(m.created_at) }}</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'
import Skeleton from '../../components/Skeleton.vue'
import { useApi } from '../../composables/useApi'

const api = useApi()

interface Message {
  id: number
  direction: 'incoming' | 'outgoing'
  content: string
  created_at: string
}

const page = usePage()
const conversationId = page.url.split('/').filter(Boolean)[1]

const loading = ref(true)
const phoneNumber = ref('')
const state = ref('')
const messages = ref<Message[]>([])

const title = computed(() => phoneNumber.value || 'Conversa')

const formatTime = (iso: string) => {
  const d = new Date(iso)
  return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

const load = async () => {
  const res = await api.get(`/api/whatsapp/conversations/${conversationId}`)
  if (res.ok && res.data) {
    const c: any = res.data
    phoneNumber.value = c.phone_number ?? phoneNumber.value
    state.value = c.state ?? state.value
    messages.value = c.messages ?? []
  }
  loading.value = false
}

let pollTimer: ReturnType<typeof setInterval> | null = null

onMounted(async () => {
  await load()
  pollTimer = setInterval(load, 10000)
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
})
</script>
