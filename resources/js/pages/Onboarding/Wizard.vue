<template>
  <div class="min-h-dvh w-full bg-[#0A0A0A] text-[#F5F5F5]">
    <!-- Top progress -->
    <div class="sticky top-0 z-10 bg-[#0A0A0A] border-b border-[#1F1F1F]">
      <div class="max-w-[640px] mx-auto px-5 py-4 flex items-center gap-3">
        <button
          v-if="step > 1"
          type="button"
          class="text-[#A1A1A1] hover:text-white"
          @click="step--"
        >
          <ChevronLeft :size="20" :stroke-width="1.75" />
        </button>
        <div class="flex-1 h-1 bg-[#1F1F1F] rounded-full overflow-hidden">
          <div
            class="h-full bg-[#FFD60A] transition-all duration-300"
            :style="{ width: progress + '%' }"
          ></div>
        </div>
        <span class="text-[11px] text-[#6B6B6B] tabular-nums">{{ step }}/4</span>
        <button
          v-if="step > 1 && step < 4"
          type="button"
          class="text-[12px] text-[#6B6B6B] hover:text-white ml-2"
          @click="skipToEnd"
        >
          Pular
        </button>
      </div>
    </div>

    <div class="max-w-[640px] mx-auto px-5 py-6 pb-32">
      <!-- STEP 1: Business -->
      <section v-if="step === 1" :key="step" class="space-y-5 animate-enter">
        <header class="space-y-1">
          <h2 class="text-[22px] font-bold text-white">Bem-vindo ao Degradê</h2>
          <p class="text-[13px] text-[#A1A1A1]">Vamos configurar sua barbearia em 4 passos rápidos.</p>
        </header>

        <div class="space-y-3">
          <input
            v-model="business.name"
            type="text"
            maxlength="100"
            placeholder="Nome da barbearia"
            class="block w-full h-12 px-4 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white outline-none focus:border-[#FFD60A] placeholder-[#6B6B6B]"
          />
          <SelectField v-model="business.timezone" :options="BR_TIMEZONES" title="Fuso horário" />
        </div>
      </section>

      <!-- STEP 2: Hours -->
      <section v-else-if="step === 2" :key="step" class="space-y-5 animate-enter">
        <header class="space-y-1">
          <h2 class="text-[22px] font-bold text-white">Horário de funcionamento</h2>
          <p class="text-[13px] text-[#A1A1A1]">Os barbeiros herdarão esses horários por padrão.</p>
        </header>

        <div class="space-y-2">
          <div
            v-for="d in days"
            :key="d.value"
            class="bg-[#131313] border border-[#2A2A2A] rounded-[10px] p-3 flex items-center gap-3"
          >
            <div class="w-14 text-[13px] font-medium text-[#A1A1A1]">{{ d.label }}</div>
            <label class="flex items-center gap-1.5 text-[11px] text-[#A1A1A1] cursor-pointer">
              <input v-model="hours[d.value].closed" type="checkbox" class="w-4 h-4 accent-[#FFD60A]" />
              Fechado
            </label>
            <template v-if="!hours[d.value].closed">
              <input
                v-model="hours[d.value].start_time"
                type="time"
                class="flex-1 h-10 px-2 bg-[#161616] border border-[#2A2A2A] rounded-[8px] text-[13px] text-white outline-none focus:border-[#FFD60A]"
              />
              <span class="text-[#6B6B6B] text-[12px]">às</span>
              <input
                v-model="hours[d.value].end_time"
                type="time"
                class="flex-1 h-10 px-2 bg-[#161616] border border-[#2A2A2A] rounded-[8px] text-[13px] text-white outline-none focus:border-[#FFD60A]"
              />
            </template>
          </div>
        </div>
      </section>

      <!-- STEP 3: Services -->
      <section v-else-if="step === 3" :key="step" class="space-y-4 animate-enter">
        <header class="space-y-1">
          <h2 class="text-[22px] font-bold text-white">Seus serviços</h2>
          <p class="text-[13px] text-[#A1A1A1]">Marque os que você oferece. O preço base vale pra todos, e você ajusta as exceções ao lado de cada um.</p>
        </header>

        <div>
          <label class="block text-[13px] text-[#A1A1A1] mb-1.5">Preço base (R$)</label>
          <div class="relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[14px] text-[#6B6B6B] pointer-events-none">R$</span>
            <input
              :value="basePrice ?? ''"
              type="number"
              step="0.01"
              inputmode="decimal"
              placeholder="Ex: 40"
              class="block w-full h-12 pl-10 pr-4 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white outline-none focus:border-[#FFD60A] placeholder-[#6B6B6B] tabular-nums"
              @input="onBasePriceInput"
            />
          </div>
          <p class="text-[11px] text-[#6B6B6B] mt-1.5">Aplicado a todos os marcados. Mude o preço de uma exceção (tesoura, pintura…) ao lado dela.</p>
        </div>

        <div class="space-y-2">
          <div
            v-for="item in serviceItems"
            :key="item.name"
            class="flex items-center gap-3 bg-[#131313] border rounded-[10px] px-3 h-12 transition-colors"
            :class="item.selected ? 'border-[#FFD60A]/40' : 'border-[#2A2A2A]'"
          >
            <button type="button" class="flex items-center gap-2.5 flex-1 min-w-0 text-left" @click="toggleService(item)">
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
                class="w-full h-9 pl-7 pr-2 bg-[#161616] border border-[#2A2A2A] rounded-[8px] text-[14px] text-white outline-none focus:border-[#FFD60A] placeholder-[#6B6B6B] tabular-nums"
                @input="(e) => onItemPriceInput(e, item)"
              />
            </div>
          </div>
        </div>
      </section>

      <!-- STEP 4: Done -->
      <section v-else :key="step" class="space-y-5 text-center animate-enter">
        <div class="w-16 h-16 mx-auto rounded-full bg-[#22C55E]/15 flex items-center justify-center">
          <Check :size="32" :stroke-width="2.5" class="text-[#22C55E]" />
        </div>
        <h2 class="text-[22px] font-bold text-white">Tudo pronto!</h2>
        <p class="text-[13px] text-[#A1A1A1] leading-relaxed">
          Sua barbearia está configurada. Você pode começar a aceitar agendamentos agora.
          O WhatsApp Bot pode ser ativado depois em Configurações.
        </p>
      </section>
    </div>

    <!-- Bottom button -->
    <div class="fixed bottom-0 left-0 right-0 bg-[#0A0A0A] border-t border-[#1F1F1F]">
      <div class="max-w-[640px] mx-auto px-5 py-4">
        <button
          type="button"
          :disabled="!canAdvance || isLoading"
          @click="onNext"
          class="w-full h-12 rounded-[10px] font-bold text-[15px] text-[#0A0A0A] flex items-center justify-center gap-2 transition-all duration-150 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-70 bg-[#FFD60A] enabled:hover:bg-[#FFE066] enabled:active:bg-[#F5C400]"
        >
          <Loader2 v-if="isLoading" :size="18" class="animate-spin" />
          {{ buttonLabel }}
        </button>
      </div>
    </div>

    <Toast />
    <ConfirmDialog />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Check, ChevronLeft, Loader2 } from 'lucide-vue-next'
