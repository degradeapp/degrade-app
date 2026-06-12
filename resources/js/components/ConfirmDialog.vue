<script setup lang="ts">
import { useConfirm } from '@/composables/useConfirm'

const { dialogs } = useConfirm()
</script>

<template>
  <Teleport to="body">
    <transition-group name="confirm">
      <div
        v-for="dialog in dialogs"
        :key="dialog.id"
        class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center sm:justify-center"
      >
        <div class="w-full sm:w-96 bg-[#131313] border-t border-[#2A2A2A] sm:border sm:rounded-[10px] p-6 space-y-4 animate-in fade-in slide-in-from-bottom-4 sm:slide-in-from-center duration-300">
          <div>
            <h2 class="text-[18px] font-semibold text-white">{{ dialog.title }}</h2>
            <p class="text-[14px] text-[#A1A1A1] mt-2">{{ dialog.message }}</p>
          </div>

          <div class="flex gap-3 pt-2">
            <button
              type="button"
              @click="dialog.resolve(false)"
              class="flex-1 h-12 rounded-[10px] font-medium text-[14px] text-[#A1A1A1] border border-[#2A2A2A] bg-transparent hover:text-white hover:border-[#3D3D3D] transition-colors active:scale-[0.98]"
            >
              {{ dialog.cancelText }}
            </button>
            <button
              type="button"
              @click="dialog.resolve(true)"
              :class="{
                'bg-[#EF4444]/10 border-[#EF4444]/30 text-[#EF4444] hover:bg-[#EF4444]/15': dialog.destructive,
                'bg-[#FFD60A] text-[#0A0A0A] hover:bg-[#FFE066] active:bg-[#F5C400]': !dialog.destructive,
              }"
              class="flex-1 h-12 rounded-[10px] font-bold text-[14px] border transition-colors active:scale-[0.98]"
            >
              {{ dialog.confirmText }}
            </button>
          </div>
        </div>
      </div>
    </transition-group>
  </Teleport>
</template>

<style scoped>
.confirm-enter-active,
.confirm-leave-active {
  transition: all 0.3s ease;
}

.confirm-enter-from {
  opacity: 0;
}

.confirm-leave-to {
  opacity: 0;
}
</style>
