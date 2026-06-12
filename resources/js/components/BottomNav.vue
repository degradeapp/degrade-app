<script setup lang="ts">
import { Home, CalendarDays, Users, Plus, MoreHorizontal } from 'lucide-vue-next'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()

defineEmits<{
  'fab-click': []
}>()

const isActive = (path: string) => {
  // Início é a raiz: só fica ativo na rota exata (senão tudo "começa com /").
  if (path === '/') return page.url === '/' || page.url === ''

  return page.url.startsWith(path)
}

const navItems = [
  { href: '/', icon: Home, label: 'Início' },
  { href: '/appointments', icon: CalendarDays, label: 'Agenda' },
  { href: '/customers', icon: Users, label: 'Clientes' },
  { href: '/settings', icon: MoreHorizontal, label: 'Mais' },
]
</script>

<template>
  <nav
    class="fixed bottom-0 left-0 right-0 h-16 flex items-center justify-around bg-[#131313] border-t border-[#2A2A2A]"
    :style="{ paddingBottom: 'max(env(safe-area-inset-bottom), 0px)' }"
  >
    <!-- Left items -->
    <template v-for="(item, idx) in navItems.slice(0, 2)" :key="item.href">
      <Link
        :href="item.href"
        class="flex-1 flex items-center justify-center relative h-full transition-colors"
        :class="isActive(item.href) ? 'text-[#FFD60A]' : 'text-[#6B6B6B] hover:text-[#A1A1A1]'"
      >
        <div class="flex flex-col items-center gap-1">
          <component :is="item.icon" :size="22" :stroke-width="1.75" />
          <div
            v-if="isActive(item.href)"
            class="w-1 h-1 rounded-full absolute bottom-2 bg-[#FFD60A]"
          ></div>
        </div>
      </Link>
    </template>

    <!-- Center FAB -->
    <button
      class="flex-1 flex items-center justify-center h-full -translate-y-4 transition-colors active:scale-95"
      @click="$emit('fab-click')"
      aria-label="Criar novo"
    >
      <div class="w-14 h-14 rounded-full flex items-center justify-center bg-[#FFD60A] text-[#0A0A0A] shadow-lg">
        <Plus :size="24" :stroke-width="2.5" />
      </div>
    </button>

    <!-- Right items -->
    <template v-for="(item, idx) in navItems.slice(2, 4)" :key="item.href">
      <Link
        :href="item.href"
        class="flex-1 flex items-center justify-center relative h-full transition-colors"
        :class="isActive(item.href) ? 'text-[#FFD60A]' : 'text-[#6B6B6B] hover:text-[#A1A1A1]'"
      >
        <div class="flex flex-col items-center gap-1">
          <component :is="item.icon" :size="22" :stroke-width="1.75" />
          <div
            v-if="isActive(item.href)"
            class="w-1 h-1 rounded-full absolute bottom-2 bg-[#FFD60A]"
          ></div>
        </div>
      </Link>
    </template>
  </nav>
</template>
