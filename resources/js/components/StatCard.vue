<template>
  <div class="p-4 rounded-md" :style="{ backgroundColor: '#161616', border: '1px solid #2A2A2A' }">
    <div class="flex items-start justify-between mb-3">
      <span class="text-xs font-medium uppercase tracking-wide" :style="{ color: '#6B6B6B' }">
        {{ label }}
      </span>
      <component
        :is="IconComponent"
        v-if="IconComponent"
        :size="18"
        :stroke-width="1.75"
        :style="{ color: '#6B6B6B' }"
      />
    </div>

    <p class="text-2xl font-bold tabular-nums mb-2" :style="{ color: '#F5F5F5' }">
      {{ value }}
    </p>

    <div v-if="trend !== undefined" class="flex items-center gap-1.5">
      <TrendIcon :trend="trend" />
      <span
        class="text-xs font-medium"
        :style="{
          color: trend >= 0 ? '#10B981' : '#EF4444',
        }"
      >
        {{ Math.abs(trend) }}%
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { TrendingUp, TrendingDown, Calendar, Award, Users } from 'lucide-vue-next'

interface Props {
  label: string
  value: string | number
  trend?: number
  icon?: 'Calendar' | 'Award' | 'Users' | 'TrendingUp'
  isCurrency?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  trend: undefined,
  isCurrency: false,
})

const IconComponent = computed(() => {
  switch (props.icon) {
    case 'Calendar':
      return Calendar
    case 'Award':
      return Award
    case 'Users':
      return Users
    case 'TrendingUp':
      return TrendingUp
    default:
      return null
  }
})

const TrendIcon = computed(() => {
  return props.trend !== undefined ? (props.trend >= 0 ? TrendingUp : TrendingDown) : null
})
</script>
