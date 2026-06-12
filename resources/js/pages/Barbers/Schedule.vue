<template>
  <AppLayout title="Horários do barbeiro" show-back-button :back-href="`/barbers/${barberId}`">
    <div v-if="loading" class="p-4 space-y-3">
      <Skeleton v-for="i in 7" :key="i" height="56px" />
    </div>

    <div v-else class="p-4 pb-32 space-y-6">
      <!-- Cabeçalho do barbeiro -->
      <div class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4">
        <p class="text-[10px] uppercase tracking-[0.08em] font-medium text-[#6B6B6B]">Barbeiro</p>
        <h2 class="text-[18px] font-semibold text-white mt-1">{{ barberName }}</h2>
      </div>

      <!-- Schedule semanal -->
      <section>
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-[15px] font-semibold text-white">Horário semanal</h3>
          <p v-if="scheduleStatus" class="text-[11px] text-[#22C55E]">{{ scheduleStatus }}</p>
        </div>

        <div class="space-y-2">
          <div
            v-for="d in days"
            :key="d.value"
            class="bg-[#131313] border border-[#2A2A2A] rounded-[10px] p-3 flex items-center gap-3"
          >
            <div class="w-12 text-[13px] font-medium text-[#A1A1A1] flex-shrink-0">{{ d.label }}</div>

            <input
              v-model="schedules[d.value].start_time"
              type="time"
              class="flex-1 h-10 px-3 bg-[#161616] border border-[#2A2A2A] rounded-[8px] text-[13px] text-white outline-none focus:border-[#FFD60A]"
            />
            <span class="text-[#6B6B6B] text-[12px]">às</span>
            <input
              v-model="schedules[d.value].end_time"
              type="time"
              class="flex-1 h-10 px-3 bg-[#161616] border border-[#2A2A2A] rounded-[8px] text-[13px] text-white outline-none focus:border-[#FFD60A]"
            />

            <button
              type="button"
              :disabled="!schedules[d.value].start_time || !schedules[d.value].end_time || savingDay === d.value"
              @click="saveDay(d.value)"
              class="h-10 px-3 rounded-[8px] text-[12px] font-medium text-[#0A0A0A] bg-[#FFD60A] enabled:hover:bg-[#FFE066] disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {{ savingDay === d.value ? '...' : 'Salvar' }}
            </button>
          </div>
        </div>
      </section>

      <!-- Time-offs -->
      <section>
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-[15px] font-semibold text-white">Folgas e férias</h3>
          <button
            type="button"
            @click="addTimeOffOpen = true"
            class="text-[12px] font-medium text-[#FFD60A] hover:text-[#FFE066]"
          >
            + Adicionar
          </button>
        </div>

        <div v-if="timeOffs.length === 0" class="text-[13px] text-[#6B6B6B] text-center py-6">
          Nenhuma folga cadastrada.
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="t in timeOffs"
            :key="t.date"
            class="bg-[#131313] border border-[#2A2A2A] rounded-[10px] p-3 flex items-center justify-between gap-3"
          >
            <div class="flex-1 min-w-0">
              <p class="text-[14px] font-medium text-white">{{ formatRange(t) }}</p>
              <p class="text-[11px] text-[#6B6B6B] mt-0.5">{{ rangeDays(t) }}</p>
              <p v-if="t.reason" class="text-[12px] text-[#A1A1A1] mt-0.5">{{ t.reason }}</p>
            </div>
            <button
              type="button"
              @click="removeTimeOff(t.date)"
              class="h-9 px-3 rounded-[8px] text-[12px] font-medium text-[#EF4444] border border-[#EF4444]/30 hover:bg-[#EF4444]/10 transition-colors"
            >
              Remover
            </button>
          </div>
        </div>
      </section>
    </div>

    <!-- Bottom sheet: add time-off -->
    <Teleport to="body">
      <div
        v-if="addTimeOffOpen"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40"
        @click="addTimeOffOpen = false"
      ></div>
      <div
        v-if="addTimeOffOpen"
        class="fixed bottom-0 left-0 right-0 z-50 bg-[#131313] border-t border-[#2A2A2A] rounded-t-[20px] p-5 pb-8 space-y-4"
      >
        <div class="w-10 h-1 bg-[#3D3D3D] rounded-full mx-auto"></div>
        <h3 class="text-[16px] font-semibold text-white">Nova folga</h3>

        <div class="space-y-3">
          <div class="flex gap-3">
            <div class="flex-1">
              <label class="block text-[12px] text-[#A1A1A1] mb-1.5 ml-1">Início</label>
              <input
                v-model="newTimeOff.date"
                type="date"
                :min="todayStr"
                class="block w-full h-12 px-4 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[14px] text-white outline-none focus:border-[#FFD60A]"
              />
            </div>
            <div class="flex-1">
              <label class="block text-[12px] text-[#A1A1A1] mb-1.5 ml-1">Fim <span class="text-[#6B6B6B]">(opcional)</span></label>
              <input
                v-model="newTimeOff.end_date"
                type="date"
                :min="newTimeOff.date || todayStr"
                class="block w-full h-12 px-4 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[14px] text-white outline-none focus:border-[#FFD60A]"
              />
            </div>
          </div>
          <input
            v-model="newTimeOff.reason"
            type="text"
            placeholder="Motivo (opcional)"
            maxlength="255"
            class="block w-full h-12 px-4 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[14px] text-white outline-none focus:border-[#FFD60A] placeholder-[#6B6B6B]"
          />
        </div>

        <p v-if="timeOffError" class="text-[12px] text-[#EF4444] px-1">{{ timeOffError }}</p>

        <div class="flex gap-3">
          <button
            type="button"
            @click="addTimeOffOpen = false"
            class="flex-1 h-12 rounded-[10px] text-[14px] font-medium text-[#A1A1A1] border border-[#2A2A2A] hover:text-white hover:border-[#3D3D3D] transition-colors"
          >
            Cancelar
          </button>
          <button
            type="button"
            :disabled="!newTimeOff.date || creatingTimeOff"
            @click="createTimeOff"
            class="flex-1 h-12 rounded-[10px] text-[14px] font-bold text-[#0A0A0A] bg-[#FFD60A] enabled:hover:bg-[#FFE066] disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ creatingTimeOff ? 'Salvando...' : 'Adicionar' }}
          </button>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'
