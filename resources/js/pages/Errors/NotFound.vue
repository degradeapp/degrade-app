<script setup lang="ts">
import { router } from '@inertiajs/vue3'
</script>

<template>
  <div class="h-screen w-screen flex flex-col items-center justify-center bg-[#0A0A0A] px-4">
    <div class="text-center animate-enter">
      <div class="text-[80px] font-bold text-[#FFD60A] mb-4">404</div>
      <h1 class="text-[24px] font-bold text-white mb-2">Página não encontrada</h1>
      <p class="text-[14px] text-[#A1A1A1] mb-8">A página que você procura não existe.</p>
      <a
        href="/"
        class="h-12 px-6 rounded-[10px] bg-[#FFD60A] text-[#0A0A0A] font-bold text-[14px] inline-flex items-center justify-center transition-all hover:bg-[#FFE066] active:bg-[#F5C400] active:scale-[0.98]"
      >
        Voltar ao início
      </a>
    </div>
  </div>
</template>
