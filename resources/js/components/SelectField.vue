<script setup lang="ts">
import { ref, computed } from 'vue'
import { ChevronDown, Check } from 'lucide-vue-next'

interface Option {
  value: string
  label: string
}

const props = defineProps<{
  modelValue: string
  options: Option[]
  title?: string
  placeholder?: string
  label?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const open = ref(false)
const selectedLabel = computed(() => props.options.find((o) => o.value === props.modelValue)?.label ?? '')

const select = (v: string) => {
  emit('update:modelValue', v)
  open.value = false
}
</script>

<template>
  <div>
    <button
      type="button"
      class="relative w-full h-12 px-4 bg-[#161616] border rounded-[10px] text-left outline-none transition-colors"
      :class="[open ? 'border-[#FFD60A]' : 'border-[#2A2A2A] hover:border-[#3D3D3D]', label ? 'pt-4 pb-1' : 'flex items-center']"
      @click="open = true"
    >
      <span v-if="label" class="absolute left-4 top-1.5 text-[11px] text-[#A1A1A1] pointer-events-none">{{ label }}</span>
      <span class="block truncate pr-6 text-[15px]" :class="selectedLabel ? 'text-white' : 'text-[#6B6B6B]'">{{ selectedLabel || placeholder || 'Selecione' }}</span>
      <ChevronDown :size="16" :stroke-width="2" class="absolute right-4 top-1/2 -translate-y-1/2 text-[#6B6B6B]" />
    </button>

    <Teleport to="body">
      <div v-if="open" class="fixed inset-0 z-[60]">
        <div class="absolute inset-0 bg-black/60" @click="open = false"></div>
        <div class="absolute bottom-0 left-0 right-0 max-w-[640px] mx-auto bg-[#131313] border-t border-[#2A2A2A] rounded-t-[20px] max-h-[70vh] flex flex-col pb-[max(1rem,env(safe-area-inset-bottom))]">
          <div class="flex justify-center pt-2 pb-1">
            <div class="w-10 h-1 bg-[#3D3D3D] rounded-full"></div>
          </div>
          <div v-if="title" class="px-5 pt-3 pb-2 border-b border-[#1F1F1F]">
            <h3 class="text-[16px] font-semibold text-white">{{ title }}</h3>
          </div>
          <div class="overflow-y-auto px-3 py-2">
            <button
              v-for="o in options"
              :key="o.value"
              type="button"
              class="w-full flex items-center justify-between px-3 h-12 rounded-[10px] text-left hover:bg-[#1A1A1A] transition-colors"
              :class="modelValue === o.value ? 'text-[#FFD60A]' : 'text-white'"
              @click="select(o.value)"
            >
              <span class="text-[14px]">{{ o.label }}</span>
              <Check v-if="modelValue === o.value" :size="16" :stroke-width="2.5" />
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
