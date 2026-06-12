<template>
  <div class="min-h-dvh w-full bg-[#0A0A0A] flex flex-col items-center justify-center px-5 text-center animate-enter">
    <div class="w-16 h-16 rounded-full bg-[#EF4444]/15 flex items-center justify-center mb-6">
      <AlertTriangle :size="32" :stroke-width="1.75" class="text-[#EF4444]" />
    </div>
    <h1 class="text-[64px] font-bold tracking-tight text-white leading-none tabular-nums">500</h1>
    <h2 class="text-[18px] font-semibold text-white mt-3">Algo deu errado no servidor</h2>
    <p class="text-[13px] text-[#A1A1A1] mt-2 max-w-[280px] leading-relaxed">
      Tente novamente em alguns instantes. Se persistir, entre em contato com o suporte.
    </p>
    <div class="mt-8 w-full max-w-[280px] flex flex-col gap-3">
      <button
        type="button"
        @click="reload"
        class="w-full h-12 rounded-[10px] font-bold text-[15px] text-[#0A0A0A] bg-[#FFD60A] hover:bg-[#FFE066] active:scale-[0.98] transition-all"
      >
        Tentar novamente
      </button>
      <Link
        href="/"
        class="block w-full h-12 leading-[3rem] text-[14px] font-medium text-[#A1A1A1] hover:text-white transition-colors"
      >
        Voltar para a agenda
      </Link>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { AlertTriangle } from 'lucide-vue-next'

const reload = () => window.location.reload()
</script>
