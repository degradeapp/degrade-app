# 🧪 Degradê — Guia de Validação & Roadmap

> Atualizado: **13/06/2026**. Suíte: **325 testes verdes**. Roda 100% local sem custo
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
Concluído (detalhe em `fable5/RELATORIO_SEGURANCA.md` e `RELATORIO_EFICIENCIA.md`):
- [x] **B1 (segurança):** `subscription.active` (JSON 402) nas rotas `/api/*` operacionais
      (appointments, customers, barbers, services, commissions, reports, units, whatsapp inbox,
      search). Conta/billing/auth/`/switch`/credenciais ficam abertos pra regularizar. `SubscriptionGateTest`.
- [x] **`exists:` escopado por tenant** em `StoreBarberRequest.user_id` (varredura concluída; era o último).
- [x] **E1:** `SearchService` filtra no banco em Postgres (unaccent ILIKE + GIN/pg_trgm via migration;
      SQLite mantém o filtro em memória) e o cache invalida por tenant (fim do `Cache::flush()` global).
- [x] **E2/E3:** N+1 removido (dashboard carrega `timeOffs` de hoje via eager load; `AppointmentPricer`
      pré-carrega barbeiros+pivot uma vez). `EfficiencyTest` prova que não escala com o nº de itens.
- [x] **E5:** auditoria paginada (`paginate`, `data` + `meta`, contrato preservado).
- [x] **B4 (LGPD):** webhook do WhatsApp não loga mais o payload completo (telefone/texto do cliente).
- [x] **B2:** webhooks (Asaas + WhatsApp) fecham a porta em produção sem secret (fail closed) em vez
      de aceitar. **Bug de prod corrigido junto:** os webhooks estavam no grupo web sob CSRF e levariam
      419 em produção (Asaas/Meta não mandam token); agora isentos de CSRF (autenticidade é o HMAC).
- [x] **E8:** removido o listener morto `InvalidateAvailabilityCache` (dava `Cache::forget` numa chave
      que NADA grava; não há cache de disponibilidade implementado).

Ainda em aberto (decisão sua / acoplado ao front): **B3** (convite permite 2º dono — recomendação:
deixar, é o caso de sócio; não mexi), **E4** (janela de 60 dias da agenda — acoplado ao front),
**E6** (Commission.pendingSummary agrupa em PHP — dispensado: os `items` são eager-loaded, não há
N+1, e o volume por mês é pequeno; mexer quebraria o contrato da tela). Detalhe nos relatórios.

## 🔌 Falta INTEGRAR (externo — congelado)
- [ ] ⚠️ **WhatsApp Cloud API** — hoje dev/manual; produção precisa de Embedded Signup
- [ ] ⚠️ **E-mail (Resend)** — convites, confirmações, redefinição de senha
- [ ] ⚠️ **Asaas live** — hoje sandbox; falta CPF do dono + chaves reais
- [ ] ⚠️ **Storage de fotos** — hoje no disco local (dev); em produção vai pra object storage (S3 / Cloudflare R2) pra escalar e servir por CDN
- [ ] 🚀 **Deploy** — Hostinger/Coolify + Cloudflare

---

## 🎯 Plano pra fechar
**Testar** (novidades + hub "Mais" + ciclo do dinheiro) → **Construir** (multiunidade / link público, se for vender Rede) → **Integrar** (WhatsApp → Asaas → e-mail) → **Teste final** → **Deploy** 💰
