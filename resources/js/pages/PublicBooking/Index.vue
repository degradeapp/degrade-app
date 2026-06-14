<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { ChevronLeft, Check, Loader2, Scissors, Store } from 'lucide-vue-next'
import { useFormatting } from '@/composables/useFormatting'

interface CatalogUnit { id: number; name: string; address: string | null }
interface CatalogService { id: number; name: string; price: number }
interface CatalogBarber { id: number; unit_id: number | null; name: string; photo_url: string | null }
interface Catalog {
  name: string
  logo_url: string | null
  timezone: string
  multi_unit: boolean
  units: CatalogUnit[]
  services: CatalogService[]
  barbers: CatalogBarber[]
}
interface Confirmation {
  id: number
  starts_at: string | null
  barber_name: string
  services: string[]
  total_price: number
  unit_name: string
}

const props = defineProps<{ slug: string }>()

const { formatBRL, formatPhone, parsePhone, formatDateLong } = useFormatting()

const primaryBtn =
  'w-full h-12 rounded-[10px] font-bold text-[15px] text-[#0A0A0A] flex items-center justify-center gap-2 transition-all duration-150 active:scale-[0.98] disabled:cursor-not-allowed bg-[#FFD60A] enabled:hover:bg-[#FFE066] enabled:active:bg-[#F5C400] enabled:shadow-[0_8px_24px_-8px_rgba(255,214,10,0.5),inset_0_1px_0_rgba(255,255,255,0.25)] disabled:opacity-70'

const xsrf = () =>
  decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

// ---- estado de carga do catálogo ----
const loading = ref(true)
const notFound = ref(false)
const loadError = ref(false)
const catalog = ref<Catalog | null>(null)

// ---- form ----
const form = reactive({
  unit_id: null as number | null,
  service_ids: [] as number[],
  barber_id: null as number | null, // null = "qualquer um"
  date: '',
  time: '',
  name: '',
  phone: '',
})
const barberChosen = ref(false) // "qualquer" também é uma escolha válida (barber_id null)

// ---- passos (unidade só aparece em rede) ----
const steps = computed<string[]>(() => {
  const s: string[] = []
  if (catalog.value?.multi_unit) s.push('unit')
  s.push('services', 'barber', 'datetime', 'contact')
  return s
})
const stepIndex = ref(0)
const stepKey = computed(() => steps.value[stepIndex.value] ?? 'services')
const STEP_LABEL: Record<string, string> = {
  unit: 'Escolha a unidade',
  services: 'O que você quer fazer?',
  barber: 'Com quem?',
  datetime: 'Quando?',
  contact: 'Seus dados',
}

const tz = computed(() => catalog.value?.timezone || 'America/Manaus')

// ---- derivados ----
const barbersForUnit = computed(() => {
  if (!catalog.value) return []
  if (!catalog.value.multi_unit) return catalog.value.barbers
  return catalog.value.barbers.filter((b) => b.unit_id === form.unit_id)
})
const selectedServices = computed(() =>
  (catalog.value?.services ?? []).filter((s) => form.service_ids.includes(s.id))
)
const totalPrice = computed(() => selectedServices.value.reduce((sum, s) => sum + s.price, 0))
const selectedBarberName = computed(() => {
  if (form.barber_id === null) return 'Qualquer profissional'
  return barbersForUnit.value.find((b) => b.id === form.barber_id)?.name ?? ''
})
const selectedUnitName = computed(
  () => catalog.value?.units.find((u) => u.id === form.unit_id)?.name ?? ''
)

const initials = (name: string) =>
  name.trim().split(/\s+/).map((p) => p[0]).slice(0, 2).join('').toUpperCase()

