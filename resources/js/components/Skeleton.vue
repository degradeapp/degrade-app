<template>
  <div
    class="animate-pulse rounded-md"
    :class="className"
    :style="{
      height,
      width,
      backgroundImage: 'linear-gradient(90deg, #131313, #161616)',
    }"
  ></div>
</template>

<script setup lang="ts">
interface Props {
  height?: string
  width?: string
  className?: string
}

withDefaults(defineProps<Props>(), {
  height: '1rem',
  width: '100%',
})
</script>
