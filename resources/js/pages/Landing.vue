<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import {
  CalendarCheck, Link2, Wallet, Users, BarChart3, ShieldCheck, Check, ChevronDown,
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
  { n: '1', title: 'Cadastre serviços, preços e equipe', text: 'Pelo celular, em uns 10 minutos. Os horários de cada barbeiro já vêm preenchidos e você ajusta.' },
  { n: '2', title: 'Coloque o link na bio e no WhatsApp', text: 'Cada barbearia recebe um endereço próprio, como degradeapp.com.br/agendar/sua-barbearia.' },
  { n: '3', title: 'Os horários caem na agenda', text: 'O cliente só vê horário livre do barbeiro que escolheu, então dois clientes nunca pegam o mesmo horário.' },
]

const features = [
  { icon: Link2, title: 'Link de agendamento', text: 'O cliente escolhe serviço, barbeiro, dia e horário pelo navegador do celular. Não precisa baixar aplicativo.' },
  { icon: CalendarCheck, title: 'Agenda por barbeiro', text: 'Encaixe de quem chega no balcão, remarcação, falta e atendimento concluído, cada um no seu horário.' },
  { icon: Wallet, title: 'Comissão por atendimento', text: 'Você define a porcentagem de cada barbeiro. Ao concluir o atendimento, o valor dele é calculado e guardado.' },
  { icon: Users, title: 'Ficha do cliente', text: 'Número de visitas, data da última vinda, observações e todos os atendimentos anteriores.' },
  { icon: BarChart3, title: 'Relatório de faturamento', text: 'Quanto entrou por dia, semana e mês, e o faturamento de cada barbeiro no período.' },
  { icon: ShieldCheck, title: 'Acesso da equipe', text: 'Gerente, recepção e barbeiro entram com login próprio e veem só o que é da função deles.' },
]

const faqs = [
  { q: 'Meu cliente precisa baixar algum aplicativo?', a: 'Não. Ele abre o seu link no navegador do celular, escolhe o horário e recebe a confirmação na tela, com a opção de salvar na agenda do próprio celular.' },
  { q: 'Preciso cadastrar cartão para testar?', a: `Não. O teste dura ${props.trialDays} dias, com todas as funções, e não pede cartão. Você só informa o pagamento se decidir assinar.` },
  { q: 'Tem fidelidade ou multa para cancelar?', a: 'Não. O cancelamento é feito na própria plataforma e o acesso continua até o fim do período que você já pagou.' },
  { q: 'Qual a diferença entre os planos?', a: 'O número de profissionais: o Solo tem 1 e o Barbearia tem até 10. As funções são as mesmas.' },
  { q: 'Quem da equipe vê o faturamento?', a: 'Só o dono e o gerente. Recepção e barbeiros veem a agenda e os clientes, mas não veem faturamento nem a comissão dos colegas.' },
  { q: 'Consigo levar meus clientes se sair?', a: 'Sim. O dono exporta a base de clientes em planilha a qualquer momento.' },
]

const openFaq = ref<number | null>(0)
</script>

<template>
  <Head title="Degradê · Agendamento online e comissão para barbearias">
    <meta
      head-key="description"
      name="description"
      content="Sistema para barbearia com link de agendamento online, agenda por barbeiro e cálculo de comissão. Teste grátis por 14 dias."
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
          Para barbearias
        </p>
        <h1 class="text-[34px] leading-[1.1] md:text-[56px] font-bold tracking-tight max-w-3xl mx-auto">
          Agendamento online e <span class="text-[#FFD60A]">controle de comissão</span> para barbearias
        </h1>
        <p class="text-[16px] md:text-[18px] text-[#A1A1A1] leading-relaxed max-w-xl mx-auto mt-5">
          O cliente escolhe o serviço, o barbeiro e um horário livre pelo link da bio do Instagram.
          Quando você conclui o atendimento, a comissão de cada barbeiro já está calculada.
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3 mt-8">
          <Link href="/register" class="w-full sm:w-auto h-12 px-7 flex items-center justify-center rounded-[10px] bg-[#FFD60A] text-[#0A0A0A] text-[16px] font-bold hover:bg-[#FFE066] transition-colors shadow-[0_8px_24px_-8px_rgba(255,214,10,0.5)]">
            Testar grátis por {{ trialDays }} dias
          </Link>
          <a href="#precos" class="w-full sm:w-auto h-12 px-7 flex items-center justify-center rounded-[10px] border border-[#2A2A2A] text-[15px] font-medium text-white hover:border-[#3D3D3D] transition-colors">
            Ver preços
          </a>
        </div>
        <p class="text-[13px] text-[#6B6B6B] mt-4">{{ trialDays }} dias grátis. O teste não pede cartão.</p>
      </section>

      <!-- DOR -->
      <section class="border-y border-[#1F1F1F] bg-[#0F0F0F]">
        <div class="max-w-3xl mx-auto px-4 py-14 text-center">
          <h2 class="text-[24px] md:text-[32px] font-bold tracking-tight">Se hoje a agenda roda pelo WhatsApp</h2>
          <p class="text-[15px] md:text-[17px] text-[#A1A1A1] leading-relaxed mt-4">
            Você responde mensagem entre um corte e outro, às vezes dois clientes ficam com o mesmo
            horário, e no fim do mês a comissão de cada barbeiro é somada no caderno. Com o Degradê o
            cliente marca pelo link, a agenda de cada barbeiro fica no celular e a comissão sai pronta.
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
          <h2 class="text-[24px] md:text-[32px] font-bold tracking-tight text-center">O que vem no sistema</h2>
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
          <h2 class="text-[24px] md:text-[32px] font-bold tracking-tight text-center">Planos</h2>
          <p class="text-[15px] text-[#A1A1A1] text-center mt-3">Os dois planos têm as mesmas funções. O que muda é quantos profissionais você cadastra.</p>
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
                Testar {{ trialDays }} dias grátis
              </Link>
            </div>
          </div>
          <p class="flex items-center justify-center gap-2 text-[13px] text-[#6B6B6B] mt-6">
            <ShieldCheck :size="16" :stroke-width="2" />
            Sem fidelidade. Ao cancelar, você usa até o fim do período pago.
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
          <h2 class="text-[26px] md:text-[34px] font-bold tracking-tight">Teste com a sua agenda de verdade</h2>
          <p class="text-[15px] text-[#A1A1A1] mt-3">Crie a conta, cadastre os serviços e mande o link para os seus clientes. São {{ trialDays }} dias para ver se funciona na sua barbearia.</p>
          <Link href="/register" class="inline-flex mt-8 h-12 px-8 items-center justify-center rounded-[10px] bg-[#FFD60A] text-[#0A0A0A] text-[16px] font-bold hover:bg-[#FFE066] transition-colors">
            Criar conta grátis
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