// ---- dias (na timezone da loja, não do navegador) ----
const pad = (n: number) => String(n).padStart(2, '0')
const tzTodayParts = () => {
  const s = new Intl.DateTimeFormat('en-CA', {
    timeZone: tz.value,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date())
  const [y, m, d] = s.split('-').map(Number)
  return { y, m, d }
}
const days = computed(() => {
  const { y, m, d } = tzTodayParts()
  const labels = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']
  return Array.from({ length: 21 }, (_, i) => {
    const dt = new Date(y, m - 1, d + i)
    return {
      date: `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}`,
      weekday: labels[dt.getDay()],
      dayNum: dt.getDate(),
      isToday: i === 0,
    }
  })
})
const dateLabel = computed(() => {
  if (!form.date) return ''
  const [y, m, d] = form.date.split('-').map(Number)
  return formatDateLong(new Date(y, m - 1, d))
})

// ---- horários (vêm da API, já no fuso da loja) ----
const slots = ref<string[]>([])
const loadingSlots = ref(false)

const loadSlots = async () => {
  if (!form.date || !form.unit_id) {
    slots.value = []
    return
  }
  loadingSlots.value = true
  slots.value = []
  try {
    const params = new URLSearchParams({ date: form.date, unit_id: String(form.unit_id) })
    if (form.barber_id !== null) params.set('barber_id', String(form.barber_id))
    const res = await fetch(
      `/api/public/agendar/${encodeURIComponent(props.slug)}/horarios?${params.toString()}`,
      { headers: { Accept: 'application/json' } }
    )
    if (res.ok) {
      const json = await res.json()
      slots.value = (json.data?.slots ?? []) as string[]
    }
  } catch {
    slots.value = []
  } finally {
    loadingSlots.value = false
  }
}

watch(stepKey, (k) => {
  if (k === 'datetime') {
    if (!form.date && days.value.length) form.date = days.value[0].date
    loadSlots()
  }
})
watch(() => form.date, () => {
  if (stepKey.value === 'datetime') loadSlots()
})

// ---- navegação ----
const canAdvance = computed(() => {
  switch (stepKey.value) {
    case 'unit': return form.unit_id !== null
    case 'services': return form.service_ids.length >= 1
    case 'barber': return barberChosen.value
    case 'datetime': return !!form.date && !!form.time
    default: return false
  }
})
const back = () => {
  submitError.value = ''
  if (stepIndex.value > 0) stepIndex.value--
}
const next = () => {
  if (stepIndex.value < steps.value.length - 1) stepIndex.value++
}

const pickUnit = (id: number) => {
  if (form.unit_id !== id) {
    form.unit_id = id
    // trocar de unidade invalida barbeiro/horário escolhidos
    form.barber_id = null
    barberChosen.value = false
    form.time = ''
  }
  next()
}
const toggleService = (id: number) => {
  const i = form.service_ids.indexOf(id)
  if (i >= 0) form.service_ids.splice(i, 1)
  else if (form.service_ids.length < 5) form.service_ids.push(id)
}
const pickBarber = (id: number | null) => {
  form.barber_id = id
  barberChosen.value = true
  form.time = ''
  next()
}
const pickSlot = (time: string) => {
  form.time = time
  next()
}

// ---- envio ----
const submitting = ref(false)
const submitError = ref('')
const confirmation = ref<Confirmation | null>(null)

const submit = async () => {
  submitError.value = ''
  if (!form.name.trim()) {
    submitError.value = 'Informe seu nome.'
    return
  }
  if (parsePhone(form.phone).length !== 11) {
    submitError.value = 'Informe um celular com DDD (11 dígitos).'
    return
  }

  submitting.value = true
  try {
    const res = await fetch(`/api/public/agendar/${encodeURIComponent(props.slug)}`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrf(),
      },
      body: JSON.stringify({
        name: form.name.trim(),
        phone: parsePhone(form.phone),
        service_ids: form.service_ids,
        barber_id: form.barber_id,
        unit_id: form.unit_id,
        starts_at: `${form.date}T${form.time}:00`,
      }),
    })

    if (res.status === 201) {
      const json = await res.json()
      confirmation.value = json.data as Confirmation
      return
    }
    if (res.status === 404) {
      notFound.value = true
      return
    }
    if (res.status === 429) {
      submitError.value = 'Muitas tentativas em pouco tempo. Aguarde um instante e tente de novo.'
      return
    }
    if (res.status === 422) {
      const body = await res.json().catch(() => ({}))
      const fieldError =
        body?.errors && (Object.values(body.errors).flat()[0] as string | undefined)
      if (fieldError) {
        submitError.value = fieldError
        return
      }
      // Sem erro de campo = provável horário tomado/indisponível: volta pro passo
      // de horário e recarrega a lista (o servidor é a autoridade).
      submitError.value = body?.message || 'Este horário não está mais disponível. Escolha outro.'
      form.time = ''
      const dtIndex = steps.value.indexOf('datetime')
      if (dtIndex >= 0) stepIndex.value = dtIndex
      loadSlots()
      return
    }
    submitError.value = `Não foi possível agendar (erro ${res.status}). Tente novamente.`
  } catch {
    submitError.value = 'Falha de rede. Verifique sua conexão e tente novamente.'
  } finally {
    submitting.value = false
  }
}

