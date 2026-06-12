<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ChevronLeft } from 'lucide-vue-next'

interface Props {
  title: string
  subtitle?: string
  showBack?: boolean
}

withDefaults(defineProps<Props>(), {
  showBack: true,
})
</script>

<template>
  <div class="px-4 py-6">
    <div class="flex items-start gap-3">
      <button
        v-if="showBack"
        @click="router.back()"
        class="flex-shrink-0 text-[#A1A1A1] hover:text-white transition-colors -ml-2 mt-0.5"
        aria-label="Voltar"
      >
        <ChevronLeft :size="24" :stroke-width="1.75" />
      </button>
      <div>
        <h1 class="text-[20px] font-semibold text-white">{{ title }}</h1>
        <p v-if="subtitle" class="text-[13px] text-[#A1A1A1] mt-1">{{ subtitle }}</p>
      </div>
    </div>
  </div>
</template>
