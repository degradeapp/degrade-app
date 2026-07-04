<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { ChevronLeft, CalendarPlus, UserPlus, Scissors, Tag, Search } from 'lucide-vue-next'
import BottomNav from '../components/BottomNav.vue'
import Toast from '../components/Toast.vue'
import ConfirmDialog from '../components/ConfirmDialog.vue'

interface Props {
  title?: string
  showHeader?: boolean
  showBackButton?: boolean
  // Destino fixo do botão fechar/voltar. Sem isso o X usa history.back(), que
  // gera loop em fluxos Editar→Salvar→Ver→X (X volta pro Editar). Passe a rota
  // pai (ex.: '/barbers') pra ter um caminho determinístico.
  backHref?: string
}

const props = withDefaults(defineProps<Props>(), {
  showHeader: true,
  showBackButton: false,
})

const showCreateMenu = ref(false)

const page = usePage()
const role = computed(() => (page.props as any).auth?.user?.role ?? null)

// Sincronização entre abas: o navegador tem UMA sessão só. Se outra aba logar com
// outro usuário, esta aba se re-sincroniza (recarrega) em vez de ficar mostrando uma
// sessão "fantasma" (ex.: tela de dono com sessão de recepcionista). Ao recarregar, o
// servidor renderiza pro usuário real (ou manda pro /403, ou pro login se deslogou).
const AUTH_KEY = 'degrade_auth_uid'
const currentUid = computed(() => (page.props as any).auth?.user?.id ?? null)
let onStorage: ((e: StorageEvent) => void) | null = null
onMounted(() => {
  if (currentUid.value != null) {
    localStorage.setItem(AUTH_KEY, String(currentUid.value))
  }
  onStorage = (e: StorageEvent) => {
    if (e.key === AUTH_KEY && e.newValue !== null && e.newValue !== String(currentUid.value)) {
      window.location.reload()
    }
  }
  window.addEventListener('storage', onStorage)
})
onUnmounted(() => {
  if (onStorage) window.removeEventListener('storage', onStorage)
})

// Cada atalho só aparece pra quem pode criar aquilo (espelha o gate das páginas/APIs):
// balcão (recepção/barbeiro) cria agendamento e cliente; barbeiro/serviço são gestão (dono/gerente).
const createActions = [
  { label: 'Novo agendamento', href: '/appointments/create', icon: CalendarPlus, roles: ['owner', 'manager', 'receptionist', 'barber'] },
  { label: 'Novo cliente', href: '/customers/create', icon: UserPlus, roles: ['owner', 'manager', 'receptionist', 'barber'] },
  { label: 'Novo barbeiro', href: '/barbers/create', icon: Scissors, roles: ['owner', 'manager'] },
  { label: 'Novo serviço', href: '/services/create', icon: Tag, roles: ['owner', 'manager'] },
]

const availableActions = computed(() =>
  createActions.filter((a) => !role.value || a.roles.includes(role.value))
)

const go = (href: string) => {
  showCreateMenu.value = false
  router.visit(href)
}

const goBack = () => {
  if (props.backHref) {
    router.visit(props.backHref)
  } else if (window.history.length > 1) {
    window.history.back()
  } else {
    router.visit('/')
  }
}
</script>

<template>
  <div class="h-screen w-screen flex flex-col overflow-hidden bg-[#0A0A0A] text-[#F5F5F5]">
    <!-- Header (só quando title definido) -->
    <header
      v-if="showHeader && title"
      class="px-4 py-4 flex items-center justify-between sticky top-0 z-40 bg-[#131313] border-b border-[#2A2A2A]"
    >
      <div class="flex items-center gap-3 flex-1 min-w-0">
        <button
          v-if="showBackButton || backHref"
          class="-ml-2 text-[#A1A1A1] hover:text-white transition-colors flex-shrink-0"
          aria-label="Voltar"
          @click="goBack"
        >
          <ChevronLeft :size="26" :stroke-width="2" />
        </button>
        <h1 class="text-[20px] font-semibold text-white truncate">{{ title }}</h1>
      </div>
      <button
        v-if="!showBackButton"
        @click="router.visit('/search')"
        class="text-[#A1A1A1] hover:text-white transition-colors flex-shrink-0"
        aria-label="Buscar"
      >
        <Search :size="20" :stroke-width="1.75" />
      </button>
      <slot name="header-right"></slot>
    </header>

    <!-- Main content -->
    <main class="flex-1 overflow-y-auto pb-20">
      <slot />
    </main>

    <!-- Bottom Nav — escondida em sub-páginas (criar/editar), que têm botão de voltar
         e seu próprio botão de salvar fixo no rodapé. -->
    <BottomNav v-if="!showBackButton" @fab-click="showCreateMenu = true" />

    <!-- FAB Create Menu (bottom sheet) -->
    <Teleport to="body">
      <transition name="fade-menu">
        <div
          v-if="showCreateMenu"
          class="fixed inset-0 bg-black/50 z-50 flex items-end"
          @click.self="showCreateMenu = false"
        >
          <div class="w-full bg-[#131313] border-t border-[#2A2A2A] rounded-t-[20px] p-4 pb-8 animate-in slide-in-from-bottom duration-300">
            <div class="w-10 h-1 bg-[#3D3D3D] rounded-full mx-auto mb-5"></div>
            <h3 class="text-[16px] font-semibold text-white mb-4 px-1">Criar novo</h3>
            <div class="space-y-1">
              <button
                v-for="action in availableActions"
                :key="action.href"
                @click="go(action.href)"
                class="w-full flex items-center gap-3 h-14 px-3 rounded-[12px] text-left hover:bg-[#1A1A1A] transition-colors active:scale-[0.99]"
              >
                <div class="w-10 h-10 rounded-full bg-[#FFD60A]/15 flex items-center justify-center flex-shrink-0">
                  <component :is="action.icon" :size="20" class="text-[#FFD60A]" :stroke-width="1.75" />
                </div>
                <span class="text-[15px] font-medium text-white">{{ action.label }}</span>
              </button>
            </div>
          </div>
        </div>
      </transition>
    </Teleport>

    <!-- Global Toast -->
    <Toast />

    <!-- Global Confirm Dialog -->
    <ConfirmDialog />
  </div>
</template>

<style scoped>
.fade-menu-enter-active,
.fade-menu-leave-active {
  transition: opacity 0.3s ease;
}
.fade-menu-enter-from,
.fade-menu-leave-to {
  opacity: 0;
}
</style>
