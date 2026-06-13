# 🧪 Degradê — Guia de Validação & Roadmap

> Atualizado: **13/06/2026**. Suíte: **315 testes verdes**. Roda 100% local sem custo
> (SQLite, MAIL=log, Asaas sandbox). Integrações externas (WhatsApp/Email/Asaas live)
> estão **congeladas** por decisão do dono.

## ▶️ Como subir
```bash
php artisan serve        # http://127.0.0.1:8000
npm run dev              # Vite com hot-reload (mudança aparece na hora)
```
- Login de teste: **owner@test.local / password**
- Mobile-first: DevTools ~390px. Accent **#FFD60A**, fundo #0A0A0A.

---

## ✅ Já validado por você
- [x] **Agendamento — lógica completa**: criar (wizard + "+"), detalhe, "Em Atendimento",
      **concluir**, **remarcar** (em qualquer status aberto), **cancelar** e **"não apareceu"**
- [x] **Agenda** — dia/semana, navegação de semana + "Hoje", filtros, status derivado,
      empilhamento de cards próximos ("+N")
- [x] **Cliente** — criar/listar/ver, histórico
- [x] **Serviços** — "Alterar serviços" mostra os personalizados
- [x] **Comissões** — fluxo entendido (gera na conclusão, dono não recebe), pagar em **lote** e **uma a uma**
- [x] **Folga** — mensagem "barbeiro de folga" no wizard
- [x] **Pelo "+"** — criar barbeiro e serviço
- [x] **Encerrar sem concluir** — "Cliente desmarcou" / "Cliente não apareceu"
- [x] **Fotos** — avatar (perfil), logo (barbearia), foto do barbeiro; reflete na Equipe e no hub
- [x] **Notificações** — só transacionais (avisos do agendamento + lembretes); marketing removido
- [x] **Permissões — TODOS OS 4 PAPÉIS** (dono, gerente, recepção, barbeiro): auditado em rota+página+policy+tela.
      Balcão (recepção/barbeiro) = agenda+clientes, gerencia atendimentos (concluir/remarcar/cancelar/falta);
      gerente = gestão menos Acessos/Cobrança; dono = tudo. **Linha do dinheiro (padrão salão)**: balcão vê
      preço/CRM mas NÃO agregados (receita, comissões, relatórios, total do dia na agenda). **WhatsApp**:
      credenciais (setup) só dono; caixa de entrada operacional. Banner/selo de plano só dono.
- [x] **Conta & plano** — excluir conta (senha + 30 dias de recuperação); cancelar assinatura
      está **bloqueado** (depende do Asaas)
- [x] **Início (dashboard) reformulado** — concluídos/a fazer/a concluir; seção "A concluir" com
      ações rápidas; ocupação sem no-show; navegação Início·Agenda·(+)·Clientes·Mais
- [x] **Fuso horário POR LOJA** — validado em runtime (Claude): mesmo "10:00" sai −04 em Manaus e
      −03 em SP exibindo a hora local; "hoje" é por loja (caso meia-noite). Teste: `TimezoneTest`
- [x] **Ciclo do dinheiro** — concluir → comissão em Pendentes → pagar (lote/individual) → Pagas;
      dono não gera comissão (só receita)

## 🧪 Falta VOCÊ validar (pronto, é só testar)

**Hub "Mais"**
- [x] **Relatórios** — testado e validado
- [x] **Acessos** — adicionar/remover login validado (mensagens de erro agora em pt-BR; 401 leva pro login)
- [ ] **Histórico** (auditoria em pt-BR detalhado)
- [ ] **Horários** da barbearia · **Barbearia** (nome, fuso, cancelamento)

## 🏢 Plano Rede — multiunidade (CONSTRUÍDO, falta VOCÊ validar)
- [x] **Construído** (289 testes verdes): várias unidades dentro do tenant, agenda/equipe por unidade,
      clientes/serviços compartilhados, seletor de unidade no topo, isolação por papel, relatório
      consolidado + por unidade. Gated no plano Rede.
- [ ] **Validar:** assine/coloque o tenant no plano **Rede**, crie uma 2ª unidade (Configurações →
      Unidades), troque de unidade no seletor do topo (agenda/dashboard mudam), cadastre barbeiro numa
      unidade, e veja o relatório consolidado (seção "Por unidade"). Confirme que barbeiro de uma
      unidade não vê a agenda da outra.

## ⚠️ Falta CONSTRUIR (antes de cobrar o plano cheio)
- [x] **Link público de agendamento — BACKEND** (Fable 5): `/api/public/agendar/{slug}`,
      escopado por slug, isolado por tenant, rate-limited, NÃO encaixa (respeita disponibilidade
      dura), `source=customer`. 19 testes. Falta a tela Vue `/agendar/{slug}` (fase frontend).

## 🔍 Auditoria backend (Fable 5) — aplicado + follow-ups
Aplicado nesta sessão (315 verdes, 0 regressão): link público + 3 correções de segurança:
escopa `exists` por tenant nos requests de agendamento (IDOR de escrita), assinatura
`X-Hub-Signature-256` no webhook do WhatsApp, e `BasePolicy::before` do dono não cruza tenant.
Pendente, em ordem de valor (detalhe em `fable5/RELATORIO_SEGURANCA.md` e `RELATORIO_EFICIENCIA.md`):
- [ ] **B1 (segurança):** `subscription.active` (variante JSON 402/403) nas rotas `/api/*` — hoje
      tenant suspenso/cancelado segue operando via API (só as páginas web barram).
- [ ] **Grep `exists:` sem escopo** nos demais FormRequests (`StoreBarberRequest.user_id` é o próximo).
- [ ] **E1 (eficiência):** busca no banco (pg_trgm) em vez de em PHP no `SearchService`;
      e trocar `Cache::flush()` global por chave versionada por tenant.
- [ ] **E2/E3:** N+1 do dashboard (`timeOffs` por barbeiro) e do `AppointmentPricer` (loop de serviços).
- [ ] **E5:** paginar a auditoria (`limit(100)` → cursor).

## 🔌 Falta INTEGRAR (externo — congelado)
- [ ] ⚠️ **WhatsApp Cloud API** — hoje dev/manual; produção precisa de Embedded Signup
- [ ] ⚠️ **E-mail (Resend)** — convites, confirmações, redefinição de senha
- [ ] ⚠️ **Asaas live** — hoje sandbox; falta CPF do dono + chaves reais
- [ ] ⚠️ **Storage de fotos** — hoje no disco local (dev); em produção vai pra object storage (S3 / Cloudflare R2) pra escalar e servir por CDN
- [ ] 🚀 **Deploy** — Hostinger/Coolify + Cloudflare

---

## 🎯 Plano pra fechar
**Testar** (novidades + hub "Mais" + ciclo do dinheiro) → **Construir** (multiunidade / link público, se for vender Rede) → **Integrar** (WhatsApp → Asaas → e-mail) → **Teste final** → **Deploy** 💰
