<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ChevronLeft } from 'lucide-vue-next'

const voltar = () => {
  if (window.history.length > 1) window.history.back()
  else router.visit('/register')
}
</script>

<template>
  <div class="min-h-screen bg-[#0A0A0A] text-[#F5F5F5]">
    <header class="sticky top-0 z-10 bg-[#131313] border-b border-[#2A2A2A] px-4 py-4 flex items-center gap-3">
      <button @click="voltar" class="-ml-2 text-[#A1A1A1] hover:text-white transition-colors flex-shrink-0" aria-label="Voltar">
        <ChevronLeft :size="26" :stroke-width="2" />
      </button>
      <h1 class="text-[18px] font-semibold text-white">Termos de Serviço</h1>
    </header>

    <main class="legal max-w-2xl mx-auto px-5 py-6 pb-24">
      <p class="updated">Última atualização: 9 de junho de 2026</p>

      <p>
        Estes Termos de Serviço ("Termos") regem o uso da plataforma Degradê ("Degradê", "plataforma",
        "nós"), um sistema de gestão para barbearias. Ao criar uma conta ou usar a plataforma, você
        ("você", "usuário", "assinante") declara que leu e concorda com estes Termos. Se não concordar,
        não use a plataforma.
      </p>

      <h2>1. O que é o Degradê</h2>
      <p>
        O Degradê é um software como serviço (SaaS) para gestão de barbearias, com funções de agenda,
        cadastro de clientes, serviços, comissões, relatórios e afins, acessado pela internet.
      </p>

      <h2>2. Cadastro e conta</h2>
      <p>
        Para usar a plataforma você deve ser maior de 18 anos e fornecer informações verdadeiras e
        atualizadas. Você é responsável por manter sua senha em sigilo e por toda atividade realizada
        na sua conta. Avise-nos imediatamente em caso de uso não autorizado.
      </p>

      <h2>3. Período de teste e assinatura</h2>
      <p>
        Oferecemos um período de teste gratuito (atualmente 14 dias), sem necessidade de cartão. Após o
        teste, o acesso às funções pagas depende da contratação de um plano. As assinaturas são
        recorrentes e renovam automaticamente até o cancelamento. Os planos e valores vigentes são
        exibidos na própria plataforma. Os pagamentos são processados por um parceiro (atualmente a
        Asaas); ao assinar, você também concorda com os termos do processador de pagamento.
      </p>

      <h2>4. Cancelamento, exclusão e direito de arrependimento</h2>
      <p>
        Você pode cancelar a assinatura a qualquer momento. O cancelamento encerra a renovação e o
        acesso às funções pagas conforme o ciclo vigente. Você também pode excluir sua conta quando
        quiser. Após a exclusão, a conta fica recuperável por 30 dias (basta fazer login novamente
        dentro do prazo) e, passado esse período, os dados são apagados em definitivo.
      </p>
      <p>
        <strong>Direito de arrependimento:</strong> se você contratar um plano pago pela internet, pode
        desistir em até 7 dias corridos, contados do início da cobrança, com reembolso integral dos
        valores pagos no período, conforme o art. 49 do Código de Defesa do Consumidor. O cancelamento é
        feito na própria plataforma, sem nenhum processo mais difícil do que o da contratação.
      </p>

      <h2>5. Uso aceitável</h2>
      <p>Você concorda em não:</p>
      <ul>
        <li>usar a plataforma para fins ilegais ou que violem direitos de terceiros;</li>
        <li>tentar acessar contas, dados ou áreas para os quais não tem autorização;</li>
        <li>sobrecarregar, comprometer ou fazer engenharia reversa do sistema;</li>
        <li>inserir conteúdo ilícito, ofensivo ou que não tenha direito de usar.</li>
      </ul>
      <p>Podemos suspender ou encerrar contas que violem estes Termos.</p>

      <h2>6. Dados dos seus clientes</h2>
      <p>
        Ao cadastrar dados de clientes da sua barbearia (como nome, telefone e histórico de
        atendimentos), <strong>você é o responsável (controlador)</strong> por esses dados e declara ter
        base legal para tratá-los, nos termos da Lei Geral de Proteção de Dados (LGPD). O Degradê atua
        como operador, tratando esses dados apenas para prestar o serviço a você. Veja a
        <a href="/privacy" target="_blank" rel="noopener">Política de Privacidade</a>.
      </p>

      <h2>7. Disponibilidade</h2>
      <p>
        Empenhamo-nos para manter a plataforma disponível e segura, mas o serviço é fornecido "no estado
        em que se encontra", sem garantia de funcionamento ininterrupto ou livre de erros. Podemos
        realizar manutenções, correções e atualizações.
      </p>

      <h2>8. Limitação de responsabilidade</h2>
      <p>
        Na máxima extensão permitida pela lei, o Degradê não se responsabiliza por danos indiretos,
        lucros cessantes ou perda de dados decorrentes do uso ou da impossibilidade de uso da
        plataforma. Recomendamos que você mantenha seus próprios registros das informações importantes.
      </p>

      <h2>9. Propriedade intelectual</h2>
      <p>
        A plataforma, a marca e o conteúdo do Degradê são de nossa titularidade. Os seus dados e os
        dados dos seus clientes continuam pertencendo a você.
      </p>

      <h2>10. Alterações nos Termos</h2>
      <p>
        Podemos atualizar estes Termos. Mudanças relevantes serão comunicadas pela plataforma. O uso
        continuado após a atualização significa concordância com a nova versão.
      </p>

      <h2>11. Lei aplicável e foro</h2>
      <p>
        Estes Termos são regidos pelas leis do Brasil. Fica eleito o foro da comarca de [COMARCA] para
        dirimir eventuais conflitos, salvo disposição legal em contrário.
      </p>

      <h2>12. Contato</h2>
      <p>
        Dúvidas sobre estes Termos podem ser enviadas para [E-MAIL DE CONTATO]. Operado por
        [RAZÃO SOCIAL], CNPJ [CNPJ].
      </p>
    </main>
  </div>
</template>

<style scoped>
.legal h2 {
  color: #fff;
  font-size: 15px;
  font-weight: 600;
  margin-top: 1.5rem;
  margin-bottom: 0.5rem;
}
.legal p {
  color: #a1a1a1;
  font-size: 13px;
  line-height: 1.75;
  margin-bottom: 0.5rem;
}
.legal ul {
  color: #a1a1a1;
  font-size: 13px;
  line-height: 1.75;
  list-style: disc;
  padding-left: 1.25rem;
  margin-bottom: 0.5rem;
}
.legal .updated {
  color: #6b6b6b;
  font-size: 12px;
  margin-bottom: 1.25rem;
}
.legal a {
  color: #ffd60a;
  text-decoration: underline;
  text-underline-offset: 2px;
}
.legal strong {
  color: #f5f5f5;
  font-weight: 600;
}
</style>
