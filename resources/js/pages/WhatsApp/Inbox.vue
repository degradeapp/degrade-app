<template>
  <AppLayout title="WhatsApp" show-back-button>
    <div v-if="loading" class="p-4 space-y-3">
      <Skeleton v-for="i in 4" :key="i" height="72px" />
    </div>

    <div v-else-if="!hasAccount" class="flex flex-col items-center justify-center gap-4 py-16 px-4">
      <div class="w-16 h-16 rounded-full bg-[#FFD60A]/15 flex items-center justify-center">
        <MessageCircle :size="32" class="text-[#FFD60A]" :stroke-width="1.75" />
      </div>
      <div class="text-center max-w-xs">
        <h2 class="text-[18px] font-semibold text-white mb-1">WhatsApp Bot</h2>
        <p class="text-[13px] text-[#A1A1A1] leading-relaxed">
          Configure sua conta da Cloud API Meta para começar a automatizar agendamentos pelo WhatsApp.
        </p>
      </div>
      <Link
        href="/whatsapp/setup"
        class="mt-4 h-12 px-5 inline-flex items-center rounded-[10px] bg-[#FFD60A] text-[14px] font-bold text-[#0A0A0A] hover:bg-[#FFE066]"
      >
        Configurar
      </Link>
    </div>

    <div v-else class="p-4 pb-24 space-y-2">
      <div v-if="conversations.length === 0" class="text-center py-16 text-[13px] text-[#6B6B6B]">
        Nenhuma conversa ainda. Quando alguém mandar mensagem, vai aparecer aqui.
      </div>

      <Link
        v-for="c in conversations"
        :key="c.id"
        :href="`/whatsapp/${c.id}`"
        class="block bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-3 hover:border-[#3D3D3D] transition-colors"
      >
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 rounded-full bg-[#1A1A1A] border border-[#2A2A2A] flex items-center justify-center text-[#A1A1A1]">
            <MessageCircle :size="16" :stroke-width="1.75" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2">
              <p class="text-[14px] font-medium text-white truncate">
                {{ c.customer_name ?? c.phone_number }}
              </p>
              <span class="text-[11px] text-[#6B6B6B] tabular-nums flex-shrink-0">{{ formatTime(c.last_interaction_at) }}</span>
            </div>
            <p class="text-[12px] text-[#A1A1A1] mt-0.5 truncate">
              {{ c.state_label || c.state }}
            </p>
          </div>
        </div>
      </Link>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { MessageCircle } from 'lucide-vue-next'
import AppLayout from '../../layouts/AppLayout.vue'
import Skeleton from '../../components/Skeleton.vue'

interface Conversation {
  id: number
  phone_number: string
  customer_name?: string | null
  state: string
  state_label?: string
  last_interaction_at?: string | null
}

const loading = ref(true)
const hasAccount = ref(false)
const conversations = ref<Conversation[]>([])

const formatTime = (iso?: string | null) => {
  if (!iso) return ''
  const d = new Date(iso)
  const now = new Date()
  const sameDay = d.toDateString() === now.toDateString()
  if (sameDay) return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
}

onMounted(async () => {
  try {
    const [acctRes, convRes] = await Promise.all([
      fetch('/api/whatsapp/account', { headers: { Accept: 'application/json' } }),
      fetch('/api/whatsapp/conversations', { headers: { Accept: 'application/json' } }),
    ])
    if (acctRes.ok) {
      const json = await acctRes.json()
      hasAccount.value = !!json.data
    }
    if (convRes.ok) {
      const json = await convRes.json()
      conversations.value = json.data ?? []
    }
  } finally {
    loading.value = false
  }
})
</script>
