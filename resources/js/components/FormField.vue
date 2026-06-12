<template>
  <div>
    <div class="relative">
      <input
        :id="id"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder || ' '"
        :disabled="disabled"
        :required="required"
        :pattern="pattern"
        :autocomplete="autocomplete"
        :maxlength="maxlength"
        class="peer block w-full h-12 px-4 pt-4 pb-1 bg-[#161616] border rounded-[10px] text-[15px] text-white outline-none transition-all duration-150 box-border tabular-nums focus:ring-2 disabled:opacity-60 disabled:cursor-not-allowed"
        :class="error
          ? 'border-[#EF4444] focus:border-[#EF4444] focus:ring-[#EF4444]/20'
          : 'border-[#2A2A2A] focus:border-[#FFD60A] focus:ring-[#FFD60A]/20'"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        @blur="$emit('blur')"
        @focus="$emit('focus')"
      />
      <label
        v-if="label"
        :for="id"
        class="absolute left-4 top-3.5 text-[14px] transition-all duration-150 pointer-events-none peer-focus:top-1.5 peer-focus:text-[11px] peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-[11px]"
        :class="error
          ? 'text-[#EF4444]'
          : 'text-[#6B6B6B] peer-focus:text-[#FFD60A] peer-[:not(:placeholder-shown)]:text-[#A1A1A1]'"
      >
        {{ label }}
      </label>
    </div>

    <div v-if="error" class="flex items-center gap-1.5 mt-1.5">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" class="flex-shrink-0 text-[#EF4444]">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.75" />
        <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
      </svg>
      <span class="text-[12px] text-[#EF4444]">{{ error }}</span>
    </div>
    <p v-else-if="hint" class="text-[11px] text-[#6B6B6B] mt-1.5 ml-1">{{ hint }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  id?: string
  modelValue: string | number
  label?: string
  type?: 'text' | 'email' | 'password' | 'tel' | 'number' | 'date' | 'time'
  placeholder?: string
  error?: string
  hint?: string
  disabled?: boolean
  required?: boolean
  pattern?: string
  autocomplete?: string
  maxlength?: number
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  modelValue: '',
})

const id = computed(() => props.id || `field-${Math.random().toString(36).slice(2, 11)}`)

defineEmits<{
  'update:modelValue': [value: string | number]
  blur: []
  focus: []
}>()
</script>