import { COMMON_SERVICES } from '@/data/commonServices'
import { capPrice } from '@/data/numericInput'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import Toast from '@/components/Toast.vue'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import SelectField from '@/components/SelectField.vue'
import { BR_TIMEZONES } from '@/data/timezones'

const { ask } = useConfirm()
const toast = useToast()

const step = ref(1)
const isLoading = ref(false)

const progress = computed(() => (step.value / 4) * 100)

const days = [
  { value: 0, label: 'Dom' },
  { value: 1, label: 'Seg' },
  { value: 2, label: 'Ter' },
  { value: 3, label: 'Qua' },
  { value: 4, label: 'Qui' },
  { value: 5, label: 'Sex' },
  { value: 6, label: 'Sáb' },
]

const business = reactive({ name: '', timezone: 'America/Manaus' })

const hours = reactive<Record<number, { start_time: string; end_time: string; closed: boolean }>>({
  0: { start_time: '09:00', end_time: '18:00', closed: true },
  1: { start_time: '09:00', end_time: '18:00', closed: false },
  2: { start_time: '09:00', end_time: '18:00', closed: false },
  3: { start_time: '09:00', end_time: '18:00', closed: false },
  4: { start_time: '09:00', end_time: '18:00', closed: false },
  5: { start_time: '09:00', end_time: '18:00', closed: false },
  6: { start_time: '09:00', end_time: '14:00', closed: false },
})

const basePrice = ref<number | null>(null)
// touched = preço editado/limpo manualmente (não sincroniza mais com o base)
const serviceItems = reactive(
  COMMON_SERVICES.map((s) => ({ name: s.name, selected: s.preselected, price: null as number | null, touched: false }))
)
// Preço final de cada item: o próprio, ou o preço base.
const resolvedPrice = (item: { price: number | null }) =>
  typeof item.price === 'number' ? item.price : basePrice.value
