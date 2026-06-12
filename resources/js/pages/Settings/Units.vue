<template>
  <AppLayout title="Unidades" show-back-button>
    <div v-if="loading" class="p-4 space-y-3">
      <Skeleton v-for="i in 2" :key="i" height="72px" />
    </div>

    <div v-else class="p-4 pb-32 space-y-3 animate-enter">
      <p class="text-[13px] text-[#A1A1A1]">Locais da sua rede. Cada unidade tem a própria agenda e equipe; clientes e serviços são compartilhados.</p>

      <button
        type="button"
        @click="openCreate"
        class="w-full h-12 rounded-[10px] bg-[#FFD60A] text-[14px] font-bold text-[#0A0A0A] flex items-center justify-center gap-2 hover:bg-[#FFE066] active:scale-[0.98] transition-all"
      >
        <Plus :size="18" :stroke-width="2" />
        Adicionar unidade
      </button>

      <div v-if="units.length === 0" class="text-[13px] text-[#6B6B6B] text-center py-10">
        Nenhuma unidade cadastrada.
      </div>

      <div v-else class="space-y-2 stagger">
        <div
          v-for="u in units"
          :key="u.id"
          class="bg-[#131313] border border-[#2A2A2A] rounded-[12px] p-3 flex items-center gap-3"
        >
          <div class="w-10 h-10 rounded-[10px] bg-[#1A1A1A] border border-[#2A2A2A] flex items-center justify-center text-[#FFD60A] flex-shrink-0">
            <Store :size="18" :stroke-width="1.75" />
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-[14px] font-medium text-white truncate">
              {{ u.name }}
              <span v-if="!u.is_active" class="text-[11px] text-[#6B6B6B] font-normal">· inativa</span>
            </p>
            <p class="text-[12px] text-[#A1A1A1] truncate">
              {{ u.address || 'Sem endereço' }} · {{ u.barbers_count }} {{ u.barbers_count === 1 ? 'barbeiro' : 'barbeiros' }}
            </p>
          </div>
          <button
            type="button"
            @click="openEdit(u)"
            class="h-9 px-3 rounded-[8px] text-[12px] font-medium text-[#A1A1A1] border border-[#2A2A2A] hover:text-white hover:border-[#3D3D3D] transition-colors"
          >
            Editar
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit sheet -->
    <Teleport to="body">
      <div v-if="sheetOpen" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40" @click="sheetOpen = false"></div>
      <div
        v-if="sheetOpen"
        class="fixed bottom-0 left-0 right-0 z-50 bg-[#131313] border-t border-[#2A2A2A] rounded-t-[20px] p-5 pb-8 space-y-4 animate-sheet"
      >
        <div class="w-10 h-1 bg-[#3D3D3D] rounded-full mx-auto"></div>
        <h3 class="text-[16px] font-semibold text-white">{{ editing ? 'Editar unidade' : 'Nova unidade' }}</h3>

        <div class="space-y-3">
          <input
            v-model="form.name"
            type="text"
            placeholder="Nome da unidade (ex.: Centro)"
            maxlength="100"
            class="block w-full h-12 px-4 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[14px] text-white outline-none focus:border-[#FFD60A] placeholder-[#6B6B6B]"
          />
          <input
            v-model="form.address"
            type="text"
            placeholder="Endereço (opcional)"
            maxlength="200"
            class="block w-full h-12 px-4 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[14px] text-white outline-none focus:border-[#FFD60A] placeholder-[#6B6B6B]"
          />
          <label v-if="editing" class="flex items-center justify-between h-12 px-4 bg-[#161616] border border-[#2A2A2A] rounded-[10px]">
            <span class="text-[14px] text-white">Unidade ativa</span>
            <input v-model="form.is_active" type="checkbox" class="w-5 h-5 accent-[#FFD60A]" />
          </label>
        </div>

        <p v-if="sheetError" class="text-[12px] text-[#EF4444]">{{ sheetError }}</p>

        <div class="flex gap-3">
          <button
            v-if="editing && canRemove"
            type="button"
            @click="removeUnit"
            class="h-12 px-4 rounded-[10px] text-[13px] font-medium text-[#EF4444] border border-[#EF4444]/30 hover:bg-[#EF4444]/10 transition-colors"
          >
            Remover
          </button>
          <button
            type="button"
            @click="sheetOpen = false"
            class="flex-1 h-12 rounded-[10px] text-[14px] font-medium text-[#A1A1A1] border border-[#2A2A2A]"
          >
            Cancelar
          </button>
          <button
            type="button"
            :disabled="!canSave || saving"
            @click="save"
            class="flex-1 h-12 rounded-[10px] text-[14px] font-bold text-[#0A0A0A] bg-[#FFD60A] enabled:hover:bg-[#FFE066] disabled:opacity-70 disabled:cursor-not-allowed"
          >
            {{ saving ? 'Salvando...' : 'Salvar' }}
          </button>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Plus, Store } from 'lucide-vue-next'
