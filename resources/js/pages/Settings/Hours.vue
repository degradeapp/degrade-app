<template>
  <AppLayout title="Horários da barbearia" show-back-button>
    <div v-if="loading" class="p-4 space-y-3">
      <Skeleton v-for="i in 7" :key="i" height="56px" />
    </div>

    <div v-else class="p-4 pb-32 space-y-3 animate-enter">
      <p class="text-[12px] text-[#A1A1A1] mb-4 leading-relaxed">
        Estes são os horários padrão da barbearia. Cada barbeiro pode ter seu próprio horário individual em
        <span class="text-white">Equipe → Barbeiros</span>.
      </p>

      <div
        v-for="d in days"
        :key="d.value"
        class="bg-[#131313] border border-[#2A2A2A] rounded-[10px] p-3 flex items-center gap-3"
      >
        <div class="w-14 text-[13px] font-medium text-[#A1A1A1] flex-shrink-0">{{ d.label }}</div>

        <label class="flex items-center gap-2 text-[12px] text-[#A1A1A1] cursor-pointer">
          <input
            v-model="hours[d.value].closed"
            type="checkbox"
            class="w-4 h-4 accent-[#FFD60A]"
          />
          Fechado
        </label>

        <template v-if="!hours[d.value].closed">
          <input
            v-model="hours[d.value].start_time"
            type="time"
            class="flex-1 h-10 px-3 bg-[#161616] border border-[#2A2A2A] rounded-[8px] text-[13px] text-white outline-none focus:border-[#FFD60A]"
          />
          <span class="text-[#6B6B6B] text-[12px]">às</span>
          <input
            v-model="hours[d.value].end_time"
            type="time"
            class="flex-1 h-10 px-3 bg-[#161616] border border-[#2A2A2A] rounded-[8px] text-[13px] text-white outline-none focus:border-[#FFD60A]"
          />
        </template>
      </div>

      <div class="fixed bottom-0 left-0 right-0 bg-[#0A0A0A] border-t border-[#1F1F1F] p-4">
        <Button type="button" variant="primary" class="w-full" :loading="saving" loading-text="Salvando..." @click="save">
          Salvar horários
        </Button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import AppLayout from '../../layouts/AppLayout.vue'
import Button from '../../components/Button.vue'
import Skeleton from '../../components/Skeleton.vue'
import { useToast } from '../../composables/useToast'

const days = [
  { value: 0, label: 'Domingo' },
  { value: 1, label: 'Segunda' },
  { value: 2, label: 'Terça' },
  { value: 3, label: 'Quarta' },
  { value: 4, label: 'Quinta' },
  { value: 5, label: 'Sexta' },
  { value: 6, label: 'Sábado' },
]

const toast = useToast()
const loading = ref(true)
const saving = ref(false)

const hours = reactive<Record<number, { start_time: string; end_time: string; closed: boolean }>>(
  Object.fromEntries(days.map((d) => [d.value, { start_time: '09:00', end_time: '18:00', closed: false }]))
)

const xsrf = () =>
  decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

onMounted(async () => {
  try {
    const res = await fetch('/api/tenant/settings', { headers: { Accept: 'application/json' } })
    if (res.ok) {
      const json = await res.json()
      const list = json?.data?.business_hours ?? []
      for (const h of list) {
        if (hours[h.day_of_week]) {
          hours[h.day_of_week].start_time = (h.start_time ?? '09:00').slice(0, 5)
          hours[h.day_of_week].end_time = (h.end_time ?? '18:00').slice(0, 5)
          hours[h.day_of_week].closed = !!h.closed
        }
      }
    }
  } finally {
    loading.value = false
  }
})

const save = async () => {
  saving.value = true
  const payload = days.map((d) => ({
    day_of_week: d.value,
    start_time: hours[d.value].closed ? null : hours[d.value].start_time,
    end_time: hours[d.value].closed ? null : hours[d.value].end_time,
    closed: hours[d.value].closed,
  }))

  try {
    const res = await fetch('/api/tenant/business-hours', {
      method: 'PUT',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrf(),
      },
      body: JSON.stringify({ business_hours: payload }),
    })
    if (res.ok) {
      toast.success('Horários salvos')
    }
  } finally {
    saving.value = false
  }
}
</script>
