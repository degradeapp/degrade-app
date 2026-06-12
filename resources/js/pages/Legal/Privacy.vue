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
      <h1 class="text-[18px] font-semibold text-white">Política de Privacidade</h1>
    </header>

    <main class="legal max-w-2xl mx-auto px-5 py-6 pb-24">
      <p class="updated">Última atualização: 9 de junho de 2026</p>

      <p>
        Esta Política de Privacidade explica como o Degradê coleta, usa, compartilha e protege dados
        pessoais, em conformidade com a Lei nº 13.709/2018 (Lei Geral de Proteção de Dados, "LGPD"). Ao
        usar a plataforma, você concorda com as práticas descritas aqui.
      </p>

      <h2>1. Quem é o controlador</h2>
      <p>
        O Degradê ([RAZÃO SOCIAL], CNPJ [CNPJ]) é o controlador dos dados da sua conta. Para assuntos de
        privacidade, fale com o nosso Encarregado (DPO) pelo e-mail [E-MAIL DO ENCARREGADO].
      </p>

      <h2>2. Dados que coletamos</h2>
      <ul>
        <li><strong>Cadastro:</strong> nome, e-mail, telefone e senha (guardada de forma cifrada).</li>
        <li><strong>Uso:</strong> registros de acesso e de ações no sistema (auditoria), endereço IP e dados do dispositivo/navegador.</li>
        <li><strong>Pagamento:</strong> processado pelo parceiro de pagamento (Asaas). Não armazenamos os dados completos do seu cartão.</li>
        <li><strong>Dados dos seus clientes:</strong> nome, telefone, histórico de atendimentos e valores que você cadastra. Esses dados são tratados em seu nome (veja o item 8).</li>
      </ul>

      <h2>3. Para que usamos e bases legais</h2>
      <ul>
        <li>Fornecer e operar a plataforma. Base: execução de contrato.</li>
        <li>Autenticar e manter a sua sessão. Base: execução de contrato.</li>
        <li>Enviar comunicações transacionais (confirmações e lembretes de agendamento). Base: execução de contrato e legítimo interesse.</li>
        <li>Processar a cobrança da assinatura. Base: execução de contrato e obrigação legal.</li>
        <li>Segurança, prevenção a fraude e auditoria. Base: legítimo interesse e obrigação legal.</li>
        <li>Prestar suporte. Base: execução de contrato.</li>
      </ul>

      <h2>4. Comunicações</h2>
      <p>
        Enviamos apenas mensagens <strong>transacionais</strong> necessárias ao serviço, como confirmação
        e lembrete de agendamento. <strong>Não usamos seus dados nem os dados dos seus clientes para
        marketing próprio.</strong>
      </p>

      <h2>5. Com quem compartilhamos</h2>
      <p>Não vendemos dados pessoais. Compartilhamos somente com:</p>
      <ul>
        <li>processador de pagamento (Asaas), para a cobrança da assinatura;</li>
        <li>provedores de infraestrutura e hospedagem que operam sob nossas instruções;</li>
        <li>plataforma de mensagens (Meta/WhatsApp), quando você ativa o envio de notificações;</li>
        <li>autoridades, quando exigido por lei ou ordem judicial.</li>
      </ul>
      <p>
        Alguns desses provedores podem estar localizados fora do Brasil. Nesses casos, a transferência
        internacional de dados observa as salvaguardas exigidas pela LGPD (art. 33), como cláusulas
        contratuais adequadas ou a adoção por países com grau de proteção compatível.
      </p>

      <h2>6. Cookies e sessão</h2>
      <p>
        Usamos cookies essenciais para autenticação e segurança da sessão. Não usamos cookies de
        publicidade nem de rastreamento de terceiros.
      </p>

      <h2>7. Retenção e exclusão</h2>
      <p>
        Mantemos seus dados enquanto a conta estiver ativa. Ao excluir a conta, ela fica recuperável por
        30 dias (e o seu e-mail fica reservado nesse prazo). Passado o prazo, os dados são apagados em
        definitivo. Alguns registros podem ser mantidos por prazo legal, por exemplo para cumprir
        obrigações fiscais.
      </p>

      <h2>8. Dados dos clientes da sua barbearia</h2>
      <p>
        Quanto aos dados que você cadastra sobre os clientes da sua barbearia, <strong>você é o
        controlador e o Degradê é o operador</strong>, nos termos da LGPD. Tratamos esses dados apenas
        para prestar o serviço a você e conforme as suas instruções. Cabe a você ter uma base legal para
        o tratamento e informar seus clientes sobre ele.
      </p>

      <h2>9. Seus direitos (LGPD, art. 18)</h2>
      <p>A qualquer momento você pode solicitar:</p>
      <ul>
        <li>confirmação de que tratamos seus dados e acesso a eles;</li>
        <li>correção de dados incompletos ou desatualizados;</li>
        <li>anonimização, bloqueio ou eliminação de dados desnecessários;</li>
        <li>portabilidade dos dados;</li>
        <li>informação sobre com quem compartilhamos;</li>
        <li>revogação de consentimento, quando aplicável.</li>
      </ul>
      <p>
        Para exercer esses direitos, escreva para [E-MAIL DO ENCARREGADO]. Você também pode excluir sua
        conta diretamente na plataforma, em Meu perfil.
      </p>
      <p>
        Se entender que o tratamento dos seus dados não está adequado, você pode apresentar uma
        reclamação à Autoridade Nacional de Proteção de Dados (ANPD).
      </p>

      <h2>10. Segurança</h2>
      <p>
        Adotamos medidas técnicas e organizacionais para proteger os dados, como senhas cifradas,
        controle de acesso por papel, isolamento dos dados de cada barbearia e conexão segura. Nenhum
        sistema é totalmente imune; em caso de incidente relevante, comunicaremos conforme a lei.
      </p>

      <h2>11. Menores de idade</h2>
      <p>
        A plataforma destina-se a profissionais maiores de 18 anos. Não coletamos intencionalmente dados
        de menores como titulares de conta.
      </p>

      <h2>12. Alterações nesta Política</h2>
      <p>
        Podemos atualizar esta Política. Mudanças relevantes serão comunicadas pela plataforma e a data
        de "última atualização" será revista.
      </p>

      <h2>13. Contato</h2>
      <p>
        [RAZÃO SOCIAL], CNPJ [CNPJ]. Encarregado pelo Tratamento de Dados (DPO): [E-MAIL DO ENCARREGADO].
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