import AppLayout from '../../layouts/AppLayout.vue'
import Skeleton from '../../components/Skeleton.vue'
import { useConfirm } from '../../composables/useConfirm'
import { useToast } from '../../composables/useToast'

const { ask } = useConfirm()
const toast = useToast()

interface Unit {
  id: number
  name: string
  address: string | null
  is_active: boolean
  barbers_count: number
}

const loading = ref(true)
const units = ref<Unit[]>([])
const sheetOpen = ref(false)
const saving = ref(false)
const sheetError = ref('')
const editing = ref<Unit | null>(null)
const form = reactive({ name: '', address: '', is_active: true })

const canSave = computed(() => form.name.trim().length > 1)
// Não dá pra remover a última unidade ativa.
const canRemove = computed(() => units.value.filter((u) => u.is_active).length > 1)

const xsrf = () => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

const api = async (path: string, method: string, body?: any) => {
  const res = await fetch(path, {
    method,
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-XSRF-TOKEN': xsrf(),
    },
    body: body ? JSON.stringify(body) : undefined,
  })
  if (res.status === 401) window.location.href = '/login'
  return res
}

onMounted(async () => {
  try {
    const res = await api('/api/units', 'GET')
    if (res.ok) units.value = (await res.json()).data ?? []
  } finally {
    loading.value = false
  }
})

const openCreate = () => {
  editing.value = null
  form.name = ''
  form.address = ''
  form.is_active = true
  sheetError.value = ''
  sheetOpen.value = true
}

const openEdit = (u: Unit) => {
  editing.value = u
  form.name = u.name
  form.address = u.address ?? ''
  form.is_active = u.is_active
  sheetError.value = ''
  sheetOpen.value = true
}

const save = async () => {
  sheetError.value = ''
  saving.value = true
  try {
    const payload = { name: form.name.trim(), address: form.address.trim() || null, is_active: form.is_active }
    const res = editing.value
      ? await api(`/api/units/${editing.value.id}`, 'PUT', payload)
      : await api('/api/units', 'POST', payload)

    if (res.ok || res.status === 201) {
      const data = (await res.json()).data
      if (editing.value) {
        units.value = units.value.map((u) => (u.id === data.id ? data : u))
      } else {
        units.value = [...units.value, data]
      }
      sheetOpen.value = false
    } else {
      const b = await res.json().catch(() => ({}))
      sheetError.value = b?.message ?? (Object.values(b?.errors ?? {}).flat()[0] as string) ?? 'Não foi possível salvar.'
    }
  } finally {
    saving.value = false
  }
}

const removeUnit = async () => {
  if (!editing.value) return
  const ok = await ask(
    'Remover unidade?',
    `${editing.value.name} sai da rede. O histórico de atendimentos é mantido.`,
    { confirmText: 'Remover', destructive: true }
  )
  if (!ok) return

  const res = await api(`/api/units/${editing.value.id}`, 'DELETE')
  if (res.ok || res.status === 204) {
    units.value = units.value.filter((u) => u.id !== editing.value!.id)
    sheetOpen.value = false
  } else {
    const b = await res.json().catch(() => ({}))
    sheetError.value = b?.message ?? 'Não foi possível remover.'
  }
}
</script>
