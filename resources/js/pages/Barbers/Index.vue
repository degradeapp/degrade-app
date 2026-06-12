<template>
  <AppLayout title="Equipe" @fab-click="showAddMenu = true">
    <div class="p-4 space-y-3 pb-24 animate-enter">
      <div class="relative">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Buscar na equipe..."
          class="block w-full h-12 pl-11 pr-4 bg-[#161616] border border-[#2A2A2A] rounded-[10px] text-[14px] text-white outline-none focus:border-[#FFD60A] placeholder-[#6B6B6B]"
        />
        <Search :size="18" class="absolute left-3.5 top-3.5 text-[#6B6B6B]" :stroke-width="1.75" />
      </div>

      <!-- Adicionar membro: abre o menu pra escolher o tipo -->
      <button
        v-if="members.length > 0"
        type="button"
        @click="showAddMenu = true"
        class="flex items-center justify-center gap-2 w-full h-12 rounded-[10px] border border-[#FFD60A]/30 text-[14px] font-semibold text-[#FFD60A] hover:bg-[#FFD60A]/10 transition-colors"
      >
        <Plus :size="18" :stroke-width="2.5" />
        Adicionar membro
      </button>

      <div v-if="loading && members.length === 0" class="space-y-3">
        <Skeleton v-for="i in 3" :key="i" height="80px" />
      </div>

      <div v-else-if="filtered.length === 0" class="text-center py-16">
        <Users :size="32" class="text-[#6B6B6B] mx-auto mb-3" :stroke-width="1.75" />
        <p class="text-[15px] font-medium text-white mb-1">{{ searchQuery ? 'Nada encontrado' : 'Equipe vazia' }}</p>
        <p class="text-[13px] text-[#A1A1A1]">
          {{ searchQuery ? 'Tente outro termo.' : 'Comece adicionando o primeiro membro.' }}
        </p>
        <button
          v-if="!searchQuery"
          type="button"
          @click="showAddMenu = true"
          class="inline-block mt-4 h-11 px-5 rounded-[10px] bg-[#FFD60A] text-[14px] font-bold text-[#0A0A0A] hover:bg-[#FFE066]"
        >
          + Adicionar membro
        </button>
      </div>

      <div v-else class="space-y-2 stagger">
        <p class="text-[12px] text-[#6B6B6B] px-1 tabular-nums">
          {{ filtered.length }} na equipe
        </p>
        <component
          :is="m.href ? Link : 'div'"
          v-for="m in filtered"
          :key="m.key"
          :href="m.href || undefined"
          class="block bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4 transition-colors flex items-center gap-3"
          :class="m.href ? 'hover:border-[#3D3D3D]' : ''"
        >
          <div class="w-12 h-12 rounded-full overflow-hidden bg-[#1A1A1A] border border-[#2A2A2A] flex items-center justify-center text-[14px] font-semibold text-[#FFD60A] flex-shrink-0">
            <img v-if="m.photo_url" :src="m.photo_url" alt="" class="w-full h-full object-cover" />
            <span v-else>{{ initials(m.name) }}</span>
          </div>
          <div class="flex-1 min-w-0 space-y-0.5 leading-tight">
            <div class="flex items-center gap-2">
              <p class="text-[14px] font-medium text-white truncate">{{ m.name }}</p>
              <span
                class="text-[10px] px-1.5 py-0.5 rounded-full font-medium flex-shrink-0"
                :class="m.isOwner ? 'bg-[#FFD60A]/15 text-[#FFD60A]' : 'bg-[#2A2A2A] text-[#A1A1A1]'"
              >
                {{ m.roleLabel }}
              </span>
              <span
                v-if="m.kind === 'barber' && !m.is_active"
                class="text-[10px] px-1.5 py-0.5 rounded-full bg-[#3D3D3D]/40 text-[#A1A1A1] font-medium flex-shrink-0"
              >
                Inativo
              </span>
            </div>
            <p class="text-[12px] text-[#A1A1A1] truncate">
              {{ m.kind === 'barber' ? (formatPhone(m.phone) || 'Sem telefone') : m.email }}
            </p>
            <p v-if="m.kind === 'barber'" class="text-[11px] text-[#6B6B6B] tabular-nums">
              Comissão {{ (m.commission ?? 0).toFixed(0) }}%
            </p>
            <p v-else class="text-[11px] text-[#6B6B6B]">Acesso ao sistema, sem agenda</p>
          </div>
          <ChevronRight :size="16" class="text-[#3D3D3D] flex-shrink-0" :stroke-width="1.75" />
        </component>
      </div>
    </div>

    <!-- Escolher o tipo de membro: barbeiro (agenda) ou acesso (recepção/gerente) -->
    <Teleport to="body">
      <transition name="fade-menu">
        <div
          v-if="showAddMenu"
          class="fixed inset-0 bg-black/50 z-50 flex items-end"
          @click.self="showAddMenu = false"
        >
          <div class="w-full bg-[#131313] border-t border-[#2A2A2A] rounded-t-[20px] p-4 pb-8 animate-in slide-in-from-bottom duration-300">
            <div class="w-10 h-1 bg-[#3D3D3D] rounded-full mx-auto mb-5"></div>
            <h3 class="text-[16px] font-semibold text-white mb-1 px-1">Adicionar membro</h3>
            <p class="text-[12px] text-[#6B6B6B] mb-4 px-1">Que tipo de pessoa entra na equipe?</p>
            <div class="space-y-1">
              <button
                v-for="opt in addOptions"
                :key="opt.href"
                @click="go(opt.href)"
                class="w-full flex items-center gap-3 px-3 py-3 rounded-[12px] text-left hover:bg-[#1A1A1A] transition-colors active:scale-[0.99]"
              >
                <div class="w-10 h-10 rounded-full bg-[#FFD60A]/15 flex items-center justify-center flex-shrink-0">
                  <component :is="opt.icon" :size="20" class="text-[#FFD60A]" :stroke-width="1.75" />
                </div>
                <span class="min-w-0">
                  <span class="block text-[15px] font-medium text-white">{{ opt.label }}</span>
                  <span class="block text-[12px] text-[#A1A1A1]">{{ opt.desc }}</span>
                </span>
              </button>
            </div>
          </div>
        </div>
      </transition>
    </Teleport>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { Users, Search, ChevronRight, Plus, Scissors, KeyRound } from 'lucide-vue-next'
