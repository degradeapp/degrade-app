<template>
  <AppLayout title="Acessos" show-back-button>
    <div v-if="loading" class="p-4 space-y-3">
      <Skeleton v-for="i in 3" :key="i" height="72px" />
    </div>

    <div v-else class="p-4 pb-32 space-y-3 animate-enter">
      <p class="text-[13px] text-[#A1A1A1]">Quem pode entrar no sistema. Os barbeiros que atendem ficam em <span class="text-white font-medium">Equipe</span>.</p>

      <button
        type="button"
        @click="openInvite"
        class="w-full h-12 rounded-[10px] bg-[#FFD60A] text-[14px] font-bold text-[#0A0A0A] flex items-center justify-center gap-2 hover:bg-[#FFE066] active:scale-[0.98] transition-all"
      >
        <Plus :size="18" :stroke-width="2" />
        Adicionar acesso
      </button>

      <div v-if="team.length === 0" class="text-[13px] text-[#6B6B6B] text-center py-10">
        Nenhum membro cadastrado.
      </div>

      <div v-else class="space-y-2 stagger">
        <div
          v-for="m in team"
          :key="m.id"
          class="bg-[#131313] border border-[#2A2A2A] rounded-[12px] p-3 flex items-center gap-3"
        >
          <div class="w-10 h-10 rounded-full bg-[#1A1A1A] border border-[#2A2A2A] flex items-center justify-center text-[12px] font-semibold text-[#A1A1A1]">
            {{ initials(m.name) }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-[14px] font-medium text-white truncate">{{ m.name }}</p>
            <p class="text-[12px] text-[#A1A1A1] truncate">{{ m.email }} · {{ roleLabel(m.role) }}</p>
          </div>
          <button
            v-if="m.id !== currentUserId"
            type="button"
            @click="removeMember(m)"
            class="h-9 px-3 rounded-[8px] text-[12px] font-medium text-[#EF4444] border border-[#EF4444]/30 hover:bg-[#EF4444]/10 transition-colors"
          >
            Remover
          </button>
        </div>
      </div>
    </div>

    <!-- Invite sheet -->
    <Teleport to="body">
      <div
        v-if="inviteOpen"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40"
        @click="inviteOpen = false"
      ></div>
      <div
        v-if="inviteOpen"
        class="fixed bottom-0 left-0 right-0 z-50 bg-[#131313] border-t border-[#2A2A2A] rounded-t-[20px] p-5 pb-8 space-y-4 animate-sheet"
      >
        <div class="w-10 h-1 bg-[#3D3D3D] rounded-full mx-auto"></div>
        <h3 class="text-[16px] font-semibold text-white">Novo acesso</h3>

        <div class="space-y-3">
          <input
            v-model="invite.name"
            type="text"
            placeholder="Nome"
            maxlength="100"
            class="block w-full h-12 px-4 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[14px] text-white outline-none focus:border-[#FFD60A] placeholder-[#6B6B6B]"
          />
          <input
            v-model="invite.email"
            type="email"
            placeholder="email@exemplo.com"
            maxlength="150"
            class="block w-full h-12 px-4 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[14px] text-white outline-none focus:border-[#FFD60A] placeholder-[#6B6B6B]"
          />
          <div class="relative">
            <input
              v-model="invite.password"
              type="text"
              placeholder="Senha de acesso (mín. 8)"
              maxlength="72"
              class="block w-full h-12 pl-4 pr-20 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[14px] text-white tabular-nums outline-none focus:border-[#FFD60A] placeholder-[#6B6B6B]"
            />
            <button
              type="button"
              @click="generatePassword"
              class="absolute right-2 top-1/2 -translate-y-1/2 h-8 px-3 rounded-[8px] text-[12px] font-medium text-[#FFD60A] hover:bg-[#FFD60A]/10 transition-colors"
            >
              Gerar
            </button>
          </div>
          <SelectField v-model="invite.role" :options="roleOptions" title="Papel do membro" />
        </div>

        <p v-if="inviteError" class="text-[12px] text-[#EF4444]">{{ inviteError }}</p>

        <div class="flex gap-3">
          <button
            type="button"
            @click="inviteOpen = false"
            class="flex-1 h-12 rounded-[10px] text-[14px] font-medium text-[#A1A1A1] border border-[#2A2A2A]"
          >
            Cancelar
          </button>
          <button
            type="button"
            :disabled="!canInvite || sending"
            @click="sendInvite"
            class="flex-1 h-12 rounded-[10px] text-[14px] font-bold text-[#0A0A0A] bg-[#FFD60A] enabled:hover:bg-[#FFE066] disabled:opacity-70 disabled:cursor-not-allowed"
          >
            {{ sending ? 'Salvando...' : 'Adicionar' }}
          </button>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Plus } from 'lucide-vue-next'
