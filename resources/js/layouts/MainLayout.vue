<template>
  <div v-if="isAuthenticated" class="h-screen w-screen flex flex-col overflow-hidden" :style="{ backgroundColor: '#0A0A0A', color: '#F5F5F5' }">
    <!-- AppLayout para páginas autenticadas -->
    <header class="px-4 py-4 flex items-center justify-between sticky top-0 z-40" :style="{ backgroundColor: '#131313', borderBottom: '1px solid #2A2A2A' }">
      <h1 class="text-xl font-semibold">{{ title }}</h1>
      <slot name="header-right"></slot>
    </header>

    <main class="flex-1 overflow-y-auto pb-20">
      <slot />
    </main>

    <BottomNav />
  </div>

  <div v-else class="h-screen w-screen" :style="{ backgroundColor: '#0A0A0A' }">
    <!-- Layout para Auth pages -->
    <slot />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import BottomNav from '@/components/BottomNav.vue'

const page = usePage()

const isAuthenticated = computed(() => !!page.props.auth?.user)
const title = computed(() => page.props.pageTitle || 'Degradê')
</script>