import AppLayout from '../../layouts/AppLayout.vue'
import Skeleton from '../../components/Skeleton.vue'
import { useFormatting } from '../../composables/useFormatting'
import { useApi } from '../../composables/useApi'

const { formatPhone } = useFormatting()
const api = useApi()

interface Member {
  kind: 'barber' | 'access'
  key: string
  name: string
  phone?: string | null
  email?: string | null
  photo_url?: string | null
  is_active?: boolean
  roleLabel: string
  isOwner: boolean
  commission?: number | null
  href: string
}

const searchQuery = ref('')
const loading = ref(true)
const members = ref<Member[]>([])
const showAddMenu = ref(false)

// Barbeiro = atende/entra na agenda. Recepção/gerente = login pro sistema (Acessos).
const addOptions = [
  { label: 'Barbeiro', desc: 'Atende clientes e entra na agenda', href: '/barbers/create', icon: Scissors },
  { label: 'Recepção ou gerente', desc: 'Acesso ao sistema, sem agenda', href: '/settings/team', icon: KeyRound },
]

const go = (href: string) => {
  showAddMenu.value = false
  router.visit(href)
}

const initials = (name: string) =>
  name.trim().split(/\s+/).map((p) => p[0]).slice(0, 2).join('').toUpperCase()

const roleLabelFor = (role?: string | null): string => {
  switch (role) {
    case 'owner': return 'Dono'
    case 'manager': return 'Gerente'
    case 'receptionist': return 'Recepção'
    default: return 'Barbeiro'
  }
}

const load = async () => {
  loading.value = true

  // Barbeiros (todos) + logins (owner-only; gerente cai em 403 e segue só com barbeiros).
  const [barbersRes, teamRes] = await Promise.all([
    api.get<any[]>('/api/barbers'),
    api.get<any[]>('/api/tenant/team'),
  ])

  const barbers = barbersRes.ok ? ((barbersRes.data as any[]) ?? []) : []
  const team = teamRes.ok ? ((teamRes.data as any[]) ?? []) : []

  const roleByUserId = new Map(team.map((u) => [u.id, u.role]))
  const linkedUserIds = new Set(barbers.map((b) => b.user_id).filter(Boolean))

  // Barbeiros (quem atende). Se tem login, herda a função (dono/gerente); senão é barbeiro.
  const barberMembers: Member[] = barbers.map((b) => {
    const role = b.user_id ? roleByUserId.get(b.user_id) : null
    return {
      kind: 'barber',
      key: `b${b.id}`,
      name: b.name,
      phone: b.phone,
      photo_url: b.photo_url,
      is_active: b.is_active,
      roleLabel: roleLabelFor(role),
      isOwner: role === 'owner',
      commission: b.default_commission_percentage != null ? Number(b.default_commission_percentage) : null,
      href: `/barbers/${b.id}`,
    }
  })

  // Logins que NÃO são barbeiros (recepção, gerente sem perfil de atendimento).
  const accessMembers: Member[] = team
    .filter((u) => !linkedUserIds.has(u.id))
    .map((u) => ({
      kind: 'access',
      key: `u${u.id}`,
      name: u.name,
      email: u.email,
      roleLabel: roleLabelFor(u.role),
      isOwner: u.role === 'owner',
      href: '/settings/team',
    }))

  members.value = [...barberMembers, ...accessMembers]
  loading.value = false
}

const filtered = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return members.value
  const digits = q.replace(/\D/g, '')
  return members.value.filter((m) =>
    m.name.toLowerCase().includes(q) ||
    (!!digits && (m.phone ?? '').includes(digits)) ||
    (m.email ?? '').toLowerCase().includes(q)
  )
})

onMounted(load)
</script>
