<script setup lang="ts">
import { useToast } from '@/composables/useToast'
import { Check, AlertCircle, Info, X } from 'lucide-vue-next'

const { toasts, remove } = useToast()
</script>

<template>
  <!-- Topo, logo abaixo do header: espaço sempre livre (não colide com a BottomNav
       nem com o rodapé fixo de "Salvar" das telas de edição). -->
  <div class="fixed top-[64px] left-0 right-0 z-50 flex flex-col items-center gap-2 px-4 pointer-events-none">
    <transition-group name="toast">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="w-full max-w-sm flex items-center gap-2.5 px-3.5 py-3 rounded-[12px] pointer-events-auto bg-[#1C1C1C] border shadow-lg shadow-black/40"
        :class="{
          'border-[#22C55E]/40': toast.type === 'success',
          'border-[#EF4444]/40': toast.type === 'error',
          'border-[#3B82F6]/40': toast.type === 'info',
        }"
      >
        <Check
          v-if="toast.type === 'success'"
          :size="18"
          class="flex-shrink-0 text-[#22C55E]"
          :stroke-width="2.5"
        />
        <AlertCircle
          v-else-if="toast.type === 'error'"
          :size="18"
          class="flex-shrink-0 text-[#EF4444]"
          :stroke-width="2.5"
        />
        <Info
          v-else
          :size="18"
          class="flex-shrink-0 text-[#3B82F6]"
          :stroke-width="2.5"
        />

        <p class="flex-1 text-[13.5px] font-medium text-[#F5F5F5] leading-snug">
          {{ toast.message }}
        </p>

        <button
          type="button"
          @click="remove(toast.id)"
          class="flex-shrink-0 -mr-1 text-[#6B6B6B] hover:text-white transition-colors"
          aria-label="Fechar notificação"
        >
          <X :size="16" :stroke-width="2" />
        </button>
      </div>
    </transition-group>
  </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.25s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-12px);
}
</style>