const confirmationDate = computed(() => {
  if (!confirmation.value?.starts_at) return ''
  const d = new Date(confirmation.value.starts_at)
  const date = formatDateLong(d)
  const hh = new Intl.DateTimeFormat('en-GB', {
    timeZone: tz.value,
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).format(d)
  return `${date} · ${hh}`
})

const loadCatalog = async () => {
  loading.value = true
  try {
    const res = await fetch(`/api/public/agendar/${encodeURIComponent(props.slug)}`, {
      headers: { Accept: 'application/json' },
    })
    if (res.status === 404) {
      notFound.value = true
      return
    }
    if (!res.ok) {
      loadError.value = true
      return
    }
    const json = await res.json()
    catalog.value = json.data as Catalog
    if (!catalog.value.multi_unit && catalog.value.units.length > 0) {
      form.unit_id = catalog.value.units[0].id
    }
  } catch {
    loadError.value = true
  } finally {
    loading.value = false
  }
}

onMounted(loadCatalog)
</script>

<template>
  <Head :title="catalog ? `Agendar · ${catalog.name}` : 'Agendar'" />

  <div class="h-dvh overflow-hidden flex flex-col bg-[#0A0A0A] text-[#F5F5F5]">
    <!-- CARREGANDO -->
    <div v-if="loading" class="flex-1 flex items-center justify-center">
      <Loader2 :size="28" class="animate-spin text-[#FFD60A]" />
    </div>

    <!-- LOJA NÃO ENCONTRADA / FORA DO AR -->
    <div v-else-if="notFound" class="flex-1 flex flex-col items-center justify-center px-8 text-center">
      <div class="w-16 h-16 rounded-full bg-[#1A1A1A] flex items-center justify-center mb-5">
        <Store :size="30" class="text-[#6B6B6B]" :stroke-width="1.75" />
      </div>
      <h1 class="text-[18px] font-semibold text-white">Barbearia não encontrada</h1>
      <p class="text-[14px] text-[#A1A1A1] mt-2 max-w-[300px] leading-relaxed">
        Este link de agendamento não está disponível. Confira o endereço com a barbearia.
      </p>
    </div>

    <!-- ERRO DE REDE -->
    <div v-else-if="loadError" class="flex-1 flex flex-col items-center justify-center px-8 text-center">
      <h1 class="text-[18px] font-semibold text-white">Não foi possível carregar</h1>
      <p class="text-[14px] text-[#A1A1A1] mt-2 mb-6">Verifique sua conexão e tente de novo.</p>
      <button type="button" @click="loadCatalog" class="h-11 px-6 rounded-[10px] border border-[#2A2A2A] text-[14px] font-medium text-white hover:border-[#3D3D3D] transition-colors">
        Tentar novamente
      </button>
    </div>

    <!-- WIZARD -->
    <template v-else-if="catalog">
      <!-- TOPO: marca + progresso -->
      <div class="flex-shrink-0 bg-[#0A0A0A] border-b border-[#1F1F1F]">
        <div class="h-14 flex items-center px-2">
          <button
            v-if="stepIndex > 0"
            @click="back"
            class="w-10 h-10 flex items-center justify-center text-[#A1A1A1] hover:text-white rounded-full transition-colors"
            aria-label="Voltar"
          >
            <ChevronLeft :size="24" :stroke-width="1.75" />
          </button>
          <div v-else class="w-10" />
          <div class="flex-1 flex items-center justify-center gap-2 min-w-0 pr-10">
            <img v-if="catalog.logo_url" :src="catalog.logo_url" :alt="catalog.name" class="w-6 h-6 rounded-md object-cover flex-shrink-0" />
            <Scissors v-else :size="16" class="text-[#FFD60A] flex-shrink-0" />
            <h1 class="text-[15px] font-semibold text-white truncate">{{ catalog.name }}</h1>
          </div>
        </div>
        <div class="px-4 pb-3">
          <p class="text-[11px] text-[#6B6B6B] mb-1.5">{{ STEP_LABEL[stepKey] }}</p>
          <div class="flex gap-1">
            <div
              v-for="(s, i) in steps"
              :key="s"
              class="flex-1 h-1 rounded-full transition-colors"
              :class="i <= stepIndex ? 'bg-[#FFD60A]' : 'bg-[#1F1F1F]'"
            ></div>
          </div>
        </div>
      </div>

      <!-- CONTEÚDO -->
      <main class="flex-1 overflow-y-auto pb-24 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <!-- UNIDADE -->
        <section v-if="stepKey === 'unit'" class="px-4 py-4 space-y-2 animate-enter">
          <button
            v-for="u in catalog.units"
            :key="u.id"
            @click="pickUnit(u.id)"
            class="w-full flex items-center gap-3 rounded-[14px] p-4 border text-left transition-colors active:scale-[0.99]"
            :class="form.unit_id === u.id ? 'border-[#FFD60A] bg-[#FFD60A]/[0.04]' : 'border-[#2A2A2A] bg-[#131313] hover:border-[#3D3D3D]'"
          >
            <div class="w-10 h-10 rounded-full bg-[#1A1A1A] flex items-center justify-center flex-shrink-0">
              <Store :size="18" class="text-[#FFD60A]" :stroke-width="1.75" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[14px] font-medium text-white truncate">{{ u.name }}</p>
              <p v-if="u.address" class="text-[12px] text-[#A1A1A1] truncate mt-0.5">{{ u.address }}</p>
            </div>
          </button>
        </section>

        <!-- SERVIÇOS -->
        <section v-else-if="stepKey === 'services'" class="px-4 py-4 space-y-2 animate-enter">
          <div v-if="catalog.services.length === 0" class="text-center py-12">
            <p class="text-[14px] font-medium text-white mb-1">Nenhum serviço disponível</p>
            <p class="text-[13px] text-[#A1A1A1]">Entre em contato com a barbearia.</p>
          </div>
          <div
            v-for="s in catalog.services"
            :key="s.id"
            @click="toggleService(s.id)"
            class="w-full flex items-center gap-3 rounded-[14px] p-4 border transition-colors duration-150 cursor-pointer active:scale-[0.99]"
            :class="form.service_ids.includes(s.id) ? 'border-[#FFD60A] bg-[#FFD60A]/[0.04]' : 'border-[#2A2A2A] bg-[#131313] hover:border-[#3D3D3D]'"
          >
            <div class="flex-1 min-w-0">
              <p class="text-[14px] font-medium text-white">{{ s.name }}</p>
              <p class="text-[13px] text-[#A1A1A1] tabular-nums mt-0.5">{{ formatBRL(s.price) }}</p>
            </div>
            <div
              class="w-6 h-6 rounded-md flex items-center justify-center flex-shrink-0 border transition-colors"
              :class="form.service_ids.includes(s.id) ? 'bg-[#FFD60A] border-[#FFD60A]' : 'border-[#3D3D3D]'"
            >
              <Check v-if="form.service_ids.includes(s.id)" :size="15" :stroke-width="3" class="text-[#0A0A0A]" />
            </div>
          </div>
        </section>

        <!-- BARBEIRO -->
        <section v-else-if="stepKey === 'barber'" class="px-4 py-4 space-y-2 animate-enter">
          <button
            @click="pickBarber(null)"
            class="w-full flex items-center gap-3 rounded-[14px] p-4 border text-left transition-colors active:scale-[0.99]"
            :class="barberChosen && form.barber_id === null ? 'border-[#FFD60A] bg-[#FFD60A]/[0.04]' : 'border-[#2A2A2A] bg-[#131313] hover:border-[#3D3D3D]'"
          >
            <div class="w-10 h-10 rounded-full bg-[#1A1A1A] flex items-center justify-center text-[13px] font-semibold text-[#FFD60A] flex-shrink-0">
              <Scissors :size="18" :stroke-width="1.75" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[14px] font-medium text-white">Qualquer profissional</p>
              <p class="text-[12px] text-[#A1A1A1] mt-0.5">A barbearia escolhe quem estiver livre</p>
            </div>
          </button>

          <button
            v-for="b in barbersForUnit"
            :key="b.id"
            @click="pickBarber(b.id)"
            class="w-full flex items-center gap-3 rounded-[14px] p-4 border text-left transition-colors active:scale-[0.99]"
            :class="form.barber_id === b.id ? 'border-[#FFD60A] bg-[#FFD60A]/[0.04]' : 'border-[#2A2A2A] bg-[#131313] hover:border-[#3D3D3D]'"
          >
            <img
              v-if="b.photo_url"
              :src="b.photo_url"
              :alt="b.name"
              class="w-10 h-10 rounded-full object-cover flex-shrink-0"
            />
            <div v-else class="w-10 h-10 rounded-full bg-[#1A1A1A] flex items-center justify-center text-[13px] font-semibold text-[#FFD60A] flex-shrink-0">
              {{ initials(b.name) }}
            </div>
            <p class="flex-1 text-[14px] font-medium text-white">{{ b.name }}</p>
          </button>

          <div v-if="barbersForUnit.length === 0" class="text-center py-10">
            <p class="text-[14px] font-medium text-white mb-1">Nenhum profissional nesta unidade</p>
            <p class="text-[13px] text-[#A1A1A1]">Use "Qualquer profissional" acima.</p>
          </div>
        </section>

        <!-- DATA E HORÁRIO -->
        <section v-else-if="stepKey === 'datetime'" class="py-4 animate-enter">
          <div class="flex gap-2 overflow-x-auto px-4 mb-4 snap-x snap-mandatory [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <button
              v-for="d in days"
              :key="d.date"
              @click="form.date = d.date"
              class="flex flex-col items-center justify-center w-14 h-[68px] rounded-[12px] border flex-shrink-0 snap-center transition-colors"
              :class="form.date === d.date ? 'bg-[#FFD60A] border-[#FFD60A]' : 'bg-[#131313] border-[#2A2A2A] hover:border-[#3D3D3D]'"
            >
              <span class="text-[10px] font-medium uppercase" :class="form.date === d.date ? 'text-[#0A0A0A]/70' : 'text-[#6B6B6B]'">{{ d.weekday }}</span>
              <span class="text-[18px] font-bold tabular-nums leading-tight" :class="form.date === d.date ? 'text-[#0A0A0A]' : 'text-white'">{{ d.dayNum }}</span>
              <span v-if="d.isToday" class="text-[9px] font-semibold leading-none" :class="form.date === d.date ? 'text-[#0A0A0A]/70' : 'text-[#FFD60A]'">Hoje</span>
            </button>
          </div>

          <div class="px-4">
            <div v-if="loadingSlots" class="grid grid-cols-3 gap-2">
              <div v-for="i in 9" :key="i" class="h-11 rounded-[10px] bg-[#131313] border border-[#1F1F1F] animate-pulse"></div>
            </div>
            <div v-else-if="slots.length === 0" class="text-center py-12">
              <p class="text-[14px] font-medium text-white mb-1">Nenhum horário neste dia</p>
              <p class="text-[13px] text-[#A1A1A1]">Escolha outro dia.</p>
            </div>
            <div v-else class="grid grid-cols-3 gap-2">
              <button
                v-for="t in slots"
                :key="t"
                type="button"
                @click="pickSlot(t)"
                class="h-11 rounded-[10px] border bg-[#131313] text-[15px] font-semibold tabular-nums text-white transition-colors active:scale-[0.97]"
                :class="form.time === t ? 'border-[#FFD60A] bg-[#FFD60A]/[0.06]' : 'border-[#2A2A2A] hover:border-[#FFD60A]'"
              >
                {{ t }}
              </button>
            </div>
          </div>
        </section>

        <!-- CONTATO + RESUMO -->
        <section v-else-if="stepKey === 'contact'" class="px-4 py-4 space-y-3 animate-enter">
          <div class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4 space-y-2">
            <div v-if="catalog.multi_unit" class="flex items-center justify-between">
              <span class="text-[12px] text-[#6B6B6B]">Unidade</span>
              <span class="text-[13px] font-medium text-white">{{ selectedUnitName }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[12px] text-[#6B6B6B]">Profissional</span>
              <span class="text-[13px] font-medium text-white">{{ selectedBarberName }}</span>
            </div>
            <div class="flex items-start justify-between">
              <span class="text-[12px] text-[#6B6B6B]">Serviços</span>
              <span class="text-[13px] font-medium text-white text-right">{{ selectedServices.map((s) => s.name).join(', ') }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-[12px] text-[#6B6B6B]">Quando</span>
              <span class="text-[13px] font-medium text-white">{{ dateLabel }} · <span class="text-[#FFD60A] tabular-nums">{{ form.time }}</span></span>
            </div>
            <div class="flex items-center justify-between pt-2 mt-1 border-t border-[#2A2A2A]">
              <span class="text-[13px] font-semibold text-white">Total</span>
              <span class="text-[16px] font-bold text-[#FFD60A] tabular-nums">{{ formatBRL(totalPrice) }}</span>
            </div>
          </div>

          <div class="relative">
            <input
              id="pbName"
              v-model="form.name"
              type="text"
              maxlength="150"
              placeholder=" "
              class="peer block w-full h-12 px-4 pt-4 pb-1 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white outline-none transition-all duration-150 focus:border-[#FFD60A] focus:ring-2 focus:ring-[#FFD60A]/20 box-border"
            />
            <label for="pbName" class="absolute left-4 top-3.5 text-[14px] text-[#6B6B6B] transition-all duration-150 pointer-events-none peer-focus:top-1.5 peer-focus:text-[11px] peer-focus:text-[#FFD60A] peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:text-[#A1A1A1]">
              Seu nome
            </label>
          </div>

          <div class="relative">
            <input
              id="pbPhone"
              :value="form.phone"
              @input="(e) => (form.phone = formatPhone(parsePhone((e.target as HTMLInputElement).value).slice(0, 11)))"
              type="tel"
              inputmode="numeric"
              placeholder=" "
              class="peer block w-full h-12 px-4 pt-4 pb-1 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[15px] text-white tabular-nums outline-none transition-all duration-150 focus:border-[#FFD60A] focus:ring-2 focus:ring-[#FFD60A]/20 box-border"
            />
            <label for="pbPhone" class="absolute left-4 top-3.5 text-[14px] text-[#6B6B6B] transition-all duration-150 pointer-events-none peer-focus:top-1.5 peer-focus:text-[11px] peer-focus:text-[#FFD60A] peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-[11px] peer-[:not(:placeholder-shown)]:text-[#A1A1A1]">
              Celular com DDD
            </label>
          </div>
        </section>
      </main>

      <!-- RODAPÉ FIXO -->
      <div class="flex-shrink-0 bg-[#0A0A0A] border-t border-[#1F1F1F] px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
        <template v-if="stepKey === 'services'">
          <p class="text-center text-[12px] mb-2 tabular-nums" :class="form.service_ids.length ? 'text-[#A1A1A1]' : 'text-[#6B6B6B]'">
            <template v-if="form.service_ids.length">
              <span class="text-white font-medium">{{ selectedServices.length }}</span>
              {{ selectedServices.length === 1 ? 'serviço' : 'serviços' }} ·
              <span class="text-white font-medium">{{ formatBRL(totalPrice) }}</span>
            </template>
            <template v-else>Selecione ao menos um serviço</template>
          </p>
          <button type="button" :disabled="!canAdvance" @click="next" :class="primaryBtn">Próximo</button>
        </template>

        <template v-else-if="stepKey === 'contact'">
          <p v-if="submitError" class="text-center text-[12px] text-[#EF4444] -mt-1 mb-1 px-2">{{ submitError }}</p>
          <button type="button" :disabled="submitting" @click="submit" :class="primaryBtn">
            <Loader2 v-if="submitting" :size="18" class="animate-spin" />
            {{ submitting ? 'Agendando...' : 'Confirmar agendamento' }}
          </button>
        </template>

        <p v-else-if="stepKey === 'datetime'" class="text-center text-[12px] text-[#6B6B6B] py-1">
          Escolha um horário para continuar
        </p>
        <p v-else class="text-center text-[12px] text-[#6B6B6B] py-1">
          Toque para escolher
        </p>
      </div>
    </template>

    <!-- SUCESSO -->
    <Transition enter-active-class="transition-opacity duration-150" enter-from-class="opacity-0" enter-to-class="opacity-100">
      <div v-if="confirmation" class="fixed inset-0 z-[60] bg-[#0A0A0A] flex flex-col items-center justify-center px-8 text-center">
        <div class="w-20 h-20 rounded-full bg-[#22C55E]/15 flex items-center justify-center mb-6">
          <Check :size="44" :stroke-width="2.5" class="text-[#22C55E]" />
        </div>
        <h2 class="text-[22px] font-bold text-white">Agendamento confirmado!</h2>
        <p class="text-[14px] text-[#A1A1A1] mt-2 leading-relaxed max-w-[320px]">
          {{ confirmation.services.join(', ') }} com {{ confirmation.barber_name }}
        </p>
        <p class="text-[15px] font-medium text-white mt-1">{{ confirmationDate }}</p>
        <p v-if="catalog?.multi_unit" class="text-[13px] text-[#A1A1A1] mt-1">{{ confirmation.unit_name }}</p>
        <p class="text-[13px] text-[#6B6B6B] mt-6 max-w-[300px] leading-relaxed">
          Guarde a data. Em caso de imprevisto, fale com a barbearia.
        </p>
      </div>
    </Transition>
  </div>
</template>
