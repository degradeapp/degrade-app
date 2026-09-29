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
      <p class="updated">Última atualização: 29 de setembro de 2026</p>

      <p>
        Esta Política de Privacidade explica como o Degradê coleta, usa, compartilha e protege dados
        pessoais, em conformidade com a Lei nº 13.709/2018 (Lei Geral de Proteção de Dados, "LGPD").
      </p>

      <div class="callout">
        <p>
          <strong>Agendou um horário numa barbearia pelo link?</strong> Veja o item 9: quem decide sobre
          o seu nome e celular é a barbearia, e o Degradê só guarda esses dados para ela.
        </p>
      </div>

      <h2>1. Quem é o controlador</h2>
      <p>
        O Degradê ([NOME OU RAZÃO SOCIAL], [CPF OU CNPJ]) é o controlador dos dados da conta de quem
        assina a plataforma (donos de barbearia e a equipe deles). Para assuntos de privacidade, fale com
        o nosso Encarregado (DPO) pelo e-mail [E-MAIL DO ENCARREGADO].
      </p>

      <h2>2. Dados que coletamos</h2>
      <ul>
        <li><strong>Cadastro:</strong> nome, e-mail, telefone e senha (guardada de forma cifrada).</li>
        <li><strong>Uso:</strong> registros de acesso e de ações no sistema (auditoria), endereço IP e dados do dispositivo/navegador.</li>
        <li><strong>Pagamento:</strong> CPF ou CNPJ do titular da assinatura (guardado cifrado) e o histórico de cobranças. O pagamento é processado pelo parceiro (Asaas); não recebemos nem armazenamos dados do seu cartão.</li>
        <li><strong>Aceite dos Termos:</strong> data e versão dos Termos e desta Política aceitos no cadastro.</li>
        <li><strong>Dados dos seus clientes:</strong> nome, telefone, histórico de atendimentos e valores que você cadastra ou que o cliente informa no seu link de agendamento. Esses dados são tratados em seu nome (veja o item 8).</li>
      </ul>

      <h2>3. Para que usamos e bases legais</h2>
      <ul>
        <li>Fornecer e operar a plataforma. Base: execução de contrato.</li>
        <li>Autenticar e manter a sua sessão. Base: execução de contrato.</li>
        <li>Enviar comunicações transacionais (ex.: redefinição de senha e, quando o recurso estiver ativo, confirmações e lembretes de agendamento). Base: execução de contrato e legítimo interesse.</li>
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
      <p>Não vendemos dados pessoais. Compartilhamos somente com os fornecedores abaixo (suboperadores), cada um para uma finalidade:</p>
      <ul>
        <li><strong>Hospedagem (servidor no Brasil):</strong> onde a plataforma e o banco de dados funcionam;</li>
        <li><strong>Asaas (Brasil):</strong> cobrança da assinatura;</li>
        <li><strong>Resend (EUA):</strong> envio de e-mails transacionais, como redefinição de senha;</li>
        <li><strong>Sentry (EUA):</strong> registro de erros técnicos, que pode incluir endereço IP e o identificador do usuário;</li>
        <li><strong>Cloudflare (armazenamento de cópias de segurança):</strong> backup cifrado do banco de dados;</li>
        <li><strong>Meta/WhatsApp:</strong> somente quando o envio de mensagens estiver ativo na sua barbearia;</li>
        <li>autoridades, quando exigido por lei ou ordem judicial.</li>
      </ul>
      <p>
        Quando o fornecedor está fora do Brasil, a transferência internacional observa as salvaguardas
        exigidas pela LGPD (art. 33), como cláusulas contratuais adequadas.
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
        definitivo. As cópias de segurança (backup) são substituídas em até 7 dias, então um dado apagado
        some também delas nesse prazo. Alguns registros podem ser mantidos por prazo legal, por exemplo
        para cumprir obrigações fiscais.
      </p>

      <h2>8. Dados dos clientes da sua barbearia</h2>
      <p>
        Quanto aos dados que você cadastra sobre os clientes da sua barbearia, <strong>você é o
        controlador e o Degradê é o operador</strong>, nos termos da LGPD. Tratamos esses dados apenas
        para prestar o serviço a você e conforme as suas instruções. Cabe a você ter uma base legal para
        o tratamento e informar seus clientes sobre ele. Como operador, o Degradê se compromete a:
      </p>
      <ul>
        <li>não usar esses dados para nenhuma finalidade própria (nem marketing, nem venda);</li>
        <li>manter os dados isolados por barbearia, com acesso restrito por função;</li>
        <li>comunicar você sem demora injustificada em caso de incidente de segurança que os envolva;</li>
        <li>oferecer as ferramentas para você atender os pedidos dos seus clientes: exportar a base e
          <strong>apagar os dados pessoais de um cliente</strong> (na ficha dele, opção "Apagar dados
          pessoais"), mantendo o histórico financeiro sem identificação;</li>
        <li>apagar os dados ao fim do contrato, nos prazos do item 7.</li>
      </ul>

      <h2>9. Se você é cliente de uma barbearia</h2>
      <p>
        Se você agendou pelo link de uma barbearia, <strong>a barbearia é a controladora</strong> do seu
        nome, celular e dos seus agendamentos, e o Degradê é o operador que guarda esses dados para ela.
        Eles são usados só para organizar o seu atendimento. Para acessar, corrigir ou apagar seus dados,
        fale com a barbearia; se preferir, escreva para [E-MAIL DO ENCARREGADO] que encaminhamos o seu
        pedido a ela.
      </p>

      <h2>10. Seus direitos (LGPD, art. 18)</h2>
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

      <h2>11. Segurança</h2>
      <p>
        Adotamos medidas técnicas e organizacionais para proteger os dados, como senhas e documentos
        cifrados, controle de acesso por papel, isolamento dos dados de cada barbearia, conexão segura
        (HTTPS) e trilha de auditoria. Nenhum
        sistema é totalmente imune; em caso de incidente relevante, comunicaremos conforme a lei.
      </p>

      <h2>12. Menores de idade</h2>
      <p>
        A conta da plataforma destina-se a profissionais maiores de 18 anos. Clientes menores de idade de
        uma barbearia devem ter o agendamento feito ou autorizado pelo responsável.
      </p>

      <h2>13. Alterações nesta Política</h2>
      <p>
        Podemos atualizar esta Política. Mudanças relevantes serão comunicadas pela plataforma e a data
        de "última atualização" será revista.
      </p>

      <h2>14. Contato</h2>
      <p>
        [NOME OU RAZÃO SOCIAL], [CPF OU CNPJ]. Encarregado pelo Tratamento de Dados (DPO):
        [E-MAIL DO ENCARREGADO].
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
.legal .callout {
  border: 1px solid #2a2a2a;
  background: #131313;
  border-radius: 12px;
  padding: 0.75rem 1rem;
  margin: 1rem 0 0.5rem;
}
.legal .callout p {
  margin-bottom: 0;
}
.legal strong {
  color: #f5f5f5;
  font-weight: 600;
}
</style>
