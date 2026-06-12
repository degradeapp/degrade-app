<template>
  <button
    :type="type"
    style="
      transition: all 200ms;
      font-weight: 600;
      height: 3rem;
      font-size: 1rem;
      border-radius: 0.625rem;
      padding: 0 1.5rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      border: none;
      min-width: 0;
      font-feature-settings: 'tnum';
    "
    :style="{
      ...buttonStyles,
      opacity: disabled || loading ? 0.7 : 1,
      transform: isPressed ? 'scale(0.98)' : 'scale(1)',
    }"
    :class="className"
    :disabled="disabled || loading"
    @click="$emit('click')"
    @mousedown="isPressed = true"
    @mouseup="isPressed = false"
    @mouseenter="isHovered = true"
    @mouseleave="isHovered = false; isPressed = false"
  >
    <span v-if="!loading" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem">
      <slot />
    </span>
    <span v-else style="display: flex; align-items: center; justify-content: center; gap: 0.5rem">
      <svg style="animation: spin 1s linear infinite; width: 1rem; height: 1rem" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle style="opacity: 0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path style="opacity: 0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <span>{{ loadingText }}</span>
    </span>
  </button>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

interface Props {
  type?: 'button' | 'submit' | 'reset'
  variant?: 'primary' | 'secondary' | 'destructive' | 'danger'
  disabled?: boolean
  loading?: boolean
  loadingText?: string
  className?: string
}

const props = withDefaults(defineProps<Props>(), {
  type: 'button',
  variant: 'primary',
  loadingText: 'Salvando...',
})

const isPressed = ref(false)
const isHovered = ref(false)

defineEmits<{
  click: []
}>()

const buttonStyles = computed(() => {
  const interactive = !props.disabled && !props.loading
  switch (props.variant) {
    case 'primary':
      return {
        backgroundColor: interactive && isPressed.value
          ? '#F5C400'
          : interactive && isHovered.value
            ? '#FFE066'
            : '#FFD60A',
        color: '#0A0A0A',
        boxShadow: interactive
          ? '0 8px 24px -8px rgba(255,214,10,0.5), inset 0 1px 0 rgba(255,255,255,0.25)'
          : 'none',
      }
    case 'secondary':
      return {
        backgroundColor: '#161616',
        borderWidth: '1.5px',
        borderStyle: 'solid',
        borderColor: '#2A2A2A',
        color: '#F5F5F5',
        boxShadow: 'none',
      }
    case 'destructive':
    case 'danger':
      return {
        backgroundColor: interactive && (isHovered.value || isPressed.value) ? 'rgba(239,68,68,0.08)' : 'transparent',
        borderWidth: '1.5px',
        borderStyle: 'solid',
        borderColor: '#EF4444',
        color: '#EF4444',
        boxShadow: 'none',
      }
    default:
      return {}
  }
})
</script>

<style scoped>
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>