import AppLayout from '../../layouts/AppLayout.vue'
import Skeleton from '../../components/Skeleton.vue'
import SelectField from '../../components/SelectField.vue'
import { useConfirm } from '../../composables/useConfirm'
import { useToast } from '../../composables/useToast'

const { ask } = useConfirm()
const toast = useToast()

const roleOptions = [
  { value: 'manager', label: 'Gerente' },
  { value: 'receptionist', label: 'Recepcionista' },
  { value: 'barber', label: 'Barbeiro' },
  { value: 'owner', label: 'Dono' },
]

interface Member {
  id: number
  name: string
  email: string
  role: string
}

const loading = ref(true)
const team = ref<Member[]>([])
const currentUserId = ref<number | null>(null)

const inviteOpen = ref(false)
const sending = ref(false)
const inviteError = ref('')
const invite = reactive({ name: '', email: '', password: '', role: 'receptionist' })

const openInvite = () => {
  Object.assign(invite, { name: '', email: '', password: '', role: 'receptionist' })
  inviteError.value = ''
  inviteOpen.value = true
}

const xsrf = () =>
  decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

const initials = (name: string) =>
  name.trim().split(/\s+/).map((p) => p[0]).slice(0, 2).join('').toUpperCase()

const roleLabel = (role: string) =>
  ({ owner: 'Dono', manager: 'Gerente', receptionist: 'Recepcionista', barber: 'Barbeiro' }[role] ?? role)

const canInvite = computed(
  () => invite.name.trim().length > 1 && invite.email.includes('@') && invite.password.length >= 8
)

// Gera uma senha forte (sem caracteres ambíguos) pro dono repassar ao membro.
const generatePassword = () => {
  const chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'
  let p = ''
  for (let i = 0; i < 10; i++) p += chars[Math.floor(Math.random() * chars.length)]
  invite.password = p
}

onMounted(async () => {
  try {
    const [teamRes, profileRes] = await Promise.all([
      fetch('/api/tenant/team', { headers: { Accept: 'application/json' } }),
      fetch('/api/profile', { headers: { Accept: 'application/json' } }),
    ])
    if (teamRes.ok) {
      const json = await teamRes.json()
      team.value = json.data ?? []
    }
    if (profileRes.ok) {
      const p = (await profileRes.json()).data
      currentUserId.value = p?.id ?? null
    }
  } finally {
    loading.value = false
  }
})

const sendInvite = async () => {
  inviteError.value = ''
  sending.value = true
  try {
    const res = await fetch('/api/tenant/team', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrf(),
      },
      body: JSON.stringify(invite),
    })
    if (res.ok || res.status === 201) {
      const json = await res.json()
      team.value = [...team.value, json.data]
      Object.assign(invite, { name: '', email: '', password: '', role: 'receptionist' })
      inviteOpen.value = false
      toast.success('Membro adicionado. Envie o e-mail e a senha pra ele entrar.', 5000)
    } else if (res.status === 401) {
      window.location.href = '/login' // sessão expirou: refaz o login
    } else if (res.status === 422) {
      const body = await res.json().catch(() => ({}))
      inviteError.value = Object.values(body?.errors ?? {}).flat()[0] as string ?? body?.message ?? 'Dados inválidos.'
    } else {
      const body = await res.json().catch(() => ({}))
      inviteError.value = body?.message ?? `Erro ${res.status}.`
    }
  } finally {
    sending.value = false
  }
}

const removeMember = async (m: Member) => {
  const ok = await ask(
    'Remover membro?',
    `${m.name} perderá o acesso à barbearia. Esta ação não pode ser desfeita.`,
    { confirmText: 'Remover', destructive: true }
  )
  if (!ok) return

  const res = await fetch(`/api/tenant/team/${m.id}`, {
    method: 'DELETE',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-XSRF-TOKEN': xsrf(),
    },
  })
  if (res.ok || res.status === 204) {
    team.value = team.value.filter((x) => x.id !== m.id)
  } else if (res.status === 401) {
    window.location.href = '/login' // sessão expirou: refaz o login
  } else {
    const body = await res.json().catch(() => ({}))
    toast.error(body?.message ?? 'Não foi possível remover o membro.')
  }
}
</script>
