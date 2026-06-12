<script setup lang="ts">
interface Props {
  code: string
  message?: string
  showHome?: boolean
}

withDefaults(defineProps<Props>(), {
  showHome: true,
})

const errorMessages: Record<string, string> = {
  '404': 'Página não encontrada',
  '403': 'Acesso negado',
  '500': 'Erro interno do servidor',
  '503': 'Serviço indisponível',
}
</script>

<template>
  <div class="flex flex-col items-center justify-center py-20 px-4">
    <div class="text-[80px] font-bold text-[#FFD60A] mb-4">{{ code }}</div>
    <h1 class="text-[24px] font-semibold text-white mb-2">{{ message || errorMessages[code] || 'Erro desconhecido' }}</h1>
    <p class="text-[14px] text-[#A1A1A1] mb-8 max-w-sm text-center">
      Desculpe, algo deu errado. Por favor, tente novamente ou volte para a página inicial.
    </p>

    <a
      v-if="showHome"
      href="/dashboard"
      class="h-12 px-6 rounded-[10px] bg-[#FFD60A] text-[#0A0A0A] font-bold text-[14px] inline-flex items-center justify-center transition-colors hover:bg-[#FFE066] active:bg-[#F5C400]"
    >
      Voltar ao início
    </a>
  </div>
</template>
