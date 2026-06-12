<template>
  <AppLayout title="Mais">
    <div class="p-4 pb-24 space-y-6 stagger">
      <!-- Identidade da barbearia — só dono/gerente abrem as configurações dela -->
      <component
        :is="canManageBusiness ? Link : 'div'"
        :href="canManageBusiness ? '/settings/business' : undefined"
        class="relative overflow-hidden block rounded-[16px] border border-[#2A2A2A] bg-gradient-to-br from-[#1C1C18] to-[#131313] p-4"
        :class="canManageBusiness ? 'hover:border-[#3D3D3D] transition-colors' : ''"
      >
        <div class="relative flex items-center gap-3.5">
          <div class="w-14 h-14 rounded-full overflow-hidden flex items-center justify-center flex-shrink-0" :class="logoUrl ? 'bg-[#1A1A1A] border border-[#2A2A2A]' : 'bg-[#FFD60A]'">
            <img v-if="logoUrl" :src="logoUrl" alt="" class="w-full h-full object-cover" />
            <span v-else class="text-[18px] font-bold text-[#0A0A0A]">{{ initials }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-[16px] font-semibold text-white truncate">{{ tenantName }}</p>
            <p class="text-[12px] text-[#A1A1A1] truncate">{{ userName }}<span v-if="roleLabel"> · {{ roleLabel }}</span></p>
          </div>
          <span v-if="planLabel && role === 'owner'" class="text-[10px] px-2 py-1 rounded-full bg-[#FFD60A]/15 text-[#FFD60A] font-medium flex-shrink-0">
            {{ planLabel }}
          </span>
          <ChevronRight v-if="canManageBusiness" :size="18" class="text-[#6B6B6B] flex-shrink-0" :stroke-width="1.75" />
        </div>
      </component>

      <!-- Seções -->
      <div v-for="group in groups" :key="group.section" class="space-y-2">
        <p class="text-[10px] uppercase tracking-[0.08em] font-medium text-[#6B6B6B] px-1">{{ group.label }}</p>
        <Link
          v-for="item in group.items"
          :key="item.href"
          :href="item.href"
          class="block bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-4 hover:border-[#3D3D3D] transition-colors"
        >
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-[10px] bg-[#1A1A1A] border border-[#2A2A2A] flex items-center justify-center text-[#FFD60A] flex-shrink-0">
              <component :is="item.icon" :size="18" :stroke-width="1.75" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-[15px] font-medium text-white">{{ item.title }}</p>
              <p class="text-[12px] text-[#A1A1A1] mt-0.5 truncate">{{ item.description }}</p>
            </div>
            <ChevronRight :size="18" class="text-[#6B6B6B] flex-shrink-0" :stroke-width="1.75" />
          </div>
        </Link>
      </div>

      <button
        type="button"
        @click="logout"
        class="w-full h-12 rounded-[10px] text-[14px] font-medium text-[#EF4444] border border-[#EF4444]/30 hover:bg-[#EF4444]/10 transition-colors"
      >
        Sair da conta
      </button>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '../../layouts/AppLayout.vue'
import {
  Building2, Clock, KeyRound, User, MessageCircle, CreditCard, Bell, History, BarChart3, ChevronRight, Tag, Wallet, Scissors, Store,
} from 'lucide-vue-next'

const page = usePage()

const role = computed(() => (page.props as any).auth?.user?.role ?? null)
const userName = computed(() => (page.props as any).auth?.user?.name ?? '')
const tenantName = computed(() => (page.props as any).tenant?.name ?? 'Minha barbearia')
const plan = computed(() => (page.props as any).tenant?.plan ?? null)
const logoUrl = computed(() => (page.props as any).tenant?.logo_url ?? null)

const roleLabels: Record<string, string> = { owner: 'Dono', manager: 'Gerente', receptionist: 'Recepcionista', barber: 'Barbeiro' }
const roleLabel = computed(() => (role.value ? roleLabels[role.value] ?? '' : ''))

// Só dono/gerente gerenciam a barbearia (o card vira link); recepção/barbeiro veem só informativo.
const canManageBusiness = computed(() => role.value === 'owner' || role.value === 'manager')

const planLabels: Record<string, string> = { solo: 'Solo', barbearia: 'Barbearia', rede: 'Rede' }
const planLabel = computed(() => (plan.value ? planLabels[plan.value] ?? plan.value : 'Trial'))

// "Unidades" só aparece pra quem é Rede (pode abrir várias) ou já tem mais de uma.
const isRede = computed(() => plan.value === 'rede')
const hasMultiUnits = computed(() => (page.props as any).units?.multiple === true)
const canSeeUnits = computed(() => isRede.value || hasMultiUnits.value)

const initials = computed(() =>
  tenantName.value.trim().split(/\s+/).map((p: string) => p[0]).slice(0, 2).join('').toUpperCase()
)

interface Item {
  section: string
  href: string
  icon: any
  title: string
  description: string
  roles?: string[]
  requiresRede?: boolean
}

const sections = [
  { section: 'gestao', label: 'Gestão' },
  { section: 'ajustes', label: 'Ajustes' },
  { section: 'conta', label: 'Conta' },
]

const items: Item[] = [
  { section: 'gestao', href: '/barbers', icon: Scissors, title: 'Equipe', description: 'Barbeiros, horários e folgas', roles: ['owner', 'manager'] },
  { section: 'gestao', href: '/services', icon: Tag, title: 'Serviços', description: 'Serviços que você oferece e seus preços', roles: ['owner', 'manager'] },
  { section: 'gestao', href: '/commissions', icon: Wallet, title: 'Comissões', description: 'Comissões dos barbeiros por atendimento', roles: ['owner', 'manager'] },
  { section: 'gestao', href: '/reports', icon: BarChart3, title: 'Relatórios', description: 'Receita, comissões e top atendimentos', roles: ['owner', 'manager'] },
  { section: 'gestao', href: '/settings/units', icon: Store, title: 'Unidades', description: 'Locais da sua rede e endereços', roles: ['owner'], requiresRede: true },
  { section: 'ajustes', href: '/settings/team', icon: KeyRound, title: 'Acessos', description: 'Quem entra no sistema e suas permissões', roles: ['owner'] },
  { section: 'ajustes', href: '/settings/hours', icon: Clock, title: 'Horários', description: 'Horários padrão de funcionamento', roles: ['owner', 'manager'] },
  { section: 'ajustes', href: '/settings/business', icon: Building2, title: 'Barbearia', description: 'Nome, fuso horário, política de cancelamento', roles: ['owner', 'manager'] },
  { section: 'ajustes', href: '/settings/notifications', icon: Bell, title: 'Notificações', description: 'Canais, lembretes e confirmações', roles: ['owner', 'manager'] },
  { section: 'ajustes', href: '/whatsapp/setup', icon: MessageCircle, title: 'WhatsApp', description: 'Cloud API, webhook e tokens', roles: ['owner'] },
  { section: 'conta', href: '/settings/profile', icon: User, title: 'Meu perfil', description: 'Nome, email e senha' },
  { section: 'conta', href: '/billing', icon: CreditCard, title: 'Plano e cobrança', description: 'Plano atual, próximas cobranças, cartão', roles: ['owner'] },
  { section: 'conta', href: '/audit', icon: History, title: 'Histórico', description: 'Auditoria de alterações no sistema', roles: ['owner', 'manager'] },
]

const groups = computed(() =>
  sections
    .map((s) => ({
      ...s,
      items: items.filter((i) =>
        i.section === s.section
        && (!i.roles || (role.value && i.roles.includes(role.value)))
        && (!i.requiresRede || canSeeUnits.value)
      ),
    }))
    .filter((g) => g.items.length > 0)
)

const logout = () => {
  router.post('/logout')
}
</script>