import Skeleton from '../../components/Skeleton.vue'

const page = usePage()
const barberId = page.url.split('/').filter(Boolean)[1]

const days = [
  { value: 0, label: 'Dom' },
  { value: 1, label: 'Seg' },
  { value: 2, label: 'Ter' },
  { value: 3, label: 'Qua' },
  { value: 4, label: 'Qui' },
  { value: 5, label: 'Sex' },
  { value: 6, label: 'Sáb' },
]

const loading = ref(true)
const barberName = ref('')
const schedules = reactive<Record<number, { start_time: string; end_time: string }>>(
  Object.fromEntries(days.map((d) => [d.value, { start_time: '', end_time: '' }]))
)
const timeOffs = ref<Array<{ date: string; end_date: string | null; reason: string | null }>>([])

const savingDay = ref<number | null>(null)
const scheduleStatus = ref('')

const addTimeOffOpen = ref(false)
const creatingTimeOff = ref(false)
const newTimeOff = reactive({ date: '', end_date: '', reason: '' })
const timeOffError = ref('')

const xsrf = () =>
  decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

const pad = (n: number) => String(n).padStart(2, '0')
const today0 = new Date()
const todayStr = `${today0.getFullYear()}-${pad(today0.getMonth() + 1)}-${pad(today0.getDate())}`

const fmt = (iso: string) => {
  const [y, m, d] = iso.split('-')
  return `${d}/${m}/${y}`
}
const formatRange = (t: { date: string; end_date: string | null }) =>
  t.end_date && t.end_date !== t.date ? `${fmt(t.date)} a ${fmt(t.end_date)}` : fmt(t.date)
const rangeDays = (t: { date: string; end_date: string | null }) => {
  if (!t.end_date || t.end_date === t.date) return '1 dia'
  const a = new Date(t.date + 'T00:00:00').getTime()
  const b = new Date(t.end_date + 'T00:00:00').getTime()
  return `${Math.round((b - a) / 86400000) + 1} dias`
}

onMounted(async () => {
  try {
    const res = await fetch(`/api/barbers/${barberId}`, {
      headers: { Accept: 'application/json' },
    })
    if (res.ok) {
      const json = await res.json()
      const b = json.data ?? json
      barberName.value = b.name ?? ''
      const list = b.schedules ?? []
      for (const s of list) {
        if (schedules[s.day_of_week]) {
          schedules[s.day_of_week].start_time = (s.start_time ?? '').slice(0, 5)
          schedules[s.day_of_week].end_time = (s.end_time ?? '').slice(0, 5)
        }
      }
      timeOffs.value = b.time_offs ?? b.timeOffs ?? []
    }
  } finally {
    loading.value = false
  }
})

const saveDay = async (day: number) => {
  savingDay.value = day
  scheduleStatus.value = ''
  try {
    const res = await fetch(`/api/barbers/${barberId}/schedule/${day}`, {
      method: 'PUT',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrf(),
      },
      body: JSON.stringify({
        start_time: schedules[day].start_time,
        end_time: schedules[day].end_time,
      }),
    })
    if (res.ok) {
      scheduleStatus.value = 'Salvo'
      setTimeout(() => (scheduleStatus.value = ''), 2000)
    }
  } finally {
    savingDay.value = null
  }
}

const createTimeOff = async () => {
  timeOffError.value = ''
  creatingTimeOff.value = true
  try {
    const res = await fetch(`/api/barbers/${barberId}/time-off`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrf(),
      },
      body: JSON.stringify({
        date: newTimeOff.date,
        end_date: newTimeOff.end_date || null,
        reason: newTimeOff.reason || null,
      }),
    })
    if (res.ok || res.status === 201) {
      const json = await res.json().catch(() => null)
      const created = json?.data ?? json ?? {
        date: newTimeOff.date,
        end_date: newTimeOff.end_date || null,
        reason: newTimeOff.reason || null,
      }
      timeOffs.value = [...timeOffs.value, created].sort((a, b) => a.date.localeCompare(b.date))
      newTimeOff.date = ''
      newTimeOff.end_date = ''
      newTimeOff.reason = ''
      addTimeOffOpen.value = false
    } else if (res.status === 422) {
      const body = await res.json().catch(() => ({}))
      timeOffError.value =
        body?.errors?.date?.[0] ?? body?.errors?.end_date?.[0] ?? body?.message ?? 'Data inválida.'
    } else {
      timeOffError.value = `Erro ${res.status}.`
    }
  } finally {
    creatingTimeOff.value = false
  }
}

const removeTimeOff = async (date: string) => {
  const res = await fetch(`/api/barbers/${barberId}/time-off/${date}`, {
    method: 'DELETE',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-XSRF-TOKEN': xsrf(),
    },
  })
  if (res.ok || res.status === 204) {
    timeOffs.value = timeOffs.value.filter((t) => t.date !== date)
  }
}
</script>
