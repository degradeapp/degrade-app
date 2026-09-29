<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import {
  CalendarCheck, Link2, Wallet, Users, BarChart3, Smartphone, ShieldCheck, Check, ChevronDown,
} from 'lucide-vue-next'
import { ref } from 'vue'
import { useFormatting } from '@/composables/useFormatting'

interface Plan {
  plan: string
  label: string
  price: number
  staff_limit: number
  description: string
}

const props = defineProps<{ plans: Plan[]; trialDays: number }>()

const { formatBRL } = useFormatting()

const steps = [
  { n: '1', title: 'Cadastre em 10 minutos', text: 'Serviços, preços, horários e a sua equipe. Sem instalar nada.' },
  { n: '2', title: 'Compartilhe o seu link', text: 'Na bio do Instagram, no WhatsApp e num QR code no balcão.' },
  { n: '3', title: 'O cliente agenda sozinho', text: 'A qualquer hora, sem baixar app. Você só atende.' },
]

const features = [
  { icon: Link2, title: 'Link de agendamento', text: 'O cliente escolhe serviço, barbeiro e horário livre. Nada de conflito de horário.' },
  { icon: CalendarCheck, title: 'Agenda por barbeiro', text: 'Encaixe no balcão, remarcação e status de cada atendimento num toque.' },
  { icon: Wallet, title: 'Comissão automática', text: 'Concluiu o atendimento, a comissão de cada barbeiro já está calculada.' },
  { icon: Users, title: 'Clientes com histórico', text: 'Quantas vezes veio, quanto gastou e quando foi a última visita.' },
  { icon: BarChart3, title: 'Relatórios de faturamento', text: 'Quanto entrou no dia, na semana e no mês, e quem mais faturou.' },
  { icon: Smartphone, title: 'Feito para o celular', text: 'Instala na tela inicial como um app. Funciona no balcão e na cadeira.' },
]

const faqs = [
  { q: 'Meu cliente precisa baixar algum aplicativo?', a: 'Não. Ele abre o seu link no navegador do celular, escolhe o horário e pronto.' },
  { q: 'Preciso de cartão de crédito para testar?', a: `Não. São ${props.trialDays} dias grátis com tudo liberado, sem cartão.` },
  { q: 'Tem fidelidade ou multa para cancelar?', a: 'Não. Você cancela quando quiser pela própria plataforma e continua usando até o fim do período já pago.' },
  { q: 'Qual a diferença entre os planos?', a: 'Só o número de profissionais. Todas as funções estão nos dois planos.' },
  { q: 'Os dados dos meus clientes ficam seguros?', a: 'Sim. Os dados de cada barbearia ficam isolados, com acesso por função da equipe, conexão segura e cópia de segurança diária. Você pode exportar a sua base quando quiser.' },
]

const openFaq = ref<number | null>(0)
</script>

