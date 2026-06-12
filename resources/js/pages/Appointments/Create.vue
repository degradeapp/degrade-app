<script setup lang="ts">
import { ref, reactive, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { X, ChevronLeft, ChevronRight, Plus, Check, Loader2, Zap } from 'lucide-vue-next'
import { useFormatting } from '@/composables/useFormatting'
import { useApi } from '@/composables/useApi'
import { capPrice } from '@/data/numericInput'

interface Customer {
  id: number
  name: string
  phone: string
  initials: string
  total_visits: number
}
interface Service {
  id: number
  name: string
  price: number
}
interface Barber {
  id: number
  name: string
  initials: string
  service_ids: number[]
}
interface Prefill {
  date?: string | null
  time?: string | null
  barber_id?: number | null
  customer_id?: number | null
}
interface Props {
  customers: Customer[]
  services: Service[]
  barbers: Barber[]
  prefill?: Prefill | null
}

const props = withDefaults(defineProps<Props>(), {
  customers: () => [],
  services: () => [],
  barbers: () => [],
  prefill: null,
})

const { formatBRL, formatPhone, parsePhone, formatDateLong } = useFormatting()
const api = useApi()

const STEP_TITLES = [
  'Quem é o cliente?',
  'Quais serviços?',
  'Quem vai atender?',
  'Quando?',
  'Confirmar agendamento',
]

const primaryBtn =
  'w-full h-12 rounded-[10px] font-bold text-[15px] text-[#0A0A0A] flex items-center justify-center gap-2 transition-all duration-150 active:scale-[0.98] disabled:cursor-not-allowed bg-[#FFD60A] enabled:hover:bg-[#FFE066] enabled:active:bg-[#F5C400] enabled:shadow-[0_8px_24px_-8px_rgba(255,214,10,0.5),inset_0_1px_0_rgba(255,255,255,0.25)] disabled:opacity-70'

const currentStep = ref(1)
const isLoading = ref(false)

const customerSearch = ref('')
const localCustomers = ref<Customer[]>([...props.customers])
const newCustomerOpen = ref(false)
const newCustomer = reactive({ name: '', phone: '', notes: '' })
const newCustomerError = ref('')
const savingCustomer = ref(false)

// Busca sob demanda de clientes além dos pré-carregados (escala p/ bases de 10k+)
const customerResults = ref<Customer[]>([])
const computeInitials = (name: string) =>
  name.trim().split(/\s+/).map((p) => p[0]).slice(0, 2).join('').toUpperCase()

let custSearchTimer: ReturnType<typeof setTimeout> | null = null
watch(customerSearch, (q) => {
  const term = q.trim()
  if (custSearchTimer) clearTimeout(custSearchTimer)
  if (term.length < 2) {
    customerResults.value = []
    return
  }
  custSearchTimer = setTimeout(async () => {
    try {
      const res = await fetch(`/api/customers?q=${encodeURIComponent(term)}&per_page=20`, {
        headers: { Accept: 'application/json' },
      })
      if (!res.ok) return
      const json = await res.json()
      const rows = (json.data ?? json) as Array<{ id: number; name: string; phone: string; total_visits?: number }>
      customerResults.value = rows.map((c) => ({
        id: c.id,
        name: c.name,
        phone: c.phone ?? '',
        initials: computeInitials(c.name),
        total_visits: Number(c.total_visits ?? 0),
      }))
    } catch {
      // falha silenciosa: cai no filtro local dos pré-carregados
    }
  }, 300)
})

const sameBarber = ref(true)
const barberSheetServiceId = ref<number | null>(null)

const selectedDay = ref('')
const discardOpen = ref(false)

const form = reactive({
  customer_id: null as number | null,
  service_ids: [] as number[],
  barberByService: {} as Record<number, number | null>,
  date: '',
  time: '',
  notes: '',
})

const pad = (n: number) => String(n).padStart(2, '0')
const localDateKey = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`

const selectedCustomer = computed(() => localCustomers.value.find((c) => c.id === form.customer_id) ?? null)
const selectedServices = computed(() => props.services.filter((s) => form.service_ids.includes(s.id)))

// Preço por serviço só para este atendimento (o barbeiro pode ajustar na hora).
const priceByService = reactive<Record<number, number>>({})
const priceOf = (s: Service) => priceByService[s.id] ?? s.price
const totalPrice = computed(() => selectedServices.value.reduce((sum, s) => sum + priceOf(s), 0))
const isSingleService = computed(() => form.service_ids.length === 1)

const filteredCustomers = computed(() => {
  const q = customerSearch.value.trim().toLowerCase()
  if (!q) return localCustomers.value
  const digits = q.replace(/\D/g, '')
  const localMatches = localCustomers.value.filter(
    (c) => c.name.toLowerCase().includes(q) || (digits.length > 0 && (c.phone ?? '').replace(/\D/g, '').includes(digits))
  )
  if (customerResults.value.length === 0) return localMatches
  // mescla resultados do servidor (clientes fora do pré-carregado) sem duplicar
  const seen = new Set(customerResults.value.map((c) => c.id))
  return [...customerResults.value, ...localMatches.filter((c) => !seen.has(c.id))]
})

const barbersForService = (serviceId: number) => props.barbers.filter((b) => b.service_ids.includes(serviceId))
const barbersForAll = computed(() =>
  props.barbers.filter((b) => form.service_ids.every((id) => b.service_ids.includes(id)))
)
const barberName = (id: number | null | undefined) => props.barbers.find((b) => b.id === id)?.name ?? ''
const isBarberForAllSelected = (id: number) =>
  form.service_ids.length > 0 && form.service_ids.every((sid) => form.barberByService[sid] === id)

const step3Valid = computed(
  () => form.service_ids.length > 0 && form.service_ids.every((id) => form.barberByService[id] != null)
)

const hasData = computed(
  () => form.customer_id !== null || form.service_ids.length > 0 || form.notes.trim() !== ''
)

// Fuso da loja (vem do backend); exibe/calcula horários nele, não no do navegador.
const TENANT_TZ = ((usePage().props as any).tenant?.timezone as string) || 'America/Manaus'
const now = ref(new Date())
const nowLabel = computed(() => {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: TENANT_TZ,
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(now.value)
  const h = parts.find((p) => p.type === 'hour')?.value ?? '00'
  const m = parts.find((p) => p.type === 'minute')?.value ?? '00'
  return `${h}:${m}`
})

// Disponibilidade REAL do dia (vinda do backend), por barbeiro escolhido.
// Substitui o mock: respeita a agenda do barbeiro, folgas e conflitos reais.
type DaySlot = { time: string; start_time: string; available: boolean; occupant: { customer: string } | null }
const daySlots = ref<DaySlot[]>([])
const loadingSlots = ref(false)
const worksToday = ref(true)
// Por que não atende neste dia: 'time_off' (folga) ou 'no_schedule' (fora do expediente).
const dayOffReason = ref<'time_off' | 'no_schedule'>('no_schedule')

const distinctBarberIds = computed(() => {
  const ids = form.service_ids
    .map((sid) => form.barberByService[sid])
    .filter((id): id is number => id != null)
  return [...new Set(ids)]
})

const loadDaySlots = async () => {
  const day = selectedDay.value
  const barberIds = distinctBarberIds.value
  if (!day || barberIds.length === 0) {
    daySlots.value = []
    return
  }

  loadingSlots.value = true
  try {
    const grids = await Promise.all(
      barberIds.map((id) =>
        fetch(`/api/appointments/availability/barber/${id}/day?date=${day}`, {
          headers: { Accept: 'application/json' },
        })
          .then((r) => (r.ok ? r.json() : { works_today: false, slots: [] }))
          .catch(() => ({ works_today: false, slots: [] }))
      )
    )

    // Só "atende hoje" se TODOS os barbeiros escolhidos atendem nesse dia.
    worksToday.value = grids.every((g) => g.works_today)
    if (!worksToday.value) {
      // Se algum dos bloqueadores é folga, a mensagem de folga é mais informativa.
      dayOffReason.value = grids.some((g) => !g.works_today && g.reason === 'time_off')
        ? 'time_off'
        : 'no_schedule'
      daySlots.value = []
      return
    }

    // Interseção por horário: livre só se livre em todos; ocupado se ocupado em qualquer um.
    const [first, ...rest] = grids
    daySlots.value = (first.slots as DaySlot[]).map((s) => {
      let available = s.available
      let occupant = s.occupant
      for (const g of rest) {
        const match = (g.slots as DaySlot[]).find((x) => x.time === s.time)
        if (!match || !match.available) {
          available = false
          if (!occupant && match?.occupant) occupant = match.occupant
        }
      }
      return { time: s.time, start_time: s.start_time, available, occupant }
    })
  } finally {
    loadingSlots.value = false
  }
}

const dayScroller = ref<HTMLElement | null>(null)
const centerSelectedDay = () => {
  nextTick(() => {
    const el = dayScroller.value?.querySelector('[data-selected="true"]') as HTMLElement | null
    el?.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' })
  })
}
watch(currentStep, (step) => {
  if (step === 4) {
    centerSelectedDay()
    loadDaySlots()
  }
})
watch(selectedDay, () => {
  if (currentStep.value === 4) loadDaySlots()
})

const days = computed(() => {
  const labels = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']
  const base = new Date()
  return Array.from({ length: 14 }, (_, i) => {
    const d = new Date(base)
    d.setDate(base.getDate() + i)
    return { date: localDateKey(d), weekday: labels[d.getDay()], dayNum: d.getDate(), isToday: i === 0 }
  })
})

const dateLabel = computed(() => {
  if (!form.date) return ''
  const [y, m, d] = form.date.split('-').map(Number)
  return formatDateLong(new Date(y, m - 1, d))
})

const goToStep = (n: number) => {
  currentStep.value = n
}

const selectCustomer = (c: Customer) => {
  form.customer_id = c.id
  goToStep(2)
}

const toggleService = (id: number) => {
  const i = form.service_ids.indexOf(id)
  if (i >= 0) {
    form.service_ids.splice(i, 1)
    delete form.barberByService[id]
    delete priceByService[id]
  } else {
    form.service_ids.push(id)
    form.barberByService[id] = null
    priceByService[id] = props.services.find((s) => s.id === id)?.price ?? 0
  }
}

const onServicePriceInput = (e: Event, id: number) => {
  const el = e.target as HTMLInputElement
  const v = capPrice(el.value)
  priceByService[id] = v === '' || v === '.' ? 0 : Number(v)
  if (v !== el.value && !v.endsWith('.')) el.value = v
}

const toggleSameBarber = () => {
  sameBarber.value = !sameBarber.value
}

const pickBarberForAll = (barberId: number) => {
  form.service_ids.forEach((id) => {
    form.barberByService[id] = barberId
  })
}

// Atalho mobile: toque duplo no barbeiro = selecionar + avançar (igual ao "Próximo").
const pickBarberForAllAndNext = (barberId: number) => {
  pickBarberForAll(barberId)
  if (step3Valid.value) goToStep(4)
}

const pickBarberForService = (barberId: number) => {
  if (barberSheetServiceId.value !== null) {
    form.barberByService[barberSheetServiceId.value] = barberId
  }
  barberSheetServiceId.value = null
}

const selectSlot = (time: string) => {
  form.date = selectedDay.value
  form.time = time
  goToStep(5)
}

const conflictSlot = ref<{ time: string; customer: string } | null>(null)
const onSlotTap = (slot: DaySlot) => {
  if (!slot.available) {
    conflictSlot.value = { time: slot.time, customer: slot.occupant?.customer ?? 'Ocupado' }
  } else {
    selectSlot(slot.time)
  }
}
const confirmOverbook = () => {
  const t = conflictSlot.value?.time
  conflictSlot.value = null
  if (t) selectSlot(t)
}

const useNow = () => {
  form.date = localDateKey(new Date())
  form.time = nowLabel.value
  goToStep(5)
}

const handleBack = () => {
  if (currentStep.value > 1) {
    currentStep.value--
    return
  }
  if (hasData.value) {
    discardOpen.value = true
  } else {
    closeFlow()
  }
}

const closeFlow = () => router.visit('/appointments')
const confirmDiscard = () => {
  discardOpen.value = false
  closeFlow()
}

const onNewCustomerPhone = (e: Event) => {
  const el = e.target as HTMLInputElement
  newCustomer.phone = formatPhone(parsePhone(el.value).slice(0, 11))
  el.value = newCustomer.phone
}

const saveNewCustomer = async () => {
  if (savingCustomer.value) return
  newCustomerError.value = ''
  if (!newCustomer.name.trim()) {
    newCustomerError.value = 'Informe o nome do cliente'
    return
  }
  // Telefone é opcional aqui (o barbeiro pode não ter o número). Se informar, valida.
  const digits = parsePhone(newCustomer.phone)
  if (digits.length > 0 && digits.length !== 11) {
    newCustomerError.value = 'Informe um celular válido com DDD (11 dígitos) ou deixe em branco'
    return
  }

  savingCustomer.value = true
  try {
    const res = await api.post('/api/customers', {
      name: newCustomer.name.trim(),
      phone: newCustomer.phone,
      notes: newCustomer.notes || null,
    })

    if (res.ok && res.data) {
      const c: any = res.data
      const created: Customer = {
        id: c.id,
        name: c.name,
        phone: c.phone ?? digits,
        initials: computeInitials(c.name),
        total_visits: Number(c.total_visits ?? 0),
      }
      localCustomers.value.unshift(created)
      newCustomerOpen.value = false
      newCustomer.name = ''
      newCustomer.phone = ''
      newCustomer.notes = ''
      customerSearch.value = ''
      customerResults.value = []
      selectCustomer(created)
    } else if (res.status === 422 && res.errors) {
      newCustomerError.value = (Object.values(res.errors).flat()[0] as string) ?? 'Verifique os dados.'
    } else {
      newCustomerError.value = res.message ?? 'Não foi possível salvar o cliente.'
    }
  } finally {
    savingCustomer.value = false
  }
}

const submitted = ref(false)
const submitError = ref('')

// O erro de confirmação não pode ficar preso: limpa ao mudar de passo ou de dia/horário.
// (Antes só era limpo no próximo clique em "Confirmar", então persistia ao voltar e corrigir.)
watch([currentStep, selectedDay, () => form.time], () => {
  submitError.value = ''
})

// Abre a agenda já no DIA do agendamento criado (não em "hoje").
const goToAgenda = () => router.visit(form.date ? `/appointments?date=${form.date}` : '/appointments')
const resetWizard = () => {
  submitted.value = false
  currentStep.value = 1
  form.customer_id = null
  form.service_ids = []
  form.barberByService = {}
  form.date = ''
  form.time = ''
  form.notes = ''
  customerSearch.value = ''
  sameBarber.value = true
  selectedDay.value = localDateKey(new Date())
}
const submit = async () => {
  if (!form.customer_id || form.service_ids.length === 0 || !form.date || !form.time) return

  isLoading.value = true
  submitError.value = ''

  const barber_ids = form.service_ids.map((sid) => form.barberByService[sid] ?? null)
  const prices: Record<number, number> = {}
  for (const sid of form.service_ids) {
    prices[sid] = priceByService[sid] ?? (props.services.find((s) => s.id === sid)?.price ?? 0)
  }
  const starts_at = `${form.date}T${form.time}:00`

  try {
    const res = await fetch('/api/appointments', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
      },
      body: JSON.stringify({
        customer_id: form.customer_id,
        service_ids: form.service_ids,
        barber_ids,
        prices,
        starts_at,
        source: 'walk_in',
        notes: form.notes || null,
      }),
    })

    if (res.ok) {
      submitted.value = true
      return
    }

    if (res.status === 401) {
      window.location.href = '/login' // sessão perdida → login
      return
    }

    if (res.status === 422) {
      const body = await res.json().catch(() => ({}))
      submitError.value = (body && (body.message || (body.errors && Object.values(body.errors).flat()[0]))) || 'Não foi possível criar o agendamento. Revise os dados.'
      return
    }

    submitError.value = `Erro ${res.status} ao criar agendamento.`
  } catch (e) {
    submitError.value = 'Falha de rede. Tente novamente.'
  } finally {
    isLoading.value = false
  }
}

const successSummary = computed(() => {
  const cliente = selectedCustomer.value?.name ?? ''
  const servicos =
    selectedServices.value.length === 1 ? selectedServices.value[0].name : `${selectedServices.value.length} serviços`
  return `${cliente} · ${servicos} · ${dateLabel.value} · ${form.time}`
})

let nowTimer: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  selectedDay.value = localDateKey(new Date())
  nowTimer = setInterval(() => {
    now.value = new Date()
  }, 30000)
  if (props.prefill) {
    if (props.prefill.date) {
      form.date = props.prefill.date
      selectedDay.value = props.prefill.date
    }
    if (props.prefill.time) form.time = props.prefill.time

    // Veio de "Agendar" na ficha do cliente → já seleciona e pula pros serviços
    if (props.prefill.customer_id) {
      const c = localCustomers.value.find((x) => x.id === props.prefill!.customer_id)
      if (c) {
        form.customer_id = c.id
        currentStep.value = 2
      }
    }
  }
})
onUnmounted(() => {
  if (nowTimer) clearInterval(nowTimer)
})
</script>

<template>
  <div class="h-dvh overflow-hidden flex flex-col bg-[#0A0A0A] text-[#F5F5F5]">
    <!-- TOPO: header + progresso -->
    <div class="flex-shrink-0 bg-[#0A0A0A] border-b border-[#1F1F1F]">
      <div class="h-14 flex items-center px-2">
        <button
          @click="handleBack"
          class="w-10 h-10 flex items-center justify-center text-[#A1A1A1] hover:text-white rounded-full transition-colors"
          :aria-label="currentStep === 1 ? 'Fechar' : 'Voltar'"
        >
          <X v-if="currentStep === 1" :size="22" :stroke-width="1.75" />
          <ChevronLeft v-else :size="24" :stroke-width="1.75" />
        </button>
        <h1 class="flex-1 text-center text-[16px] font-semibold text-white pr-10 truncate">
          {{ STEP_TITLES[currentStep - 1] }}
        </h1>
      </div>
      <div class="px-4 pb-3">
        <p class="text-[11px] text-[#6B6B6B] mb-1.5">Passo {{ currentStep }} de 5</p>
        <div class="flex gap-1">
          <div
            v-for="n in 5"
            :key="n"
            class="flex-1 h-1 rounded-full transition-colors"
            :class="n <= currentStep ? 'bg-[#FFD60A]' : 'bg-[#1F1F1F]'"
          ></div>
        </div>
      </div>
    </div>

    <!-- CONTEÚDO -->
    <main class="flex-1 overflow-y-auto pb-20 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
      <!-- STEP 1 — CLIENTE -->
      <section v-if="currentStep === 1" :key="currentStep" class="px-4 py-4 animate-enter">
        <div class="relative mb-4">
          <input
            id="customerSearch"
            v-model="customerSearch"
            type="text"
            placeholder=" "
            autofocus
            class="peer block w-full h-14 px-4 pt-5 pb-1.5 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white outline-none transition-all duration-150 focus:border-[#FFD60A] focus:ring-2 focus:ring-[#FFD60A]/20 box-border"
          />
          <label
            for="customerSearch"
            class="absolute left-4 top-4 text-[14px] text-[#6B6B6B] transition-all duration-150 pointer-events-none peer-focus:top-2 peer-focus:text-[11px] peer-focus:text-[#FFD60A] peer-[:not(:placeholder-shown)]:top-2 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:text-[#A1A1A1]"
          >
            Buscar por nome ou telefone
          </label>
        </div>

        <div v-if="filteredCustomers.length > 0" class="space-y-2">
          <button
            v-for="c in filteredCustomers"
            :key="c.id"
            @click="selectCustomer(c)"
            class="w-full flex items-center gap-3 bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-3 text-left hover:border-[#3D3D3D] transition-colors active:scale-[0.99]"
          >
            <div class="w-10 h-10 rounded-full bg-[#1A1A1A] flex items-center justify-center text-[13px] font-semibold text-[#FFD60A] flex-shrink-0">
              {{ c.initials }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[14px] font-medium text-white truncate">{{ c.name }}</p>
              <p class="text-[12px] text-[#A1A1A1] tabular-nums">{{ formatPhone(c.phone) }}</p>
            </div>
            <span v-if="c.total_visits > 0" class="text-[11px] text-[#6B6B6B] tabular-nums flex-shrink-0">
              {{ c.total_visits }} {{ c.total_visits === 1 ? 'visita' : 'visitas' }}
            </span>
          </button>
        </div>
        <div v-else class="text-center py-12">
          <p class="text-[14px] font-medium text-white mb-1">Nenhum cliente encontrado</p>
          <p class="text-[13px] text-[#A1A1A1]">Tente outro nome ou cadastre um novo cliente.</p>
        </div>
      </section>

      <!-- STEP 2 — SERVIÇOS -->
      <section v-else-if="currentStep === 2" :key="currentStep" class="px-4 py-4 space-y-2 animate-enter">
        <div
          v-for="s in services"
          :key="s.id"
          @click="toggleService(s.id)"
          class="w-full flex items-center gap-3 rounded-[14px] p-4 border transition-colors duration-150 cursor-pointer active:scale-[0.99]"
          :class="form.service_ids.includes(s.id) ? 'border-[#FFD60A] bg-[#FFD60A]/[0.04]' : 'border-[#2A2A2A] bg-[#131313] hover:border-[#3D3D3D]'"
        >
          <div class="flex-1 min-w-0">
            <p class="text-[14px] font-medium text-white">{{ s.name }}</p>
            <p v-if="!form.service_ids.includes(s.id)" class="text-[13px] text-[#A1A1A1] tabular-nums mt-0.5">{{ formatBRL(s.price) }}</p>
          </div>

          <!-- preço editável só deste atendimento — clicar/editar aqui não desmarca -->
          <div v-if="form.service_ids.includes(s.id)" class="relative w-[104px] flex-shrink-0" @click.stop>
            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[12px] text-[#6B6B6B] pointer-events-none">R$</span>
            <input
              :value="priceByService[s.id]"
              @input="(e) => onServicePriceInput(e, s.id)"
              type="number"
              step="0.01"
              inputmode="decimal"
              class="w-full h-9 pl-7 pr-2 bg-[#0A0A0A] border border-[#FFD60A]/40 rounded-[8px] text-[13px] text-white text-right tabular-nums outline-none focus:border-[#FFD60A]"
            />
          </div>

          <div
            class="w-6 h-6 rounded-md flex items-center justify-center flex-shrink-0 border transition-colors"
            :class="form.service_ids.includes(s.id) ? 'bg-[#FFD60A] border-[#FFD60A]' : 'border-[#3D3D3D]'"
          >
            <Check v-if="form.service_ids.includes(s.id)" :size="15" :stroke-width="3" class="text-[#0A0A0A]" />
          </div>
        </div>
      </section>

      <!-- STEP 3 — BARBEIRO -->
      <section v-else-if="currentStep === 3" :key="currentStep" class="px-4 py-4 space-y-3 animate-enter">
        <div
          v-if="!isSingleService"
          class="flex items-center justify-between bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4"
        >
          <div class="min-w-0 pr-3">
            <p class="text-[14px] font-medium text-white">Mesmo barbeiro para todos</p>
            <p class="text-[12px] text-[#A1A1A1] mt-0.5">{{ selectedServices.length }} serviços selecionados</p>
          </div>
          <button
            @click="toggleSameBarber"
            role="switch"
            :aria-checked="sameBarber"
            aria-label="Mesmo barbeiro para todos"
            class="w-11 h-6 rounded-full transition-colors flex-shrink-0 relative"
            :class="sameBarber ? 'bg-[#FFD60A]' : 'bg-[#2A2A2A]'"
          >
            <span
              class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white transition-transform"
              :class="sameBarber ? 'translate-x-5' : ''"
            ></span>
          </button>
        </div>

        <!-- single OU mesmo barbeiro: escolha única (tap avança) -->
        <div v-if="isSingleService || sameBarber" class="space-y-2">
          <button
            v-for="b in barbersForAll"
            :key="b.id"
            @click="pickBarberForAll(b.id)"
            @dblclick="pickBarberForAllAndNext(b.id)"
            class="w-full flex items-center gap-3 rounded-[14px] p-4 border text-left transition-colors active:scale-[0.99] select-none touch-manipulation"
            :class="isBarberForAllSelected(b.id) ? 'border-[#FFD60A] bg-[#FFD60A]/[0.04]' : 'border-[#2A2A2A] bg-[#131313] hover:border-[#3D3D3D]'"
          >
            <div class="w-10 h-10 rounded-full bg-[#1A1A1A] flex items-center justify-center text-[13px] font-semibold text-[#FFD60A] flex-shrink-0">
              {{ b.initials }}
            </div>
            <p class="flex-1 text-[14px] font-medium text-white">{{ b.name }}</p>
            <div
              v-if="isBarberForAllSelected(b.id)"
              class="w-6 h-6 rounded-full bg-[#FFD60A] flex items-center justify-center flex-shrink-0"
            >
              <Check :size="15" :stroke-width="3" class="text-[#0A0A0A]" />
            </div>
          </button>
          <div v-if="barbersForAll.length === 0" class="text-center py-10">
            <p class="text-[14px] font-medium text-white mb-1">Nenhum barbeiro faz todos juntos</p>
            <p class="text-[13px] text-[#A1A1A1]">Desative "mesmo barbeiro" para escolher um por serviço.</p>
          </div>
        </div>

        <!-- múltiplos + barbeiro por serviço -->
        <div v-else class="space-y-2">
          <button
            v-for="s in selectedServices"
            :key="s.id"
            @click="barberSheetServiceId = s.id"
            class="w-full flex items-center gap-3 rounded-[14px] p-4 border text-left bg-[#131313] transition-colors hover:border-[#3D3D3D]"
            :class="form.barberByService[s.id] != null ? 'border-[#2A2A2A]' : 'border-[#2A2A2A]'"
          >
            <div class="flex-1 min-w-0">
              <p class="text-[14px] font-medium text-white truncate">{{ s.name }}</p>
              <p
                class="text-[12px] mt-0.5"
                :class="form.barberByService[s.id] != null ? 'text-[#FFD60A]' : 'text-[#6B6B6B]'"
              >
                {{ form.barberByService[s.id] != null ? barberName(form.barberByService[s.id]) : 'Escolher barbeiro' }}
              </p>
            </div>
            <ChevronRight :size="18" :stroke-width="1.75" class="text-[#6B6B6B] flex-shrink-0" />
          </button>
        </div>
      </section>

      <!-- STEP 4 — DATA E HORÁRIO -->
      <section v-else-if="currentStep === 4" :key="currentStep" class="py-4 animate-enter">
        <!-- Atalho AGORA (walk-in) -->
        <div class="px-4 mb-4">
          <button
            type="button"
            @click="useNow"
            class="w-full flex items-center gap-3 bg-[#FFD60A]/[0.06] border border-[#FFD60A]/20 rounded-[10px] px-4 py-3 text-left transition-colors hover:bg-[#FFD60A]/[0.1] active:scale-[0.99]"
          >
            <div class="w-9 h-9 rounded-full bg-[#FFD60A]/15 flex items-center justify-center flex-shrink-0">
              <Zap :size="18" class="text-[#FFD60A]" fill="#FFD60A" :stroke-width="1.5" />
            </div>
            <div class="min-w-0">
              <p class="text-[14px] font-semibold text-white">
                Atender agora · <span class="tabular-nums text-[#FFD60A]">{{ nowLabel }}</span>
              </p>
              <p class="text-[12px] text-[#A1A1A1] mt-0.5">Cria agendamento imediato com o horário atual</p>
            </div>
          </button>
        </div>

        <!-- Chips de dia (scroll-snap horizontal, scrollbar oculta) -->
        <div
          ref="dayScroller"
          class="flex gap-2 overflow-x-auto px-4 mb-4 snap-x snap-mandatory [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
          <button
            v-for="d in days"
            :key="d.date"
            @click="selectedDay = d.date"
            :data-selected="selectedDay === d.date"
            class="flex flex-col items-center justify-center w-14 h-[68px] rounded-[12px] border flex-shrink-0 snap-center transition-colors"
            :class="selectedDay === d.date ? 'bg-[#FFD60A] border-[#FFD60A]' : 'bg-[#131313] border-[#2A2A2A] hover:border-[#3D3D3D]'"
          >
            <span class="text-[10px] font-medium uppercase" :class="selectedDay === d.date ? 'text-[#0A0A0A]/70' : 'text-[#6B6B6B]'">
              {{ d.weekday }}
            </span>
            <span class="text-[18px] font-bold tabular-nums leading-tight" :class="selectedDay === d.date ? 'text-[#0A0A0A]' : 'text-white'">
              {{ d.dayNum }}
            </span>
            <span
              v-if="d.isToday"
              class="text-[9px] font-semibold leading-none"
              :class="selectedDay === d.date ? 'text-[#0A0A0A]/70' : 'text-[#FFD60A]'"
            >
              Hoje
            </span>
          </button>
        </div>

        <!-- Lista de horários do dia (real; ocupados continuam clicáveis p/ encaixe) -->
        <div class="px-4">
          <!-- carregando -->
          <div v-if="loadingSlots" class="space-y-2">
            <div v-for="i in 6" :key="i" class="w-full h-12 rounded-[10px] bg-[#131313] border border-[#1F1F1F] animate-pulse"></div>
          </div>

          <!-- barbeiro de folga ou fora do expediente neste dia -->
          <div v-else-if="!worksToday" class="text-center py-12">
            <p class="text-[14px] font-medium text-white mb-1">
              {{ dayOffReason === 'time_off' ? 'Barbeiro de folga neste dia' : 'Sem expediente neste dia' }}
            </p>
            <p class="text-[13px] text-[#A1A1A1]">Escolha outro dia ou use "Atender agora" pra encaixar.</p>
          </div>

          <!-- nenhum horário -->
          <div v-else-if="daySlots.length === 0" class="text-center py-12">
            <p class="text-[14px] font-medium text-white mb-1">Nenhum horário neste dia</p>
            <p class="text-[13px] text-[#A1A1A1]">Tente outro dia.</p>
          </div>

          <!-- grade real -->
          <div v-else class="space-y-2">
            <button
              v-for="slot in daySlots"
              :key="slot.time"
              type="button"
              @click="onSlotTap(slot)"
              class="w-full h-12 flex items-center justify-between rounded-[10px] border bg-[#131313] px-4 transition-colors active:scale-[0.99]"
              :class="slot.available ? 'border-[#2A2A2A] hover:border-[#FFD60A]' : 'border-[#1F1F1F] hover:border-[#3D3D3D]'"
            >
              <span class="text-[15px] font-semibold tabular-nums" :class="slot.available ? 'text-white' : 'text-[#6B6B6B]'">{{ slot.time }}</span>
              <span v-if="!slot.available" class="flex items-center gap-2 min-w-0">
                <span class="text-[11px] text-[#6B6B6B] truncate">{{ slot.occupant?.customer }}</span>
                <span class="text-[10px] text-[#6B6B6B] border border-[#2A2A2A] rounded px-1.5 py-0.5 flex-shrink-0">Ocupado</span>
              </span>
            </button>
          </div>
        </div>
      </section>

      <!-- STEP 5 — CONFIRMAÇÃO -->
      <section v-else-if="currentStep === 5" :key="currentStep" class="px-4 py-4 space-y-3 animate-enter">
        <div class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4">
          <p class="text-[11px] text-[#6B6B6B] uppercase tracking-[0.08em] mb-2.5">Cliente</p>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-[#1A1A1A] flex items-center justify-center text-[13px] font-semibold text-[#FFD60A] flex-shrink-0">
              {{ selectedCustomer?.initials }}
            </div>
            <div class="min-w-0">
              <p class="text-[14px] font-medium text-white truncate">{{ selectedCustomer?.name }}</p>
              <p class="text-[12px] text-[#A1A1A1] tabular-nums">{{ formatPhone(selectedCustomer?.phone ?? '') }}</p>
            </div>
          </div>
        </div>

        <div class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4">
          <p class="text-[11px] text-[#6B6B6B] uppercase tracking-[0.08em] mb-1">Serviços</p>
          <div
            v-for="(s, i) in selectedServices"
            :key="s.id"
            class="flex items-center justify-between py-2"
            :class="i > 0 ? 'border-t border-[#1F1F1F]' : ''"
          >
            <div class="min-w-0 pr-3">
              <p class="text-[14px] font-medium text-white truncate">{{ s.name }}</p>
              <p class="text-[12px] text-[#A1A1A1] mt-0.5">com {{ barberName(form.barberByService[s.id]) }}</p>
            </div>
            <span class="text-[14px] font-medium text-white tabular-nums flex-shrink-0">{{ formatBRL(priceOf(s)) }}</span>
          </div>
          <div class="flex items-center justify-between pt-3 mt-1 border-t border-[#2A2A2A]">
            <span class="text-[14px] font-semibold text-white">Total</span>
            <span class="text-[18px] font-bold text-[#FFD60A] tabular-nums">{{ formatBRL(totalPrice) }}</span>
          </div>
        </div>

        <div class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4">
          <p class="text-[11px] text-[#6B6B6B] uppercase tracking-[0.08em] mb-2">Data e horário</p>
          <p class="text-[15px] font-medium text-white">
            {{ dateLabel }} <span class="text-[#FFD60A] tabular-nums">· {{ form.time }}</span>
          </p>
        </div>

        <div class="relative">
          <textarea
            id="notes"
            v-model="form.notes"
            placeholder=" "
            rows="3"
            maxlength="100"
            class="peer block w-full min-h-[88px] px-4 pt-5 pb-2 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white outline-none resize-none transition-all duration-150 focus:border-[#FFD60A] focus:ring-2 focus:ring-[#FFD60A]/20 box-border"
          ></textarea>
          <label
            for="notes"
            class="absolute left-4 top-4 text-[14px] text-[#6B6B6B] transition-all duration-150 pointer-events-none peer-focus:top-2 peer-focus:text-[11px] peer-focus:text-[#FFD60A] peer-[:not(:placeholder-shown)]:top-2 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:text-[#A1A1A1]"
          >
            Observações (opcional)
          </label>
          <span class="absolute right-3 bottom-2 text-[11px] text-[#6B6B6B] tabular-nums pointer-events-none">{{ form.notes.length }}/100</span>
        </div>
      </section>
    </main>

    <!-- RODAPÉ FIXO -->
    <div class="flex-shrink-0 bg-[#0A0A0A] border-t border-[#1F1F1F] px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
      <!-- STEP 1 -->
      <button
        v-if="currentStep === 1"
        type="button"
        @click="newCustomerOpen = true"
        class="w-full h-12 rounded-[10px] font-medium text-[14px] text-[#A1A1A1] border border-[#2A2A2A] bg-transparent hover:text-white hover:border-[#3D3D3D] transition-colors active:scale-[0.98] flex items-center justify-center gap-2"
      >
        <Plus :size="18" :stroke-width="2" />
        Novo cliente
      </button>

      <!-- STEP 2 -->
      <template v-else-if="currentStep === 2">
        <p class="text-center text-[12px] mb-2 tabular-nums" :class="form.service_ids.length ? 'text-[#A1A1A1]' : 'text-[#6B6B6B]'">
          <template v-if="form.service_ids.length">
            <span class="text-white font-medium">{{ selectedServices.length }}</span>
            {{ selectedServices.length === 1 ? 'serviço' : 'serviços' }} ·
            <span class="text-white font-medium">{{ formatBRL(totalPrice) }}</span>
          </template>
          <template v-else>Selecione ao menos um serviço</template>
        </p>
        <button type="button" :disabled="form.service_ids.length === 0" @click="goToStep(3)" :class="primaryBtn">
          Próximo
        </button>
      </template>

      <!-- STEP 3 — Próximo aparece quando todos os serviços têm barbeiro (qualquer modo) -->
      <template v-else-if="currentStep === 3">
        <button
          v-if="step3Valid"
          type="button"
          @click="goToStep(4)"
          :class="primaryBtn"
        >
          Próximo
        </button>
        <p v-else class="text-center text-[12px] text-[#6B6B6B] py-1">Escolha o barbeiro para continuar</p>
      </template>

      <!-- STEP 4 -->
      <p v-else-if="currentStep === 4" class="text-center text-[12px] text-[#6B6B6B] py-1">
        Escolha um horário para continuar
      </p>

      <!-- STEP 5 -->
      <template v-else>
        <p v-if="submitError" class="text-center text-[12px] text-[#EF4444] -mt-1 mb-1 px-2">{{ submitError }}</p>
        <button type="button" :disabled="isLoading" @click="submit" :class="primaryBtn">
          <Loader2 v-if="isLoading" :size="18" class="animate-spin" />
          {{ isLoading ? 'Criando...' : 'Confirmar agendamento' }}
        </button>
      </template>
    </div>

    <!-- BOTTOM SHEET: NOVO CLIENTE -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-300"
        leave-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <div v-if="newCustomerOpen" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40" @click="newCustomerOpen = false"></div>
      </Transition>
      <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        leave-active-class="transition-transform duration-300 ease-out"
        enter-from-class="translate-y-full"
        leave-to-class="translate-y-full"
      >
        <div v-if="newCustomerOpen" class="fixed bottom-0 left-0 right-0 bg-[#131313] rounded-t-[20px] z-50 pb-[max(1rem,env(safe-area-inset-bottom))]">
          <div class="flex justify-center pt-2 pb-1">
            <div class="w-9 h-1 rounded-full bg-[#3D3D3D]"></div>
          </div>
          <div class="px-5 pt-3 pb-4 border-b border-[#1F1F1F]">
            <h3 class="text-[17px] font-semibold text-white">Novo cliente</h3>
          </div>
          <div class="px-5 py-4 space-y-3">
            <div class="relative">
              <input
                id="ncName"
                v-model="newCustomer.name"
                type="text"
                maxlength="100"
                placeholder=" "
                class="peer block w-full h-12 px-4 pt-4 pb-1 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white outline-none transition-all duration-150 focus:border-[#FFD60A] focus:ring-2 focus:ring-[#FFD60A]/20 box-border"
              />
              <label for="ncName" class="absolute left-4 top-3.5 text-[14px] text-[#6B6B6B] transition-all duration-150 pointer-events-none peer-focus:top-1.5 peer-focus:text-[11px] peer-focus:text-[#FFD60A] peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:text-[#A1A1A1]">
                Nome
              </label>
            </div>
            <div class="relative">
              <input
                id="ncPhone"
                :value="newCustomer.phone"
                @input="onNewCustomerPhone"
                type="tel"
                inputmode="numeric"
                placeholder=" "
                class="peer block w-full h-12 px-4 pt-4 pb-1 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white tabular-nums outline-none transition-all duration-150 focus:border-[#FFD60A] focus:ring-2 focus:ring-[#FFD60A]/20 box-border"
              />
              <label for="ncPhone" class="absolute left-4 top-3.5 text-[14px] text-[#6B6B6B] transition-all duration-150 pointer-events-none peer-focus:top-1.5 peer-focus:text-[11px] peer-focus:text-[#FFD60A] peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:text-[#A1A1A1]">
                Telefone (opcional)
              </label>
            </div>
            <div class="relative">
              <textarea
                id="ncNotes"
                v-model="newCustomer.notes"
                placeholder=" "
                rows="2"
                maxlength="2000"
                class="peer block w-full h-16 px-4 pt-4 pb-1 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white outline-none resize-none transition-all duration-150 focus:border-[#FFD60A] focus:ring-2 focus:ring-[#FFD60A]/20 box-border"
              ></textarea>
              <label for="ncNotes" class="absolute left-4 top-3.5 text-[14px] text-[#6B6B6B] transition-all duration-150 pointer-events-none peer-focus:top-1.5 peer-focus:text-[11px] peer-focus:text-[#FFD60A] peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:text-[#A1A1A1]">
                Observações (opcional)
              </label>
            </div>
            <div v-if="newCustomerError" class="flex items-center gap-1.5">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" class="flex-shrink-0 text-[#EF4444]">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.75" />
                <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
              </svg>
              <span class="text-[12px] text-[#EF4444]">{{ newCustomerError }}</span>
            </div>
          </div>
          <div class="px-5 pt-1 pb-2">
            <button type="button" :disabled="savingCustomer" @click="saveNewCustomer" :class="primaryBtn">
              <Loader2 v-if="savingCustomer" :size="18" class="animate-spin" />
              {{ savingCustomer ? 'Salvando...' : 'Salvar e selecionar' }}
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- BOTTOM SHEET: BARBEIRO POR SERVIÇO -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-300"
        leave-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <div v-if="barberSheetServiceId !== null" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40" @click="barberSheetServiceId = null"></div>
      </Transition>
      <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        leave-active-class="transition-transform duration-300 ease-out"
        enter-from-class="translate-y-full"
        leave-to-class="translate-y-full"
      >
        <div v-if="barberSheetServiceId !== null" class="fixed bottom-0 left-0 right-0 bg-[#131313] rounded-t-[20px] z-50 pb-[max(1rem,env(safe-area-inset-bottom))]">
          <div class="flex justify-center pt-2 pb-1">
            <div class="w-9 h-1 rounded-full bg-[#3D3D3D]"></div>
          </div>
          <div class="px-5 pt-3 pb-4 border-b border-[#1F1F1F]">
            <h3 class="text-[17px] font-semibold text-white">Escolher barbeiro</h3>
          </div>
          <div class="px-5 py-3 space-y-2 max-h-[60vh] overflow-y-auto">
            <button
              v-for="b in barbersForService(barberSheetServiceId)"
              :key="b.id"
              @click="pickBarberForService(b.id)"
              class="w-full flex items-center gap-3 rounded-[14px] p-3 border text-left transition-colors hover:border-[#3D3D3D]"
              :class="form.barberByService[barberSheetServiceId] === b.id ? 'border-[#FFD60A] bg-[#FFD60A]/[0.04]' : 'border-[#2A2A2A] bg-[#1A1A1A]'"
            >
              <div class="w-9 h-9 rounded-full bg-[#131313] flex items-center justify-center text-[12px] font-semibold text-[#FFD60A] flex-shrink-0">
                {{ b.initials }}
              </div>
              <p class="flex-1 text-[14px] font-medium text-white">{{ b.name }}</p>
              <Check v-if="form.barberByService[barberSheetServiceId] === b.id" :size="16" :stroke-width="3" class="text-[#FFD60A]" />
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- BOTTOM SHEET: DESCARTAR -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-300"
        leave-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <div v-if="discardOpen" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40" @click="discardOpen = false"></div>
      </Transition>
      <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        leave-active-class="transition-transform duration-300 ease-out"
        enter-from-class="translate-y-full"
        leave-to-class="translate-y-full"
      >
        <div v-if="discardOpen" class="fixed bottom-0 left-0 right-0 bg-[#131313] rounded-t-[20px] z-50 pb-[max(1rem,env(safe-area-inset-bottom))]">
          <div class="flex justify-center pt-2 pb-1">
            <div class="w-9 h-1 rounded-full bg-[#3D3D3D]"></div>
          </div>
          <div class="px-5 pt-4 pb-2">
            <h3 class="text-[17px] font-semibold text-white">Descartar agendamento?</h3>
            <p class="text-[13px] text-[#A1A1A1] mt-1">Os dados preenchidos serão perdidos.</p>
          </div>
          <div class="px-5 pt-3 pb-2 space-y-2">
            <button
              type="button"
              @click="confirmDiscard"
              class="w-full h-12 rounded-[10px] font-medium text-[14px] text-[#EF4444] border border-[#EF4444]/20 bg-[#EF4444]/5 hover:bg-[#EF4444]/10 transition-colors active:scale-[0.98]"
            >
              Descartar
            </button>
            <button
              type="button"
              @click="discardOpen = false"
              class="w-full h-12 rounded-[10px] font-medium text-[14px] text-[#A1A1A1] border border-[#2A2A2A] bg-transparent hover:text-white hover:border-[#3D3D3D] transition-colors active:scale-[0.98]"
            >
              Continuar editando
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- BOTTOM SHEET: ENCAIXE EM SLOT OCUPADO -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-300"
        leave-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <div v-if="conflictSlot" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40" @click="conflictSlot = null"></div>
      </Transition>
      <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        leave-active-class="transition-transform duration-300 ease-out"
        enter-from-class="translate-y-full"
        leave-to-class="translate-y-full"
      >
        <div v-if="conflictSlot" class="fixed bottom-0 left-0 right-0 bg-[#131313] rounded-t-[20px] z-50 pb-[max(1rem,env(safe-area-inset-bottom))]">
          <div class="flex justify-center pt-2 pb-1">
            <div class="w-9 h-1 rounded-full bg-[#3D3D3D]"></div>
          </div>
          <div class="px-5 pt-4 pb-2">
            <h3 class="text-[17px] font-semibold text-white">Este horário já tem agendamento</h3>
            <p class="text-[13px] text-[#A1A1A1] mt-1">Este horário já está ocupado. Você pode encaixar mesmo assim se preferir.</p>
          </div>
          <div class="px-5 py-2">
            <div class="bg-[#1A1A1A] border border-[#2A2A2A] rounded-[12px] p-3">
              <div class="flex items-center justify-between mb-1.5">
                <span class="text-[15px] font-semibold text-white tabular-nums">{{ conflictSlot.time }}</span>
                <span class="text-[10px] text-[#6B6B6B] border border-[#2A2A2A] rounded px-1.5 py-0.5">Ocupado</span>
              </div>
              <p class="text-[14px] text-white">{{ conflictSlot.customer }}</p>
            </div>
          </div>
          <div class="px-5 pt-3 pb-2 space-y-2">
            <button type="button" @click="confirmOverbook" :class="primaryBtn">Encaixar mesmo assim</button>
            <button
              type="button"
              @click="conflictSlot = null"
              class="w-full h-12 rounded-[10px] font-medium text-[14px] text-[#A1A1A1] border border-[#2A2A2A] bg-transparent hover:text-white hover:border-[#3D3D3D] transition-colors active:scale-[0.98]"
            >
              Cancelar
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- AVISO DE SUCESSO: cobre a tela inteira -->
    <Transition
      enter-active-class="transition-opacity duration-150"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
    >
      <div
        v-if="submitted"
        class="fixed inset-0 z-[60] bg-[#0A0A0A] flex flex-col items-center justify-center px-8 text-center"
      >
        <div class="w-20 h-20 rounded-full bg-[#22C55E]/15 flex items-center justify-center mb-6">
          <Check :size="44" :stroke-width="2.5" class="text-[#22C55E]" />
        </div>
        <h2 class="text-[22px] font-bold text-white">Agendamento confirmado!</h2>
        <p class="text-[14px] text-[#A1A1A1] mt-2 leading-relaxed max-w-[300px]">{{ successSummary }}</p>
        <div class="w-full max-w-[280px] mt-8 flex flex-col gap-3">
          <button type="button" @click="goToAgenda" :class="primaryBtn">Ver na agenda</button>
          <button
            type="button"
            @click="resetWizard"
            class="w-full h-11 text-[14px] font-medium text-[#A1A1A1] hover:text-white transition-colors active:scale-[0.98]"
          >
            Novo agendamento
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>
