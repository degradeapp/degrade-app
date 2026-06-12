<script setup lang="ts">
import { ref, computed } from 'vue'

interface Props {
  title: string
  message: string
  icon?: 'search' | 'inbox' | 'calendar' | 'users'
  action?: {
    label: string
    href?: string
    onClick?: () => void
  }
}

defineProps<Props>()

const iconConfig = {
  search: {
    svg: `<svg class="w-16 h-16 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>`,
  },
  inbox: {
    svg: `<svg class="w-16 h-16 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 01-2 2H6a2 2 0 01-2-2m16 0V9a2 2 0 00-2-2H6a2 2 0 00-2 2v4"/></svg>`,
  },
  calendar: {
    svg: `<svg class="w-16 h-16 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>`,
  },
  users: {
    svg: `<svg class="w-16 h-16 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0zM15 20H9"/></svg>`,
  },
}
</script>

<template>
  <div class="flex flex-col items-center justify-center py-16 px-4 text-center">
    <div v-if="icon" class="text-[#A1A1A1] mb-6" v-html="iconConfig[icon]?.svg || iconConfig.inbox.svg"></div>

    <h3 class="text-[18px] font-semibold text-white mb-2">{{ title }}</h3>
    <p class="text-[13px] text-[#A1A1A1] mb-6 max-w-sm">{{ message }}</p>

    <a
      v-if="action?.href"
      :href="action.href"
      class="h-10 px-4 rounded-[10px] bg-[#FFD60A] text-[#0A0A0A] font-bold text-[14px] inline-flex items-center justify-center transition-colors hover:bg-[#FFE066] active:bg-[#F5C400]"
    >
      {{ action.label }}
    </a>
    <button
      v-else-if="action?.onClick"
      @click="action.onClick"
      class="h-10 px-4 rounded-[10px] bg-[#FFD60A] text-[#0A0A0A] font-bold text-[14px] inline-flex items-center justify-center transition-colors hover:bg-[#FFE066] active:bg-[#F5C400]"
    >
      {{ action.label }}
    </button>
  </div>
</template>