<template>
  <Head title="Degradê · Agenda online para barbearias">
    <meta
      head-key="description"
      name="description"
      content="Seu cliente agenda sozinho pelo link do Instagram, sem baixar app. Agenda por barbeiro, comissão automática e relatórios. Teste grátis."
    />
  </Head>

  <div class="min-h-dvh bg-[#0A0A0A] text-[#F5F5F5]">
    <!-- TOPO -->
    <header class="sticky top-0 z-20 bg-[#0A0A0A]/90 backdrop-blur border-b border-[#1F1F1F]">
      <div class="max-w-5xl mx-auto h-14 px-4 flex items-center justify-between">
        <span class="text-[18px] font-bold tracking-tight">Degradê</span>
        <div class="flex items-center gap-2">
          <Link href="/login" class="h-9 px-3 flex items-center text-[14px] text-[#A1A1A1] hover:text-white transition-colors">Entrar</Link>
          <Link href="/register" class="h-9 px-4 flex items-center rounded-[8px] bg-[#FFD60A] text-[#0A0A0A] text-[14px] font-bold hover:bg-[#FFE066] transition-colors">
            Testar grátis
          </Link>
        </div>
      </div>
    </header>

    <main>
      <!-- HERO -->
      <section class="max-w-5xl mx-auto px-4 pt-14 pb-16 md:pt-24 md:pb-24 text-center">
        <p class="inline-flex items-center gap-2 text-[12px] font-medium text-[#FFD60A] bg-[#FFD60A]/10 border border-[#FFD60A]/20 rounded-full px-3 py-1 mb-6">
          Agenda online para barbearias
        </p>
        <h1 class="text-[34px] leading-[1.1] md:text-[56px] font-bold tracking-tight max-w-3xl mx-auto">
          Seu cliente agenda sozinho.<br class="hidden sm:block" />
          <span class="text-[#FFD60A]">Você só corta.</span>
        </h1>
        <p class="text-[16px] md:text-[18px] text-[#A1A1A1] leading-relaxed max-w-xl mx-auto mt-5">
          Link de agendamento para a bio do Instagram, agenda por barbeiro e comissão calculada
          na hora. Sem o cliente baixar app, sem você responder mensagem de madrugada.
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3 mt-8">
          <Link href="/register" class="w-full sm:w-auto h-12 px-7 flex items-center justify-center rounded-[10px] bg-[#FFD60A] text-[#0A0A0A] text-[16px] font-bold hover:bg-[#FFE066] transition-colors shadow-[0_8px_24px_-8px_rgba(255,214,10,0.5)]">
            Testar grátis por {{ trialDays }} dias
          </Link>
          <a href="#precos" class="w-full sm:w-auto h-12 px-7 flex items-center justify-center rounded-[10px] border border-[#2A2A2A] text-[15px] font-medium text-white hover:border-[#3D3D3D] transition-colors">
            Ver preços
          </a>
        </div>
        <p class="text-[13px] text-[#6B6B6B] mt-4">Sem cartão de crédito. Sem fidelidade.</p>
      </section>

      <!-- DOR -->
      <section class="border-y border-[#1F1F1F] bg-[#0F0F0F]">
        <div class="max-w-3xl mx-auto px-4 py-14 text-center">
          <h2 class="text-[24px] md:text-[32px] font-bold tracking-tight">Horário vazio é dinheiro que não volta</h2>
          <p class="text-[15px] md:text-[17px] text-[#A1A1A1] leading-relaxed mt-4">
            Agendar pelo WhatsApp na mão toma o seu tempo, gera conflito de horário e deixa buraco na
            agenda. E a comissão no fim do mês vira conta de caderno. O Degradê organiza isso tudo
            num lugar só, direto no celular.
          </p>
        </div>
      </section>

      <!-- COMO FUNCIONA -->
      <section class="max-w-5xl mx-auto px-4 py-16 md:py-20">
        <h2 class="text-[24px] md:text-[32px] font-bold tracking-tight text-center">Como funciona</h2>
        <div class="grid md:grid-cols-3 gap-4 mt-10">
          <div v-for="s in steps" :key="s.n" class="bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-5">
            <span class="w-9 h-9 rounded-full bg-[#FFD60A] text-[#0A0A0A] text-[15px] font-bold flex items-center justify-center">{{ s.n }}</span>
            <h3 class="text-[17px] font-semibold mt-4">{{ s.title }}</h3>
            <p class="text-[14px] text-[#A1A1A1] leading-relaxed mt-1.5">{{ s.text }}</p>
          </div>
        </div>
      </section>

      <!-- FUNÇÕES -->
      <section class="border-t border-[#1F1F1F]">
        <div class="max-w-5xl mx-auto px-4 py-16 md:py-20">
          <h2 class="text-[24px] md:text-[32px] font-bold tracking-tight text-center">Tudo o que a barbearia precisa</h2>
          <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-10">
            <div v-for="f in features" :key="f.title" class="flex gap-4 bg-[#131313] border border-[#2A2A2A] rounded-[14px] p-5">
              <div class="w-10 h-10 rounded-[10px] bg-[#FFD60A]/10 flex items-center justify-center flex-shrink-0">
                <component :is="f.icon" :size="20" :stroke-width="2" class="text-[#FFD60A]" />
              </div>
              <div class="min-w-0">
                <h3 class="text-[15px] font-semibold">{{ f.title }}</h3>
                <p class="text-[13px] text-[#A1A1A1] leading-relaxed mt-1">{{ f.text }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- PREÇOS -->
      <section id="precos" class="border-t border-[#1F1F1F] bg-[#0F0F0F] scroll-mt-14">
        <div class="max-w-4xl mx-auto px-4 py-16 md:py-20">
          <h2 class="text-[24px] md:text-[32px] font-bold tracking-tight text-center">Preço justo, sem surpresa</h2>
          <p class="text-[15px] text-[#A1A1A1] text-center mt-3">Todas as funções nos dois planos. A diferença é só o tamanho da equipe.</p>
          <div class="grid md:grid-cols-2 gap-4 mt-10">
            <div
              v-for="p in plans"
              :key="p.plan"
              class="relative bg-[#131313] border rounded-[16px] p-6 flex flex-col"
              :class="p.staff_limit > 1 ? 'border-[#FFD60A]' : 'border-[#2A2A2A]'"
            >
              <span
                v-if="p.staff_limit > 1"
                class="absolute -top-2.5 left-6 px-2.5 py-0.5 rounded-full bg-[#FFD60A] text-[#0A0A0A] text-[11px] font-bold uppercase tracking-wide"
              >
                Mais escolhido
              </span>
              <h3 class="text-[20px] font-bold">{{ p.label }}</h3>
              <p class="text-[14px] text-[#A1A1A1] mt-1">
                {{ p.staff_limit === 1 ? 'Para quem atende sozinho' : `Equipe de até ${p.staff_limit} profissionais` }}
              </p>
              <p class="mt-5">
                <span class="text-[36px] font-bold text-[#FFD60A] tabular-nums">{{ formatBRL(p.price) }}</span>
                <span class="text-[14px] text-[#6B6B6B]">/mês</span>
              </p>
              <ul class="space-y-2 mt-5 mb-7 text-[14px] text-white/90">
                <li class="flex gap-2"><Check :size="17" :stroke-width="2.5" class="text-[#22C55E] flex-shrink-0 mt-0.5" />{{ p.staff_limit === 1 ? '1 profissional' : `Até ${p.staff_limit} profissionais` }}</li>
                <li class="flex gap-2"><Check :size="17" :stroke-width="2.5" class="text-[#22C55E] flex-shrink-0 mt-0.5" />Link de agendamento online</li>
                <li class="flex gap-2"><Check :size="17" :stroke-width="2.5" class="text-[#22C55E] flex-shrink-0 mt-0.5" />Agenda, clientes e comissões</li>
                <li class="flex gap-2"><Check :size="17" :stroke-width="2.5" class="text-[#22C55E] flex-shrink-0 mt-0.5" />Relatórios de faturamento</li>
                <li v-if="p.staff_limit > 1" class="flex gap-2"><Check :size="17" :stroke-width="2.5" class="text-[#22C55E] flex-shrink-0 mt-0.5" />Acesso da equipe por função</li>
              </ul>
              <Link
                href="/register"
                class="mt-auto h-12 flex items-center justify-center rounded-[10px] text-[15px] font-bold transition-colors"
                :class="p.staff_limit > 1 ? 'bg-[#FFD60A] text-[#0A0A0A] hover:bg-[#FFE066]' : 'border border-[#2A2A2A] text-white hover:border-[#FFD60A]'"
              >
                Começar {{ trialDays }} dias grátis
              </Link>
            </div>
          </div>
          <p class="flex items-center justify-center gap-2 text-[13px] text-[#6B6B6B] mt-6">
            <ShieldCheck :size="16" :stroke-width="2" />
            Cancele quando quiser, sem multa.
          </p>
        </div>
      </section>

      <!-- FAQ -->
      <section class="border-t border-[#1F1F1F]">
        <div class="max-w-2xl mx-auto px-4 py-16 md:py-20">
          <h2 class="text-[24px] md:text-[32px] font-bold tracking-tight text-center">Perguntas frequentes</h2>
          <div class="mt-8 divide-y divide-[#1F1F1F] border-y border-[#1F1F1F]">
            <div v-for="(f, i) in faqs" :key="f.q">
              <button
                type="button"
                class="w-full flex items-center justify-between gap-4 py-4 text-left"
                :aria-expanded="openFaq === i"
                @click="openFaq = openFaq === i ? null : i"
              >
                <span class="text-[15px] font-medium">{{ f.q }}</span>
                <ChevronDown :size="18" class="text-[#6B6B6B] flex-shrink-0 transition-transform" :class="openFaq === i ? 'rotate-180' : ''" />
              </button>
              <p v-show="openFaq === i" class="text-[14px] text-[#A1A1A1] leading-relaxed pb-4 -mt-1">{{ f.a }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- CTA FINAL -->
      <section class="border-t border-[#1F1F1F] bg-[#0F0F0F]">
        <div class="max-w-3xl mx-auto px-4 py-16 text-center">
          <h2 class="text-[26px] md:text-[34px] font-bold tracking-tight">Sua agenda organizada ainda hoje</h2>
          <p class="text-[15px] text-[#A1A1A1] mt-3">Crie a conta, cadastre seus serviços e compartilhe o link. Leva menos de 10 minutos.</p>
          <Link href="/register" class="inline-flex mt-8 h-12 px-8 items-center justify-center rounded-[10px] bg-[#FFD60A] text-[#0A0A0A] text-[16px] font-bold hover:bg-[#FFE066] transition-colors">
            Testar grátis por {{ trialDays }} dias
          </Link>
        </div>
      </section>
    </main>

    <footer class="border-t border-[#1F1F1F]">
      <div class="max-w-5xl mx-auto px-4 py-8 flex flex-col sm:flex-row items-center justify-between gap-3 text-[13px] text-[#6B6B6B]">
        <span>© {{ new Date().getFullYear() }} Degradê</span>
        <nav class="flex items-center gap-5">
          <a href="/terms" class="hover:text-white transition-colors">Termos</a>
          <a href="/privacy" class="hover:text-white transition-colors">Privacidade</a>
          <Link href="/login" class="hover:text-white transition-colors">Entrar</Link>
        </nav>
      </div>
    </footer>
  </div>
</template>
