<script setup lang="ts">
interface Props {
  status: 'pending' | 'confirmed' | 'completed' | 'cancelled'
  size?: 'sm' | 'md'
}

withDefaults(defineProps<Props>(), {
  size: 'md',
})

const statusConfig = {
  pending: {
    label: 'Pendente',
    bg: 'bg-[#6B6B6B]/10',
    border: 'border-[#6B6B6B]/30',
    text: 'text-[#A1A1A1]',
  },
  confirmed: {
    label: 'Confirmado',
    bg: 'bg-[#A1A1A1]/10',
    border: 'border-[#A1A1A1]/30',
    text: 'text-[#A1A1A1]',
  },
  completed: {
    label: 'Concluído',
    bg: 'bg-[#22C55E]/10',
    border: 'border-[#22C55E]/30',
    text: 'text-[#22C55E]',
  },
  cancelled: {
    label: 'Cancelado',
    bg: 'bg-[#EF4444]/10',
    border: 'border-[#EF4444]/30',
    text: 'text-[#EF4444]',
  },
}
</script>

<template>
  <span
    :class="[
      statusConfig[status].bg,
      statusConfig[status].border,
      statusConfig[status].text,
      size === 'sm' ? 'text-[11px] px-2.5 py-1' : 'text-[12px] px-3 py-1.5',
      'inline-block border rounded-full font-medium'
    ]"
  >
    {{ statusConfig[status].label }}
  </span>
</template>