const selectedServices = () => serviceItems.filter((i) => i.selected)

// Marca/desmarca serviço; ao marcar, herda o preço base (se ainda não editado)
const toggleService = (item: { selected: boolean; touched: boolean; price: number | null }) => {
  item.selected = !item.selected
  if (item.selected && !item.touched && basePrice.value != null) item.price = basePrice.value
}

// Preço: até 6 dígitos + 2 decimais
const onBasePriceInput = (e: Event) => {
  const el = e.target as HTMLInputElement
  const v = capPrice(el.value)
  basePrice.value = v === '' || v === '.' ? null : Number(v)
  if (v !== el.value && !v.endsWith('.')) el.value = v
  // Sincroniza os itens que o dono não editou manualmente
  serviceItems.forEach((i) => {
    if (i.selected && !i.touched) i.price = basePrice.value
  })
}
const onItemPriceInput = (e: Event, item: { price: number | null; touched: boolean }) => {
  const el = e.target as HTMLInputElement
  const v = capPrice(el.value)
  item.price = v === '' || v === '.' ? null : Number(v)
  item.touched = true // a partir de agora não sincroniza mais com o base (inclusive vazio)
  if (v !== el.value && !v.endsWith('.')) el.value = v
}

const canAdvance = computed(() => {
  if (step.value === 1) return business.name.trim().length > 1 && business.timezone
  if (step.value === 2) return true
  // Só exige ter ao menos 1 marcado pra liberar o botão; a falta de preço é avisada
  // com mensagem ao clicar (onNext), em vez de travar o botão sem explicar.
  if (step.value === 3) return selectedServices().length > 0
  return true
})

const buttonLabel = computed(() => {
  if (isLoading.value) return 'Salvando...'
  if (step.value === 4) return 'Acessar minha barbearia'
  return 'Continuar'
})

const xsrf = () =>
  decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

const callApi = async (path: string, body: any) => {
  const res = await fetch(path, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-XSRF-TOKEN': xsrf(),
    },
    body: JSON.stringify(body),
  })
  if (!res.ok) {
    const data = await res.json().catch(() => ({}))
    // 409 → onboarding já concluído (ex.: voltou pelo navegador). Não trava o usuário
    // num erro: manda direto pro início, que é onde ele deveria estar.
    if (res.status === 409) {
      router.visit('/')
      throw new Error('__redirected__')
    }
    // 422 → erro do 1º campo (limpo). 5xx → mensagem genérica (não vaza SQL/stack).
    if (res.status === 422 && data.errors) {
      const first = (Object.values(data.errors)[0] as string[] | undefined)?.[0]
      throw new Error(first ?? 'Verifique os campos e tente novamente.')
    }
    if (res.status >= 500) {
      throw new Error('Algo deu errado ao salvar. Tente de novo em instantes.')
    }
    throw new Error(data.message ?? `Erro ${res.status}.`)
  }
  return res.json().catch(() => ({}))
}

const skipToEnd = async () => {
  const ok = await ask(
    'Pular configuração?',
    'Você pode cadastrar os serviços depois em Configurações.',
    { confirmText: 'Pular', cancelText: 'Voltar' }
  )
  if (!ok) return
  step.value = 4
}

const onNext = async () => {
  isLoading.value = true
  try {
    if (step.value === 1) {
      await callApi('/api/onboarding/business', business)
      step.value = 2
    } else if (step.value === 2) {
      const payload = days.map((d) => ({
        day_of_week: d.value,
        start_time: hours[d.value].closed ? null : hours[d.value].start_time,
        end_time: hours[d.value].closed ? null : hours[d.value].end_time,
        closed: hours[d.value].closed,
      }))
      await callApi('/api/onboarding/hours', { business_hours: payload })
      step.value = 3
    } else if (step.value === 3) {
      const sel = selectedServices()
      const semPreco = sel.some((i) => {
        const p = resolvedPrice(i)
        return typeof p !== 'number' || p < 0
      })
      if (semPreco) {
        toast.error('Os serviços marcados precisam de um preço. Defina o preço base ou o valor de cada um.')
        return
      }
      const services = sel.map((i) => ({ name: i.name, price: resolvedPrice(i) }))
      await callApi('/api/onboarding/service', { services })
      step.value = 4
    } else {
      const json = await callApi('/api/onboarding/complete', {})
      router.visit(json.redirect ?? '/')
    }
  } catch (e: any) {
    if (e?.message === '__redirected__') return
    toast.error(e?.message ?? 'Erro ao salvar.')
  } finally {
    isLoading.value = false
  }
}
</script>
