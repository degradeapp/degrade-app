<script setup lang="ts">
const goBack = () => window.history.length > 1 ? window.history.back() : window.location.assign('/')
</script>

<template>
  <div class="h-screen w-screen flex flex-col items-center justify-center bg-[#0A0A0A] px-4">
    <div class="text-center animate-enter">
      <div class="text-[80px] font-bold text-[#EF4444] mb-4">403</div>
      <h1 class="text-[24px] font-bold text-white mb-2">Acesso negado</h1>
      <p class="text-[14px] text-[#A1A1A1] mb-8">Você não tem permissão para acessar este recurso.</p>
      <button
        @click="goBack"
        class="h-12 px-6 rounded-[10px] bg-[#FFD60A] text-[#0A0A0A] font-bold text-[14px] inline-flex items-center justify-center transition-all hover:bg-[#FFE066] active:bg-[#F5C400] active:scale-[0.98]"
      >
        Voltar
      </button>
    </div>
  </div>
</template>
